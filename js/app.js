// ── Helpers ────────────────────────────────────────────────────
function calcHrs(from, to) {
  if (!from || !to) return 0;
  const [fh, fm] = from.split(':').map(Number);
  const [th, tm] = to.split(':').map(Number);
  return ((th * 60 + tm) - (fh * 60 + fm)) / 60;
}

function formatTime(t) {
  if (!t) return '';
  const [h, m] = t.split(':').map(Number);
  const ampm = h >= 12 ? 'PM' : 'AM';
  const hour  = h % 12 || 12;
  return `${hour}:${m.toString().padStart(2, '0')} ${ampm}`;
}

// ── Log Modal ──────────────────────────────────────────────────
const modal         = document.getElementById('log-modal');
const modalOpenBtn  = document.getElementById('open-modal-btn');
const modalCloseBtn = document.getElementById('modal-close-btn');

function openModal()  { if (modal) modal.classList.add('open'); }
function closeModal() { if (modal) modal.classList.remove('open'); }

if (modalOpenBtn)  modalOpenBtn.addEventListener('click',  openModal);
if (modalCloseBtn) modalCloseBtn.addEventListener('click', closeModal);
if (modal) modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

const fromInput  = document.getElementById('log-from');
const toInput    = document.getElementById('log-to');
const hrsPreview = document.getElementById('hrs-preview');

function updateHrsPreview() {
  if (!fromInput || !toInput || !hrsPreview) return;
  const hrs = calcHrs(fromInput.value, toInput.value);
  hrsPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid range';
  hrsPreview.style.color = hrs > 0 ? 'var(--green)' : 'var(--red)';
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
  editPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid';
  editPreview.style.color = hrs > 0 ? 'var(--green)' : 'var(--red)';
}
if (editFrom) editFrom.addEventListener('change', updateEditPreview);
if (editTo)   editTo.addEventListener('change',   updateEditPreview);
if (editCloseBtn) editCloseBtn.addEventListener('click', () => editModal?.classList.remove('open'));
if (editModal)    editModal.addEventListener('click', e => { if (e.target === editModal) editModal.classList.remove('open'); });

document.querySelectorAll('.edit-btn').forEach(btn => {
  if (!btn.dataset.id) return;
  btn.addEventListener('click', () => {
    const id = document.getElementById('edit-log-id');
    const date = document.getElementById('edit-date');
    const desc = document.getElementById('edit-desc');
    const from = document.getElementById('edit-from');
    const to   = document.getElementById('edit-to');
    if (id)   id.value   = btn.dataset.id;
    if (date) date.value = btn.dataset.date;
    if (desc) desc.value = btn.dataset.desc;
    if (from) from.value = btn.dataset.from;
    if (to)   to.value   = btn.dataset.to;
    updateEditPreview();
    editModal?.classList.add('open');
  });
});

// ── Delete confirm ─────────────────────────────────────────────
document.querySelectorAll('.delete-form').forEach(form => {
  form.addEventListener('submit', e => {
    if (!confirm('Delete this log entry?')) e.preventDefault();
  });
});

// ── Multi-select delete ────────────────────────────────────────
const selectAll      = document.getElementById('select-all');
const bulkDeleteBar  = document.getElementById('bulk-delete-bar');
const bulkDeleteCount= document.getElementById('bulk-delete-count');
const bulkDeleteIds  = document.getElementById('bulk-delete-ids');
const bulkDeselect   = document.getElementById('bulk-deselect');
const bulkDeleteForm = document.getElementById('bulk-delete-form');

function getChecked() { return Array.from(document.querySelectorAll('.row-checkbox:checked')); }
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
        inp.type = 'hidden'; inp.name = 'log_ids[]'; inp.value = cb.value;
        bulkDeleteIds.appendChild(inp);
      });
    }
  } else {
    bulkDeleteBar.classList.remove('show');
    if (selectAll) selectAll.checked = false;
  }
}
if (selectAll) {
  selectAll.addEventListener('change', () => {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = selectAll.checked);
    updateBulkBar();
  });
}
document.querySelectorAll('.row-checkbox').forEach(cb => {
  cb.addEventListener('change', () => {
    const all = document.querySelectorAll('.row-checkbox');
    if (selectAll) selectAll.checked = getChecked().length === all.length;
    updateBulkBar();
  });
});
if (bulkDeselect) {
  bulkDeselect.addEventListener('click', () => {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    if (selectAll) selectAll.checked = false;
    updateBulkBar();
  });
}
if (bulkDeleteForm) {
  bulkDeleteForm.addEventListener('submit', e => {
    const count = getChecked().length;
    if (!confirm(`Delete ${count} log${count !== 1 ? 's' : ''}? This cannot be undone.`)) e.preventDefault();
  });
}

