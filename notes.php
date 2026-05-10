<?php
date_default_timezone_set('Asia/Manila');
require_once 'includes/config.php';
require_login();

$user       = current_user();
$active_page = 'notes';
$page_css   = 'css/notes.css';

// ── POST actions ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_note') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body']  ?? '');
        $tag   = trim($_POST['tag']   ?? 'General');
        $date  = $_POST['date'] ?? date('Y-m-d');
        if ($title && $body) {
            add_note($user['id'], [
                'id'    => generate_id(),
                'title' => $title,
                'body'  => $body,
                'tag'   => $tag,
                'date'  => $date,
            ]);
            set_flash('success', 'Note saved!');
        } else {
            set_flash('error', 'Title and body are required.');
        }
        header('Location: notes.php'); exit;
    }

    if ($action === 'edit_note') {
        $note_id = $_POST['note_id'] ?? '';
        $title   = trim($_POST['title'] ?? '');
        $body    = trim($_POST['body']  ?? '');
        $tag     = trim($_POST['tag']   ?? 'General');
        $date    = $_POST['date'] ?? date('Y-m-d');
        if ($note_id && $title && $body) {
            update_note($note_id, $user['id'], [
                'title' => $title,
                'body'  => $body,
                'tag'   => $tag,
                'date'  => $date,
            ]);
            set_flash('success', 'Note updated.');
        }
        header('Location: notes.php'); exit;
    }

    if ($action === 'delete_note') {
        $note_id = $_POST['note_id'] ?? '';
        if ($note_id) {
            delete_note($note_id, $user['id']);
            set_flash('success', 'Note deleted.');
        }
        header('Location: notes.php'); exit;
    }

    if ($action === 'add_tag') {
        $tag_name = trim($_POST['tag_name'] ?? '');
        if ($tag_name) {
            add_user_tag($user['id'], $tag_name);
        }
        header('Location: notes.php'); exit;
    }

    if ($action === 'delete_tag') {
        $tag_name = trim($_POST['tag_name'] ?? '');
        if ($tag_name) {
            delete_user_tag($user['id'], $tag_name);
        }
        header('Location: notes.php'); exit;
    }
}

// ── Fetch notes ───────────────────────────────────────────────
$all_notes = get_notes($user['id']);
$tags = get_user_tags($user['id']);
$active_tag = $_GET['tag'] ?? '';

// Filter by tag
if ($active_tag) {
    $all_notes = array_filter($all_notes, fn($n) => ($n['tag'] ?? 'General') === $active_tag);
    $all_notes = array_values($all_notes);
}
$total_notes = count($all_notes);

// ── Pagination ────────────────────────────────────────────────
$per_page     = 9;
$current_page = max(1, intval($_GET['page'] ?? 1));
$total_pages  = max(1, (int) ceil($total_notes / $per_page));
if ($current_page > $total_pages) $current_page = $total_pages;
$paginated    = array_slice($all_notes, ($current_page - 1) * $per_page, $per_page);

include 'includes/header.php';
?>

