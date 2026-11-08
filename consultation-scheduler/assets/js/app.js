/* Student Consultation Scheduler — dialogs (B-03/B-04), loading states, toasts, live notifications (FR-004) */
(function () {
  'use strict';
  var body = document.body;
  var base = body.dataset.base || '';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var lastFocus = null;

  /* ---------- overlays ---------- */
  function openScrim(el, focusSel) {
    lastFocus = document.activeElement;
    el.hidden = false;
    var f = (focusSel && $(focusSel, el)) || $('input:not([type=hidden]), button', el);
    if (f) { setTimeout(function () { f.focus(); }, 30); }
  }
  function closeScrim(el) {
    el.hidden = true;
    if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
  }
  document.addEventListener('click', function (e) {
    var open = e.target.closest('[data-open]');
    if (open) { openScrim(document.getElementById(open.dataset.open)); return; }
    var close = e.target.closest('[data-close]');
    if (close) { closeScrim(close.closest('.scrim')); return; }
    if (e.target.classList && e.target.classList.contains('scrim')) { closeScrim(e.target); }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { $$('.scrim:not([hidden])').forEach(closeScrim); }
  });

  /* ---------- confirmation dialog: Decline (B-03) and Cancel (B-04) ---------- */
  var dlg = $('#confirm-dialog');
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm]');
    if (!b || !dlg) { return; }
    var form = $('#cd-form', dlg);
    form.action = b.dataset.endpoint;
    $('#cd-action', dlg).value = b.dataset.confirm;
    $('#cd-id', dlg).value = b.dataset.id;
    $('#cd-title', dlg).textContent = b.dataset.title;
    $('#cd-body', dlg).textContent = b.dataset.body;
    $('#cd-ok', dlg).textContent = b.dataset.ok;
    $('#cd-ok', dlg).dataset.busyText = b.dataset.confirm === 'decline' ? 'Declining…' : 'Cancelling…';
    $('#cd-cancel', dlg).textContent = b.dataset.keep;
    var wrap = $('#cd-reason-wrap', dlg);
    wrap.hidden = b.dataset.confirm !== 'decline';
    $('input', wrap).value = '';
    b.classList.add('is-pressed');
    openScrim(dlg, '#cd-cancel');
  });

  /* ---------- loading / pressed states ---------- */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    var btn = e.submitter || $('button[type=submit]', f);
    if (!btn) { return; }
    if (btn.classList.contains('switch')) { // B-01: flip the switch immediately, then save
      var card = f.closest('.status-card');
      card.classList.toggle('is-on');
      card.classList.toggle('is-off');
      btn.classList.add('is-busy');
      return;
    }
    var text = btn.dataset.busyText || f.dataset.busy;
    if (text) { btn.textContent = text; }
    btn.classList.add('is-busy');
    setTimeout(function () { btn.disabled = true; }, 0);
  });

  /* ---------- password show / hide ---------- */
  $$('[data-toggle-pw]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = document.getElementById(b.dataset.togglePw);
      var show = i.type === 'password';
      i.type = show ? 'text' : 'password';
      b.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });
  /* "Forgot password?" keeps the role chosen on the login form */
  $$('[data-carry-role]').forEach(function (a) {
    a.addEventListener('click', function () {
      var r = $('input[name=role]:checked');
      if (r) { a.href = a.href.split('?')[0] + '?role=' + encodeURIComponent(r.value); }
    });
  });

  /* ---------- calendar overlay month paging ---------- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-cal]');
    if (!b) { return; }
    var cur = b.closest('.cal');
    var next = $('.cal[data-month="' + (parseInt(cur.dataset.month, 10) + parseInt(b.dataset.cal, 10)) + '"]');
    if (next) { cur.hidden = true; next.hidden = false; }
  });

  /* ---------- booking: keep the time hint in step with the chosen window (FR-04) ---------- */
  var hint = $('#time-hint');
  function syncWindow() {
    var r = $('input[name=slot_id]:checked');
    var s = $('input[name=start_time]'), en = $('input[name=end_time]');
    if (!r || !s) { return; }
    s.min = en.min = r.dataset.start; s.max = en.max = r.dataset.end;
    if (hint) { hint.lastChild.textContent = ' Must be within ' + r.closest('.window').querySelector('b').textContent; }
  }
  $$('input[name=slot_id]').forEach(function (r) { r.addEventListener('change', syncWindow); });
  syncWindow();

  /* ---------- instructor search: filter as you type ---------- */
  var q = $('#q'), list = $('#instructor-list');
  if (q && list) {
    q.addEventListener('input', function () {
      var term = q.value.trim().toLowerCase(), n = 0;
      $$('.row-card', list).forEach(function (row) {
        var hit = row.querySelector('b').textContent.toLowerCase().indexOf(term) !== -1;
        row.hidden = !hit; if (hit) { n++; }
      });
      var c = $('.panel-head .blue'); if (c) { c.textContent = n + ' found'; }
    });
  }

  /* ---------- toasts ---------- */
  function autohide(t) {
    var ms = parseInt(t.dataset.autohide || '6000', 10);
    setTimeout(function () { t.classList.add('is-leaving'); setTimeout(function () { t.remove(); }, 300); }, ms);
  }
  $$('.toast[data-autohide]').forEach(autohide);
  function toast(msg, type) {
    var stack = $('#toast-stack'); if (!stack) { return; }
    var t = document.createElement('div');
    t.className = 'toast toast-' + (type || 'info');
    t.setAttribute('role', 'status');
    t.dataset.autohide = '7000';
    var s = document.createElement('span'); s.textContent = msg; t.appendChild(s);
    stack.appendChild(t); autohide(t);
  }

  /* ---------- live notifications (FR-004 / NFR-001) ---------- */
  if (body.dataset.poll === '1') {
    var live = /(dashboard|consultations|instructors)\.php$/.test(location.pathname);
    var busy = false;
    var poll = function () {
      if (busy || document.hidden) { return; }
      busy = true;
      fetch(base + '/api/notifications.php', { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          busy = false;
          if (!d || !d.ok || !d.items.length) { return; }
          d.items.forEach(function (n) { toast(n.message, 'notify'); });
          // Refresh the list so the new status shows, unless the user is in the middle of something
          if (live) {
            setTimeout(function () {
              var typing = document.activeElement && /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName);
              if (!$('.scrim:not([hidden])') && !typing) {
                try { sessionStorage.setItem('scs-toasts', JSON.stringify(d.items.map(function (n) { return n.message; }))); } catch (e) {}
                location.reload();
              }
            }, 2500);
          }
        })
        .catch(function () { busy = false; });
    };
    setInterval(poll, parseInt(body.dataset.pollMs || '5000', 10));
    setTimeout(poll, 800);
    try { // re-show notifications that triggered the refresh
      var kept = JSON.parse(sessionStorage.getItem('scs-toasts') || '[]');
      sessionStorage.removeItem('scs-toasts');
      kept.forEach(function (m) { toast(m, 'notify'); });
    } catch (e) {}
  }
})();
