// ── Helpers ────────────────────────────────────────────────────
function calcHrs(from, to) {
  if (!from || !to) return 0;
  const [fh, fm] = from.split(':').map(Number);
  const [th, tm] = to.split(':').map(Number);
  return ((th * 60 + tm) - (fh * 60 + fm)) / 60;
}

// ── Log Modal ──────────────────────────────────────────────────
const modal         = document.getElementById('log-modal');
const modalOpenBtn  = document.getElementById('open-modal-btn');
const modalOpenBtn2 = document.getElementById('open-modal-btn2');
const modalCloseBtn = document.getElementById('modal-close-btn');

function openModal()  { if (modal) modal.classList.add('open'); }
function closeModal() { if (modal) modal.classList.remove('open'); }

if (modalOpenBtn)  modalOpenBtn.addEventListener('click',  openModal);
if (modalOpenBtn2) modalOpenBtn2.addEventListener('click', openModal);
if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);
if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

const fromInput  = document.getElementById('log-from');
const toInput    = document.getElementById('log-to');
const hrsPreview = document.getElementById('hrs-preview');

function updateHrsPreview() {
  if (!fromInput || !toInput || !hrsPreview) return;
  const hrs = calcHrs(fromInput.value, toInput.value);
  if (hrs > 0) {
    hrsPreview.textContent = hrs.toFixed(2) + ' hrs';
    hrsPreview.style.color = 'var(--green)';
  } else {
    hrsPreview.textContent = '— invalid range';
    hrsPreview.style.color = 'var(--red)';
  }
}
if (fromInput) fromInput.addEventListener('change', updateHrsPreview);
if (toInput)   toInput.addEventListener('change',   updateHrsPreview);
updateHrsPreview();

// ── Edit Modal ─────────────────────────────────────────────────
const editModal    = document.getElementById('edit-modal');
const editCloseBtn = document.getElementById('edit-close-btn');
const editFrom     = document.getElementById('edit-from');
const editTo       = document.getElementById('edit-to');
const editPreview  = document.getElementById('edit-hrs-preview');

function updateEditPreview() {
  if (!editFrom || !editTo || !editPreview) return;
  const hrs = calcHrs(editFrom.value, editTo.value);
  if (hrs > 0) {
    editPreview.textContent = hrs.toFixed(2) + ' hrs';
    editPreview.style.color = 'var(--green)';
  } else {
    editPreview.textContent = '— invalid range';
    editPreview.style.color = 'var(--red)';
  }
}
if (editFrom) editFrom.addEventListener('change', updateEditPreview);
if (editTo)   editTo.addEventListener('change',   updateEditPreview);
if (editCloseBtn) editCloseBtn.addEventListener('click', () => editModal.classList.remove('open'));
if (editModal)    editModal.addEventListener('click', (e) => { if (e.target === editModal) editModal.classList.remove('open'); });

document.querySelectorAll('.edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('edit-log-id').value = btn.dataset.id;
    document.getElementById('edit-date').value   = btn.dataset.date;
    document.getElementById('edit-desc').value   = btn.dataset.desc;
    document.getElementById('edit-from').value   = btn.dataset.from;
    document.getElementById('edit-to').value     = btn.dataset.to;
    updateEditPreview();
    editModal.classList.add('open');
  });
});

// ── Delete confirm ─────────────────────────────────────────────
document.querySelectorAll('.delete-form').forEach(form => {
  form.addEventListener('submit', (e) => {
    if (!confirm('Delete this log entry?')) e.preventDefault();
  });
});

// ── Bulk Modal ─────────────────────────────────────────────────
const bulkModal    = document.getElementById('bulk-modal');
const openBulkBtn  = document.getElementById('open-bulk-btn');
const bulkCloseBtn = document.getElementById('bulk-close-btn');

function openBulkModal()  { if (bulkModal) bulkModal.classList.add('open'); }
function closeBulkModal() { if (bulkModal) bulkModal.classList.remove('open'); }

if (openBulkBtn)  openBulkBtn.addEventListener('click',  openBulkModal);
if (bulkCloseBtn) bulkCloseBtn.addEventListener('click', closeBulkModal);
if (bulkModal)    bulkModal.addEventListener('click', (e) => { if (e.target === bulkModal) closeBulkModal(); });

// ── Bulk hrs/day → hidden to-time ─────────────────────────────
const bulkHrs      = document.getElementById('bulk-hrs');
const bulkToHidden = document.getElementById('bulk-to-hidden');

function updateBulkToTime() {
  if (!bulkHrs || !bulkToHidden) return;
  const hrs      = parseFloat(bulkHrs.value) || 8;
  const totalMin = Math.round(hrs * 60);
  const toMin    = 480 + totalMin; // 08:00 = 480 min
  const h = Math.floor(toMin / 60).toString().padStart(2, '0');
  const m = (toMin % 60).toString().padStart(2, '0');
  bulkToHidden.value = `${h}:${m}`;
  updateRangePreview();
}
if (bulkHrs) bulkHrs.addEventListener('change', updateBulkToTime);