// ── Bulk log modal ─────────────────────────────────────────────
const bulkModal    = document.getElementById('bulk-modal');
const openBulkBtn  = document.getElementById('open-bulk-btn');
const bulkCloseBtn = document.getElementById('bulk-close-btn');

if (openBulkBtn)  openBulkBtn.addEventListener('click',  () => bulkModal?.classList.add('open'));
if (bulkCloseBtn) bulkCloseBtn.addEventListener('click', () => bulkModal?.classList.remove('open'));
if (bulkModal)    bulkModal.addEventListener('click', e => { if (e.target === bulkModal) bulkModal.classList.remove('open'); });

// Day toggles
document.querySelectorAll('.day-toggle').forEach(label => {
  label.addEventListener('click', () => {
    const cb = label.querySelector('input[type="checkbox"]');
    cb.checked = !cb.checked;
    label.classList.toggle('day-toggle--excluded', cb.checked);
    updateRangePreview();
  });
});

// Bulk hrs/day
const bulkHrs      = document.getElementById('bulk-hrs');
const bulkToHidden = document.getElementById('bulk-to-hidden');
function updateBulkToTime() {
  if (!bulkHrs || !bulkToHidden) return;
  const hrs   = parseFloat(bulkHrs.value) || 8;
  const toMin = 480 + Math.round(hrs * 60);
  bulkToHidden.value = `${Math.floor(toMin/60).toString().padStart(2,'0')}:${(toMin%60).toString().padStart(2,'0')}`;
  updateRangePreview();
}
if (bulkHrs) bulkHrs.addEventListener('change', updateBulkToTime);

// Range preview
const bulkStart    = document.getElementById('bulk-start');
const bulkEnd      = document.getElementById('bulk-end');
const rangePreview = document.getElementById('bulk-range-preview');
function updateRangePreview() {
  if (!bulkStart || !bulkEnd || !rangePreview) return;
  if (!bulkStart.value || !bulkEnd.value) { rangePreview.textContent = ''; return; }
  const start = new Date(bulkStart.value);
  const end   = new Date(bulkEnd.value);
  if (start > end) { rangePreview.textContent = 'Start must be before end.'; rangePreview.style.color = 'var(--red)'; return; }
  const excluded = Array.from(document.querySelectorAll('.day-toggle input:checked')).map(cb => parseInt(cb.value));
  let count = 0, cursor = new Date(start);
  while (cursor <= end) {
    const iso = cursor.getDay() === 0 ? 7 : cursor.getDay();
    if (!excluded.includes(iso)) count++;
    cursor.setDate(cursor.getDate() + 1);
  }
  const hrs = parseFloat(bulkHrs?.value) || 8;
  rangePreview.style.color = 'var(--green-dark)';
  rangePreview.textContent = `${count} day${count !== 1 ? 's' : ''} will be filled — ${(hrs * count).toFixed(1)} hrs total`;
}
if (bulkStart) bulkStart.addEventListener('change', updateRangePreview);
if (bulkEnd)   bulkEnd.addEventListener('change',   updateRangePreview);

// ── View toggle (logs page) ────────────────────────────────────
const viewCalBtn  = document.getElementById('view-cal-btn');
const viewListBtn = document.getElementById('view-list-btn');
const viewCal     = document.getElementById('view-calendar');
const viewList    = document.getElementById('view-list');

function setView(v) {
  if (!viewCal || !viewList) return;
  if (v === 'calendar') {
    viewCal.style.display  = 'block';
    viewList.style.display = 'none';
    viewCalBtn?.classList.add('active');
    viewListBtn?.classList.remove('active');
    localStorage.setItem('logs_view', 'calendar');
  } else {
    viewCal.style.display  = 'none';
    viewList.style.display = 'block';
    viewListBtn?.classList.add('active');
    viewCalBtn?.classList.remove('active');
    localStorage.setItem('logs_view', 'list');
  }
}
if (viewCal && viewList) {
  const savedView = localStorage.getItem('logs_view') || 'calendar';
  setView(savedView);
  viewCalBtn?.addEventListener('click',  () => setView('calendar'));
  viewListBtn?.addEventListener('click', () => setView('list'));
}

