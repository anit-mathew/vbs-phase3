/* ══════════════════════════════════════
   info_modal.js — Page info button modal
   Loaded by standalone pages that don't
   include shared.js / shared.css
══════════════════════════════════════ */

(function() {
  /* Inject CSS if not already present (shared.css already includes it) */
  if (!document.getElementById('info-modal-css')) {
    const s = document.createElement('style');
    s.id = 'info-modal-css';
    s.textContent = `
.info-btn{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:rgba(255,255,255,.18);border:1.5px solid rgba(255,255,255,.45);color:white;font-size:.85rem;font-weight:900;cursor:pointer;font-family:'Nunito',sans-serif;line-height:1;flex-shrink:0;transition:background .18s,transform .15s;user-select:none}
.info-btn:hover{background:rgba(255,255,255,.35);transform:scale(1.1)}
.info-overlay{position:fixed;inset:0;background:rgba(10,20,50,.55);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;backdrop-filter:blur(3px);animation:infoFadeIn .18s ease}
@keyframes infoFadeIn{from{opacity:0}to{opacity:1}}
.info-modal{background:white;border-radius:20px;max-width:420px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.22);overflow:hidden;animation:infoSlideUp .2s ease}
@keyframes infoSlideUp{from{transform:translateY(16px);opacity:0}to{transform:translateY(0);opacity:1}}
.info-modal-header{padding:20px 22px 14px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.info-modal-icon{font-size:2rem;line-height:1;flex-shrink:0}
.info-modal-title{font-family:'Fredoka One',cursive;font-size:1.25rem;color:#1A2744;line-height:1.2;margin-top:2px}
.info-modal-close{width:28px;height:28px;border-radius:50%;border:none;background:#F1F5F9;color:#7A8BAD;font-size:1rem;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-weight:900;transition:background .15s}
.info-modal-close:hover{background:#E2E8F0;color:#1A2744}
.info-modal-body{padding:0 22px 22px}
.info-modal-desc{font-size:.88rem;color:#4A5568;line-height:1.6;margin-bottom:14px}
.info-modal-steps{display:flex;flex-direction:column;gap:9px}
.info-step{display:flex;gap:10px;align-items:flex-start;background:#F8FAFF;border-radius:10px;padding:9px 12px}
.info-step-num{min-width:22px;height:22px;border-radius:50%;background:#1A2744;color:white;font-size:.72rem;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px}
.info-step-text{font-size:.82rem;color:#2D3748;line-height:1.5;font-weight:600}
.info-modal-tip{margin-top:14px;padding:10px 12px;border-radius:10px;background:#FFFBEB;border:1px solid #FCD34D;font-size:.78rem;color:#92400E;font-weight:600;line-height:1.5}
    `;
    document.head.appendChild(s);
  }
})();

var PAGE_INFO = window.PAGE_INFO || {
  'command-center': {
    icon: '⛪', title: 'Command Center',
    desc: 'The main dashboard for VBS 2026. View real-time registration stats, volunteer assignments, and live attendance — all in one place.',
    steps: [
      'Select the <strong>Kids</strong> tab to see registered children grouped by grade or class.',
      'Select the <strong>Volunteers</strong> tab to view team assignments and roles.',
      'Use the <strong>Attendance</strong> tab to monitor who is currently checked in.',
      'Click <strong>↻ Refresh</strong> at any time to pull the latest data from the spreadsheet.',
      'Use the <strong>Grade / Class</strong> toggle to switch between views.'
    ],
    tip: '💡 This page is read-only. Use Check In / Check Out pages to update attendance.'
  },
  'checkin': {
    icon: '✅', title: 'Check In',
    desc: 'Used at the entrance desk to register children arriving for the day.',
    steps: [
      'Select the correct <strong>day</strong> from the dropdown at the top.',
      'Search for a child by name, family, or class using the search bar.',
      'Tap the child\'s card and click <strong>Check In</strong> to mark them as arrived.',
      'The green counter updates live as children are checked in.',
      'Use <strong>🪪 ID Cards</strong> to print wristbands or labels.'
    ],
    tip: '💡 Make sure the correct day is selected before checking anyone in.'
  },
  'checkout': {
    icon: '🚪', title: 'Check Out',
    desc: 'Used at the exit to safely release children to their authorized guardians.',
    steps: [
      'Select the correct <strong>day</strong> from the selector.',
      'Search for the child by name or family.',
      'Verify the guardian\'s identity, then tap <strong>Check Out</strong>.',
      'Only children who are currently checked in can be checked out.',
      'The stats bar shows how many children are still inside.'
    ],
    tip: '⚠️ Always verify guardian identity before releasing a child.'
  },
  'vol-checkin': {
    icon: '🙋', title: 'Volunteer Check-In',
    desc: 'Allows volunteers to sign in and out at the start and end of each VBS day.',
    steps: [
      'Select the correct <strong>day</strong> from the dropdown.',
      'Search for your name in the list.',
      'Tap your card and press <strong>Check In</strong> to mark yourself as present.',
      'Your team assignment and role will be shown on your card.',
      'Check-ins are recorded in real time.'
    ],
    tip: '💡 Volunteers should check in before campers start arriving.'
  },
  'merch': {
    icon: '🎽', title: 'Merch Station',
    desc: 'Manage T-shirt and merchandise distribution for VBS participants.',
    steps: [
      'Search for a participant by name to find their merch order.',
      'Review the item(s) listed on their card.',
      'Click <strong>Mark as Given</strong> once the item has been handed out.',
      'Use the filter tabs to view <strong>Pending</strong>, <strong>Given</strong>, or <strong>All</strong> orders.',
      'Stats at the top show how many items remain to be distributed.'
    ],
    tip: '💡 Items marked as given are saved instantly — no need to refresh.'
  }
};