<div class="content">
<div class="dash-wrap notes-wrap">

  <!-- ── Hero header ── -->
  <div class="notes-hero">
    <div class="notes-hero-content">
      <div class="notes-eyebrow">OJT JOURNAL</div>
      <h1 class="notes-title">Notes <span class="notes-title-icon">📓</span></h1>
      <p class="notes-sub">Your personal diary for reflections, wins, and learnings.</p>
    </div>
    
    <div class="notes-hero-actions">
      <button type="button" class="btn btn-primary" id="open-note-modal-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
        </svg>
        New Note
      </button>
    </div>
  </div>

  <!-- ── Tag filter ── -->
  <div class="notes-tag-filter">
    <a href="notes.php" class="notes-tag-pill <?= $active_tag === '' ? 'notes-tag-pill--active' : '' ?>">All</a>
    <?php foreach ($tags as $t): ?>
      <span class="notes-tag-pill <?= $active_tag === $t ? 'notes-tag-pill--active' : '' ?>">
        <a href="notes.php?tag=<?= urlencode($t) ?>" class="tag-pill-link"><?= e($t) ?></a>
        <form method="POST" action="notes.php" style="display:inline;" onsubmit="return confirm('Delete tag?')">
          <input type="hidden" name="action" value="delete_tag" />
          <input type="hidden" name="tag_name" value="<?= e($t) ?>" />
          <button type="submit" class="tag-pill-x">×</button>
        </form>
      </span>
    <?php endforeach; ?>
    <button type="button" class="notes-tag-pill notes-tag-add" id="add-tag-btn">+</button>
  </div>

  <!-- ── Add tag inline ── -->
  <div class="notes-tag-manager" id="tag-manager" style="display:none;">
    <form method="POST" action="notes.php" class="tag-add-form">
      <input type="hidden" name="action" value="add_tag" />
      <input type="text" name="tag_name" placeholder="Tag name" class="form-input tag-input" required maxlength="30" />
      <button type="submit" class="btn btn-primary tag-add-btn">Add</button>
    </form>
  </div>

  <script>
    document.getElementById('add-tag-btn')?.addEventListener('click', () => {
      const mgr = document.getElementById('tag-manager');
      mgr.style.display = mgr.style.display === 'none' ? 'block' : 'none';
    });
  </script>

  <!-- ── Notes grid ── -->
  <?php if ($total_notes === 0): ?>
    <div class="notes-empty">
      <div class="notes-empty-icon">📓</div>
      <div class="notes-empty-title">No notes yet</div>
      <p class="notes-empty-sub">Start journaling your OJT journey. Jot down wins, challenges, or anything on your mind.</p>
      <button type="button" class="btn btn-primary" id="empty-add-note-btn" style="margin-top:1rem;">
        Write your first note
      </button>
    </div>
  <?php else: ?>
    <div class="notes-grid">
      <?php foreach ($paginated as $note): ?>
        <div class="note-card">
          <div class="note-card-top">
            <span class="note-date"><?= date('M j, Y', strtotime($note['date'])) ?></span>
          </div>
          <h3 class="note-card-title"><?= e($note['title']) ?></h3>
          <p class="note-card-body"><?= nl2br(e($note['body'])) ?></p>
          <div class="note-card-footer">
            <span class="note-created">Added <?= date('M j', strtotime($note['created_at'])) ?></span>
            <div class="note-card-actions">
              <button type="button" class="note-btn-edit"
                data-id="<?= e($note['id']) ?>"
                data-title="<?= e($note['title']) ?>"
                data-body="<?= e($note['body']) ?>"
                data-tag="<?= e($note['tag'] ?? 'General') ?>"
                data-date="<?= e($note['date']) ?>"
                aria-label="Edit note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              </button>
              <button type="button" class="note-btn-delete"
                data-id="<?= e($note['id']) ?>"
                data-title="<?= e($note['title']) ?>"
                aria-label="Delete note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ── Pagination ── -->
    <?php if ($total_pages > 1): ?>
      <div class="notes-pagination">
        <?php if ($current_page > 1): ?>
          <a href="?page=<?= $current_page - 1 ?>" class="btn btn-secondary btn-sm">← Prev</a>
        <?php endif; ?>
        <span class="pagination-info">Page <?= $current_page ?> of <?= $total_pages ?></span>
        <?php if ($current_page < $total_pages): ?>
          <a href="?page=<?= $current_page + 1 ?>" class="btn btn-secondary btn-sm">Next →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>

</div>
</div>

<!-- ═══════════════════════════════════
     ADD / EDIT NOTE MODAL