// ── Calendar (logs page) ───────────────────────────────────────
const selectedDays     = new Set();
const calActionBar     = document.getElementById('cal-action-bar');
const calSelectedCount = document.getElementById('cal-selected-count');
const calBulkIdsDiv    = document.getElementById('cal-bulk-ids');
const calDeselect      = document.getElementById('cal-deselect');
const calBulkForm      = document.getElementById('cal-bulk-delete-form');
const calSelectAll     = document.getElementById('cal-select-all');
const calBulkEditBtn   = document.getElementById('cal-bulk-edit-btn');
const calBulkEditModal = document.getElementById('cal-bulk-edit-modal');
const calBulkEditClose = document.getElementById('cal-bulk-edit-close');
const calBulkEditIds   = document.getElementById('cal-bulk-edit-ids');
const calEditFrom      = document.getElementById('cal-edit-from');
const calEditTo        = document.getElementById('cal-edit-to');
const calEditPreview   = document.getElementById('cal-edit-preview');

function buildBulkIds() {
  [calBulkIdsDiv, calBulkEditIds].forEach(container => {
    if (!container || !window.logData) return;
    container.innerHTML = '';
    selectedDays.forEach(date => {
      (window.logData[date] || []).forEach(l => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'log_ids[]'; inp.value = l.id;
        container.appendChild(inp);
      });
    });
  });
}

function updateCalActionBar() {
  if (selectedDays.size > 0) {
    calActionBar?.classList.add('show');
    if (calSelectedCount) calSelectedCount.textContent = selectedDays.size + ' day' + (selectedDays.size > 1 ? 's' : '') + ' selected';
    buildBulkIds();
  } else {
    calActionBar?.classList.remove('show');
  }
  const allCbs = document.querySelectorAll('.cal-day-cb');
  if (calSelectAll) calSelectAll.checked = allCbs.length > 0 && selectedDays.size === allCbs.length;
}

function toggleCalDay(cb) {
  const date = cb.dataset.date;
  const el   = cb.closest('.cal-day');
  if (cb.checked) { selectedDays.add(date); el.classList.add('cal-day--selected'); }
  else { selectedDays.delete(date); el.classList.remove('cal-day--selected'); }
  updateCalActionBar();
}
window.toggleCalDay = toggleCalDay;

if (calSelectAll) {
  calSelectAll.addEventListener('change', () => {
    document.querySelectorAll('.cal-day-cb').forEach(cb => {
      cb.checked = calSelectAll.checked;
      const date = cb.dataset.date;
      const el   = cb.closest('.cal-day');
      if (calSelectAll.checked) { selectedDays.add(date); el.classList.add('cal-day--selected'); }
      else { selectedDays.delete(date); el.classList.remove('cal-day--selected'); }
    });
    updateCalActionBar();
  });
}

if (calDeselect) {
  calDeselect.addEventListener('click', () => {
    document.querySelectorAll('.cal-day-cb').forEach(cb => { cb.checked = false; });
    document.querySelectorAll('.cal-day--selected').forEach(el => el.classList.remove('cal-day--selected'));
    selectedDays.clear();
    updateCalActionBar();
  });
}
if (calBulkForm) {
  calBulkForm.addEventListener('submit', e => {
    const total = document.querySelectorAll('#cal-bulk-ids input').length;
    if (!confirm(`Delete all logs for ${selectedDays.size} day(s)? (${total} entries)`)) e.preventDefault();
  });
}
if (calBulkEditBtn)   calBulkEditBtn.addEventListener('click', () => { buildBulkIds(); calBulkEditModal?.classList.add('open'); });
if (calBulkEditClose) calBulkEditClose.addEventListener('click', () => calBulkEditModal?.classList.remove('open'));
if (calBulkEditModal) calBulkEditModal.addEventListener('click', e => { if (e.target === calBulkEditModal) calBulkEditModal.classList.remove('open'); });

function updateCalEditPreview() {
  if (!calEditFrom || !calEditTo || !calEditPreview) return;
  const hrs = calcHrs(calEditFrom.value, calEditTo.value);
  calEditPreview.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid';
  calEditPreview.style.color = hrs > 0 ? 'var(--green)' : 'var(--red)';
}
if (calEditFrom) calEditFrom.addEventListener('change', updateCalEditPreview);
if (calEditTo)   calEditTo.addEventListener('change',   updateCalEditPreview);

function handleDayClick(el, event) {
  if (event.target.classList.contains('cal-day-cb')) return;
  const date     = el.dataset.date;
  const isLogged = el.dataset.logged === '1';
  if (isLogged) openDayModal(date);
  else openLogModal(date);
}
window.handleDayClick = handleDayClick;