function showInfoModal(pageKey) {
  var info = PAGE_INFO[pageKey];
  if (!info) return;
  var existing = document.getElementById('info-overlay');
  if (existing) existing.remove();
  var stepsHtml = info.steps.map(function(s, i) {
    return '<div class="info-step"><div class="info-step-num">' + (i+1) + '</div><div class="info-step-text">' + s + '</div></div>';
  }).join('');
  var tipHtml = info.tip ? '<div class="info-modal-tip">' + info.tip + '</div>' : '';
  var el = document.createElement('div');
  el.id = 'info-overlay';
  el.className = 'info-overlay';
  el.innerHTML =
    '<div class="info-modal" role="dialog" aria-modal="true">' +
      '<div class="info-modal-header">' +
        '<div style="display:flex;align-items:center;gap:10px">' +
          '<div class="info-modal-icon">' + info.icon + '</div>' +
          '<div class="info-modal-title">' + info.title + '</div>' +
        '</div>' +
        '<button class="info-modal-close" onclick="document.getElementById(\'info-overlay\').remove()" aria-label="Close">✕</button>' +
      '</div>' +
      '<div class="info-modal-body">' +
        '<div class="info-modal-desc">' + info.desc + '</div>' +
        '<div class="info-modal-steps">' + stepsHtml + '</div>' +
        tipHtml +
      '</div>' +
    '</div>';
  el.addEventListener('click', function(e) { if (e.target === el) el.remove(); });
  document.addEventListener('keydown', function esc(e) {
    if (e.key === 'Escape') { el.remove(); document.removeEventListener('keydown', esc); }
  });
  document.body.appendChild(el);
}

/* ── Data Version Polling (reset detection) for standalone pages ── */
(function() {
  var API = 'https://pypaonline.org/vbs/api.php';
  var KEY = 'vbs_data_version';
  var _timer = null;

  function startDataVersionPolling(onReset) {
    if (_timer) clearInterval(_timer);
    fetch(API + '?action=getDataVersion&t=' + Date.now())
      .then(function(r) { return r.json(); })
      .then(function(d) {
        if (d.status === 'ok') {
          var stored = sessionStorage.getItem(KEY);
          if (!stored) {
            sessionStorage.setItem(KEY, d.version);
          } else if (stored !== d.version) {
            sessionStorage.setItem(KEY, d.version);
            if (typeof onReset === 'function') onReset();
          }
        }
      }).catch(function() {});

    _timer = setInterval(function() {
      fetch(API + '?action=getDataVersion&t=' + Date.now())
        .then(function(r) { return r.json(); })
        .then(function(d) {
          if (d.status !== 'ok') return;
          var stored = sessionStorage.getItem(KEY);
          if (stored && stored !== d.version) {
            sessionStorage.setItem(KEY, d.version);
            if (typeof onReset === 'function') onReset();
          }
        }).catch(function() {});
    }, 15000);
  }

  function showResetBannerPage() {
    var b = document.getElementById('vbs-reset-banner');
    if (b) return;
    b = document.createElement('div');
    b.id = 'vbs-reset-banner';
    b.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:8888;background:#EF4444;color:white;text-align:center;padding:10px 16px;font-family:Nunito,sans-serif;font-weight:800;font-size:.88rem;display:flex;align-items:center;justify-content:center;gap:12px;box-shadow:0 2px 12px rgba(0,0,0,.2)';
    b.innerHTML = '⚠️ Data was reset by an administrator — refreshing now. <button onclick="this.parentNode.remove()" style="background:rgba(255,255,255,.25);border:none;border-radius:6px;padding:3px 10px;color:white;font-weight:800;cursor:pointer;font-family:Nunito,sans-serif">✕</button>';
    document.body.prepend(b);
    setTimeout(function() { if (b.parentNode) b.remove(); }, 8000);
  }

  window.startDataVersionPolling = window.startDataVersionPolling || startDataVersionPolling;
  window.showResetBannerPage = showResetBannerPage;
})();