// ── Day toggles ────────────────────────────────────────────────
document.querySelectorAll('.day-toggle').forEach(label => {
  label.addEventListener('click', () => {
    const cb = label.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
    label.classList.toggle('day-toggle--excluded', cb.checked);
    updateRangePreview();
  });
});

// ── Range preview ──────────────────────────────────────────────
const bulkStart    = document.getElementById('bulk-start');
const bulkEnd      = document.getElementById('bulk-end');
const rangePreview = document.getElementById('bulk-range-preview');

function updateRangePreview() {
  if (!bulkStart || !bulkEnd || !rangePreview) return;
  if (!bulkStart.value || !bulkEnd.value) { rangePreview.textContent = ''; return; }

  const start = new Date(bulkStart.value);
  const end   = new Date(bulkEnd.value);
  if (start > end) {
    rangePreview.textContent = 'Start date must be before end date.';
    rangePreview.style.color = 'var(--red)';
    return;
  }

  const excluded = Array.from(document.querySelectorAll('.day-toggle input:checked'))
    .map(cb => parseInt(cb.value));

  let count  = 0;
  let cursor = new Date(start);
  while (cursor <= end) {
    const iso = cursor.getDay() === 0 ? 7 : cursor.getDay();
    if (!excluded.includes(iso)) count++;
    cursor.setDate(cursor.getDate() + 1);
  }

  const hrs   = parseFloat(bulkHrs?.value) || 8;
  const total = (hrs * count).toFixed(1);
  rangePreview.style.color = 'var(--green-dark)';
  rangePreview.textContent = `${count} day${count !== 1 ? 's' : ''} will be filled — ${total} hrs total`;
}

if (bulkStart) bulkStart.addEventListener('change', updateRangePreview);
if (bulkEnd)   bulkEnd.addEventListener('change',   updateRangePreview);

// ── Multi-select delete ────────────────────────────────────────
const selectAll      = document.getElementById('select-all');
const bulkDeleteBar  = document.getElementById('bulk-delete-bar');
const bulkDeleteCount= document.getElementById('bulk-delete-count');
const bulkDeleteIds  = document.getElementById('bulk-delete-ids');
const bulkDeselect   = document.getElementById('bulk-deselect');
const bulkDeleteForm = document.getElementById('bulk-delete-form');

function getChecked() {
  return Array.from(document.querySelectorAll('.row-checkbox:checked'));
}

function updateBulkBar() {
  const checked = getChecked();
  if (!bulkDeleteBar) return;

  if (checked.length > 0) {
    bulkDeleteBar.classList.add('show');
    if (bulkDeleteCount) bulkDeleteCount.textContent = checked.length + ' selected';

    if (bulkDeleteIds) {
      bulkDeleteIds.innerHTML = '';
      checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'log_ids[]';
        inp.value = cb.value;
        bulkDeleteIds.appendChild(inp);
      });
    }
  } else {
    bulkDeleteBar.classList.remove('show');
    if (selectAll) selectAll.checked = false;
  }
}

// Select all toggle
if (selectAll) {
  selectAll.addEventListener('change', () => {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
      cb.checked = selectAll.checked;
    });
    updateBulkBar();
  });
}

// Individual checkboxes
document.querySelectorAll('.row-checkbox').forEach(cb => {
  cb.addEventListener('change', () => {
    const all     = document.querySelectorAll('.row-checkbox');
    const checked = getChecked();
    if (selectAll) selectAll.checked = checked.length === all.length;
    updateBulkBar();
  });
});

// Deselect all
if (bulkDeselect) {
  bulkDeselect.addEventListener('click', () => {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    if (selectAll) selectAll.checked = false;
    updateBulkBar();
  });
}

// Confirm before bulk delete
if (bulkDeleteForm) {
  bulkDeleteForm.addEventListener('submit', (e) => {
    const count = getChecked().length;
    if (!confirm(`Delete ${count} log${count !== 1 ? 's' : ''}? This cannot be undone.`)) {
      e.preventDefault();
    }
  });
}

// ── Allowance Modal ────────────────────────────────────────────
const allowanceModal    = document.getElementById('allowance-modal');
const openAllowanceBtn  = document.getElementById('open-allowance-btn');
const allowanceCloseBtn = document.getElementById('allowance-close-btn');

if (openAllowanceBtn)  openAllowanceBtn.addEventListener('click',  () => allowanceModal.classList.add('open'));
if (allowanceCloseBtn) allowanceCloseBtn.addEventListener('click', () => allowanceModal.classList.remove('open'));
if (allowanceModal)    allowanceModal.addEventListener('click', (e) => {
  if (e.target === allowanceModal) allowanceModal.classList.remove('open');
});