// ── Log modal (calendar) ───────────────────────────────────────
const logModal      = document.getElementById('log-modal');
const logModalClose = document.getElementById('modal-close-btn');
const logDateInput  = document.getElementById('log-date');
const logFromEl     = document.getElementById('log-from');
const logToEl       = document.getElementById('log-to');
const hrsPreviewEl  = document.getElementById('hrs-preview');
const openModalBtnEl= document.getElementById('open-modal-btn');

function openLogModal(date) {
  if (logDateInput && date) logDateInput.value = date;
  logModal?.classList.add('open');
}
if (openModalBtnEl) openModalBtnEl.addEventListener('click', () => openLogModal(null));
if (logModalClose)  logModalClose.addEventListener('click', () => logModal?.classList.remove('open'));
if (logModal)       logModal.addEventListener('click', e => { if (e.target === logModal) logModal.classList.remove('open'); });

function updateHrsPreviewEl() {
  if (!logFromEl || !logToEl || !hrsPreviewEl) return;
  const hrs = calcHrs(logFromEl.value, logToEl.value);
  hrsPreviewEl.textContent = hrs > 0 ? hrs.toFixed(2) + ' hrs' : '— invalid';
  hrsPreviewEl.style.color = hrs > 0 ? 'var(--green)' : 'var(--red)';
}
if (logFromEl) logFromEl.addEventListener('change', updateHrsPreviewEl);
if (logToEl)   logToEl.addEventListener('change',   updateHrsPreviewEl);

// ── Day detail modal ───────────────────────────────────────────
const dayModal      = document.getElementById('day-modal');
const dayModalTitle = document.getElementById('day-modal-title');
const dayModalBody  = document.getElementById('day-modal-body');
const dayModalClose = document.getElementById('day-modal-close');
const dayModalAdd   = document.getElementById('day-modal-add');
let currentDayDate  = '';

function openDayModal(date) {
  if (!window.logData) return;
  currentDayDate = date;
  const logs = window.logData[date] || [];
  if (dayModalTitle) {
    const d = new Date(date + 'T00:00:00');
    dayModalTitle.textContent = 'Logs for ' + d.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
  }
  let html = '<div style="display:flex;flex-direction:column;gap:8px;">';
  logs.forEach(l => {
    html += `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:var(--bg2);border-radius:var(--radius-sm);border:1px solid var(--border);">
        <div>
          <div style="font-size:13px;font-weight:600;color:var(--text);">
            ${formatTime(l.from)} — ${formatTime(l.to)}
            <span style="font-family:'DM Mono',monospace;color:var(--green);margin-left:8px;">${parseFloat(l.hours).toFixed(2)} hrs</span>
          </div>
          ${l.desc ? `<div style="font-size:12px;color:var(--text3);margin-top:2px;">${l.desc}</div>` : ''}
        </div>
        <div style="display:flex;gap:4px;align-items:center;">
          <button type="button" class="edit-btn" onclick="openEditFromDay('${l.id}','${l.date}','${l.from}','${l.to}',\`${l.desc.replace(/`/g,"'")}\`)" title="Edit">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
          </button>
          <form method="POST" action="logs.php" style="display:inline;" onsubmit="return confirm('Delete this log?')">
            <input type="hidden" name="action" value="delete_log" />
            <input type="hidden" name="log_id" value="${l.id}" />
            <button type="submit" class="delete-btn" title="Delete">
              <svg viewBox="0 0 24 24" fill="currentColor" style="width:13px;height:13px;"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
            </button>
          </form>
        </div>
      </div>`;
  });
  html += '</div>';
  if (dayModalBody) dayModalBody.innerHTML = html;
  dayModal?.classList.add('open');
}

if (dayModalClose) dayModalClose.addEventListener('click', () => dayModal?.classList.remove('open'));
if (dayModal)      dayModal.addEventListener('click', e => { if (e.target === dayModal) dayModal.classList.remove('open'); });
if (dayModalAdd) {
  dayModalAdd.addEventListener('click', () => {
    dayModal?.classList.remove('open');
    openLogModal(currentDayDate);
  });
}

// ── Edit from day modal ────────────────────────────────────────
function openEditFromDay(id, date, from, to, desc) {
  dayModal?.classList.remove('open');
  const logId = document.getElementById('edit-log-id');
  const eDate = document.getElementById('edit-date');
  const eFrom = document.getElementById('edit-from');
  const eTo   = document.getElementById('edit-to');
  const eDesc = document.getElementById('edit-desc');
  if (logId) logId.value = id;
  if (eDate) eDate.value = date;
  if (eFrom) eFrom.value = from;
  if (eTo)   eTo.value   = to;
  if (eDesc) eDesc.value = desc;
  updateEditPreview();
  editModal?.classList.add('open');
}
window.openEditFromDay = openEditFromDay;