════════════════════════════════════ -->
<div class="modal-backdrop" id="note-modal-backdrop" aria-hidden="true"></div>
<div class="modal-sheet" id="note-modal" role="dialog" aria-modal="true" aria-labelledby="note-modal-title">
  <div class="modal-sheet-inner">
    <div class="modal-sheet-header">
      <span class="modal-sheet-title" id="note-modal-title">New Note</span>
      <button type="button" class="modal-close-btn" id="note-modal-close" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <form method="POST" action="notes.php" id="note-form">
      <input type="hidden" name="action" id="note-form-action" value="add_note" />
      <input type="hidden" name="note_id" id="note-form-id" value="" />

      <div class="form-group">
        <label class="form-label" for="note-title">Title</label>
        <input class="form-input" type="text" name="title" id="note-title" placeholder="Give this entry a title…" required maxlength="160" />
      </div>

      <div class="form-row note-form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
        <div class="form-group">
          <label class="form-label" for="note-date">Date</label>
          <input class="form-input" type="date" name="date" id="note-date" value="<?= date('Y-m-d') ?>" required />
        </div>
        <div class="form-group">
          <label class="form-label" for="note-tag">Tag</label>
          <select class="form-input" name="tag" id="note-tag">
            <?php foreach ($tags as $t): ?>
              <option value="<?= e($t) ?>"><?= e($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="note-body">Your Note</label>
        <textarea class="form-input note-textarea" name="body" id="note-body"
          placeholder="Write freely — what happened today? What did you learn? What are you proud of?" required rows="6"></textarea>
        <span class="form-hint note-char-counter" id="note-char-counter">0 characters</span>
      </div>

      <div class="modal-form-actions">
        <button type="button" class="btn btn-secondary" id="note-modal-cancel">Cancel</button>
        <button type="submit" class="btn btn-primary" id="note-submit-btn">Save Note</button>
      </div>
    </form>
  </div>
</div>

<!-- ═══════════════════════════════════
     DELETE CONFIRM MODAL
════════════════════════════════════ -->
<div class="modal-backdrop" id="delete-modal-backdrop" aria-hidden="true"></div>
<div class="modal-sheet modal-sheet--sm" id="delete-modal" role="dialog" aria-modal="true">
  <div class="modal-sheet-inner">
    <div class="modal-sheet-header">
      <span class="modal-sheet-title">Delete Note</span>
      <button type="button" class="modal-close-btn" id="delete-modal-close" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <p class="delete-confirm-text">Are you sure you want to delete <strong id="delete-note-title-preview"></strong>? This cannot be undone.</p>
    <form method="POST" action="notes.php" id="delete-note-form">
      <input type="hidden" name="action" value="delete_note" />
      <input type="hidden" name="note_id" id="delete-note-id" />
      <div class="modal-form-actions">
        <button type="button" class="btn btn-secondary" id="delete-modal-cancel">Cancel</button>
        <button type="submit" class="btn btn-danger">Delete</button>
      </div>
    </form>
  </div>
</div>

<script>
// ── Modal helpers ──────────────────────────────────────────────
const noteModal        = document.getElementById('note-modal');
const noteBackdrop     = document.getElementById('note-modal-backdrop');
const deleteModal      = document.getElementById('delete-modal');
const deleteBackdrop   = document.getElementById('delete-modal-backdrop');

function openNoteModal(mode = 'add', data = {}) {
    const title  = document.getElementById('note-modal-title');
    const action = document.getElementById('note-form-action');
    const noteId = document.getElementById('note-form-id');
    const inp_title = document.getElementById('note-title');
    const inp_body  = document.getElementById('note-body');
    const inp_tag   = document.getElementById('note-tag');
    const inp_date  = document.getElementById('note-date');

    if (mode === 'edit') {
        title.textContent       = 'Edit Note';
        action.value            = 'edit_note';
        noteId.value            = data.id    ?? '';
        inp_title.value         = data.title ?? '';
        inp_body.value          = data.body  ?? '';
        inp_tag.value           = data.tag   ?? 'General';
        inp_date.value          = data.date  ?? '<?= date('Y-m-d') ?>';
        document.getElementById('note-submit-btn').textContent = 'Save Changes';
    } else {
        title.textContent       = 'New Note';
        action.value            = 'add_note';
        noteId.value            = '';
        inp_title.value         = '';
        inp_body.value          = '';
        inp_tag.value           = 'General';
        inp_date.value          = '<?= date('Y-m-d') ?>';
        document.getElementById('note-submit-btn').textContent = 'Save Note';
    }
    updateCharCounter();
    noteModal.classList.add('open');
    noteBackdrop.classList.add('open');
    noteModal.setAttribute('aria-hidden', 'false');
    inp_title.focus();
}

function closeNoteModal() {
    noteModal.classList.remove('open');
    noteBackdrop.classList.remove('open');
    noteModal.setAttribute('aria-hidden', 'true');
}

function openDeleteModal(id, titleText) {
    document.getElementById('delete-note-id').value = id;
    document.getElementById('delete-note-title-preview').textContent = '"' + titleText + '"';
    deleteModal.classList.add('open');
    deleteBackdrop.classList.add('open');
}

function closeDeleteModal() {
    deleteModal.classList.remove('open');
    deleteBackdrop.classList.remove('open');
}

// ── Char counter ──────────────────────────────────────────────
function updateCharCounter() {
    const body    = document.getElementById('note-body');
    const counter = document.getElementById('note-char-counter');
    if (!body || !counter) return;
    const len = body.value.length;
    counter.textContent = len + ' character' + (len !== 1 ? 's' : '');
}
document.getElementById('note-body')?.addEventListener('input', updateCharCounter);

// ── Bindings ──────────────────────────────────────────────────
document.getElementById('open-note-modal-btn')?.addEventListener('click', () => openNoteModal('add'));
document.getElementById('empty-add-note-btn')?.addEventListener('click', () => openNoteModal('add'));
document.getElementById('note-modal-close')?.addEventListener('click', closeNoteModal);
document.getElementById('note-modal-cancel')?.addEventListener('click', closeNoteModal);
noteBackdrop?.addEventListener('click', closeNoteModal);

document.getElementById('delete-modal-close')?.addEventListener('click', closeDeleteModal);
document.getElementById('delete-modal-cancel')?.addEventListener('click', closeDeleteModal);
deleteBackdrop?.addEventListener('click', closeDeleteModal);

document.querySelectorAll('.note-btn-edit').forEach(btn => {
    btn.addEventListener('click', () => {
        openNoteModal('edit', {
            id:    btn.dataset.id,
            title: btn.dataset.title,
            body:  btn.dataset.body,
            tag:   btn.dataset.tag,
            date:  btn.dataset.date,
        });
    });
});

document.querySelectorAll('.note-btn-delete').forEach(btn => {
    btn.addEventListener('click', () => {
        openDeleteModal(btn.dataset.id, btn.dataset.title);
    });
});

window.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeNoteModal();
        closeDeleteModal();
    }
});
</script>

<?php include 'includes/footer.php'; ?>