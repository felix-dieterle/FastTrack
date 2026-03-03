/**
 * FastTrack – app.js
 * Vanilla JS / Fetch for clock-in/out, undo, entry edit/delete.
 */

'use strict';

/* ── Undo state ─────────────────────────────────────────────── */
let undoTimer      = null;
let undoCountdown  = null;
let undoToastInst  = null;

/* ── Helpers ────────────────────────────────────────────────── */

/**
 * POST to a given URL and return parsed JSON.
 * @param {string} url
 * @param {Object|FormData} data
 * @returns {Promise<Object>}
 */
async function postJSON(url, data) {
    const body = data instanceof FormData ? data : new URLSearchParams(data);
    const res  = await fetch(url, {
        method      : 'POST',
        credentials : 'same-origin',
        body,
    });
    return res.json();
}

/** Show a Bootstrap toast with a given message. */
function showToast(message, variant = 'success') {
    // Reuse a simple live-region toast if it exists, otherwise create one
    let container = document.getElementById('globalToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'globalToastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1100';
        document.body.appendChild(container);
    }
    const id   = 'toast_' + Date.now();
    const variantClass = typeof bootstrap !== 'undefined' ? `text-bg-${variant}` : `alert alert-${variant}`;
    const html = `
      <div id="${id}" class="toast align-items-center ${variantClass} border-0" role="alert" data-bs-delay="4000">
        <div class="d-flex">
          <div class="toast-body">${message}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>`;
    container.insertAdjacentHTML('beforeend', html);
    const el = document.getElementById(id);
    if (typeof bootstrap !== 'undefined') {
        const t  = new bootstrap.Toast(el);
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    } else {
        el.style.display = 'block';
        setTimeout(() => el.remove(), 4000);
    }
}

/* ── Clock In ───────────────────────────────────────────────── */

async function handleClockIn() {
    const btn = document.getElementById('clockBtn');
    if (btn) btn.disabled = true;

    try {
        const data = await postJSON('/api/clock_in.php', {});
        if (data.success) {
            showToast(`Eingestempelt um ${data.clock_in} Uhr`, 'success');
            showUndo('Eingestempelt');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message || 'Fehler beim Einstempeln.', 'danger');
            if (btn) btn.disabled = false;
        }
    } catch (e) {
        showToast('Netzwerkfehler – bitte erneut versuchen.', 'danger');
        if (btn) btn.disabled = false;
    }
}

/* ── Clock Out ──────────────────────────────────────────────── */

async function handleClockOut() {
    const btn = document.getElementById('clockBtn');
    if (btn) btn.disabled = true;

    try {
        const data = await postJSON('/api/clock_out.php', {});
        if (data.success) {
            showToast(`Ausgestempelt – ${data.duration} gearbeitet`, 'success');
            showUndo('Ausgestempelt');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message || 'Fehler beim Ausstempeln.', 'danger');
            if (btn) btn.disabled = false;
        }
    } catch (e) {
        showToast('Netzwerkfehler – bitte erneut versuchen.', 'danger');
        if (btn) btn.disabled = false;
    }
}

/* ── Undo ───────────────────────────────────────────────────── */

/**
 * Show the undo toast with 10-second countdown.
 * @param {string} actionLabel  Human-readable label for the action.
 */
function showUndo(actionLabel) {
    clearUndoTimer();

    const toastEl = document.getElementById('undoToast');
    if (!toastEl) return;

    document.getElementById('undoToastBody').textContent = actionLabel + ' – Rückgängig machen?';

    if (typeof bootstrap === 'undefined') return;
    undoToastInst = bootstrap.Toast.getOrCreateInstance(toastEl, { autohide: false });
    undoToastInst.show();

    let seconds = 10;
    const countdownEl   = document.getElementById('undoCountdown');
    const progressBar   = document.getElementById('undoProgressBar');

    if (countdownEl) countdownEl.textContent = seconds;
    if (progressBar) progressBar.style.width = '100%';

    undoCountdown = setInterval(() => {
        seconds--;
        if (countdownEl) countdownEl.textContent = seconds;
        if (progressBar) progressBar.style.width = (seconds * 10) + '%';
        if (seconds <= 0) {
            clearUndoTimer();
            undoToastInst?.hide();
        }
    }, 1000);
}

function clearUndoTimer() {
    if (undoTimer)    { clearTimeout(undoTimer);    undoTimer    = null; }
    if (undoCountdown){ clearInterval(undoCountdown); undoCountdown = null; }
}

async function handleUndo() {
    clearUndoTimer();
    undoToastInst?.hide();

    try {
        const data = await postJSON('/api/undo.php', {});
        if (data.success) {
            showToast(data.message || 'Rückgängig gemacht.', 'info');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Rückgängig machen fehlgeschlagen.', 'warning');
        }
    } catch (e) {
        showToast('Netzwerkfehler – bitte erneut versuchen.', 'danger');
    }
}

/* ── Entry Edit ─────────────────────────────────────────────── */

function editEntry(id) {
    // Hide all other open edit rows first
    document.querySelectorAll('.edit-row').forEach(r => {
        if (!r.classList.contains('d-none')) {
            const otherId = r.id.replace('edit-row-', '');
            cancelEdit(parseInt(otherId, 10));
        }
    });
    document.getElementById('row-' + id)?.classList.add('d-none');
    document.getElementById('edit-row-' + id)?.classList.remove('d-none');
}

function cancelEdit(id) {
    document.getElementById('edit-row-' + id)?.classList.add('d-none');
    document.getElementById('row-' + id)?.classList.remove('d-none');
}

async function saveEntry(event, id) {
    event.preventDefault();
    const form     = event.target;
    const formData = new FormData(form);
    formData.append('entry_id', id);

    // Convert datetime-local value (YYYY-MM-DDTHH:MM) to MySQL format
    const cin  = formData.get('clock_in').replace('T', ' ') + ':00';
    const cout = formData.get('clock_out') ? formData.get('clock_out').replace('T', ' ') + ':00' : '';
    formData.set('clock_in',  cin);
    formData.set('clock_out', cout);

    try {
        const data = await postJSON('/api/entry_update.php', formData);
        if (data.success) {
            showToast('Eintrag gespeichert.', 'success');
            showUndo('Eintrag aktualisiert');
            setTimeout(() => location.reload(), 800);
        } else {
            showToast(data.message || 'Fehler beim Speichern.', 'danger');
        }
    } catch (e) {
        showToast('Netzwerkfehler – bitte erneut versuchen.', 'danger');
    }
}

/* ── Entry Delete ───────────────────────────────────────────── */

async function confirmDelete(id) {
    if (!confirm('Eintrag wirklich löschen?')) return;

    try {
        const data = await postJSON('/api/entry_delete.php', { entry_id: id });
        if (data.success) {
            showToast('Eintrag gelöscht.', 'success');
            showUndo('Eintrag gelöscht');
            // Remove rows from DOM immediately
            document.getElementById('row-' + id)?.remove();
            document.getElementById('edit-row-' + id)?.remove();
            setTimeout(() => location.reload(), 2500);
        } else {
            showToast(data.message || 'Fehler beim Löschen.', 'danger');
        }
    } catch (e) {
        showToast('Netzwerkfehler – bitte erneut versuchen.', 'danger');
    }
}
