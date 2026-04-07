/* ══════════════════════════════════════
   shared.js — Utilities used by BOTH tabs.
   Edit this file to change: CSV parsing,
   tab switching, colour maps, t-shirt logic.
══════════════════════════════════════ */

/* ── Config ── */
const CSV_KIDS = "https://docs.google.com/spreadsheets/d/e/2PACX-1vQI-vHJhZBtLtBBn94Eq_0beJYuNhywqgP4dpgpd0sjPfvPzgnLq8NtAAQvYTmN_0OBFpb7X1q-G20Y/pub?output=csv";
const CSV_VOLS = "https://docs.google.com/spreadsheets/d/e/2PACX-1vT1qao6oa4ze6Hzmy6q6DeltkBWYzgr8Dtp29zYROsFbjpxpOqNveYjU2cNbSbIVAfduJEYsrXh1v83/pub?output=csv";

/* ── Tab Switcher ── */
function switchTab(name, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('panel-' + name).classList.add('active');
}

/* ── CSV Parser ── */
function parseCSV(text) {
  const lines = text.trim().split('\n');
  const headers = splitLine(lines[0]).map(h => h.trim().replace(/^"|"$/g, ''));
  return lines.slice(1).filter(l => l.trim()).map(line => {
    const vals = splitLine(line);
    const obj = {};
    headers.forEach((h, i) => obj[h] = (vals[i] || '').trim().replace(/^"|"$/g, ''));
    return obj;
  });
}
function splitLine(line) {
  const r = []; let c = '', q = false;
  for (let i = 0; i < line.length; i++) {
    if (line[i] === '"') { q = !q; }
    else if (line[i] === ',' && !q) { r.push(c); c = ''; }
    else c += line[i];
  }
  r.push(c); return r;
}

/* ── Colour Maps ── */
const GRADE_C = {
  'Pre':'#FF7043','K':'#FF9800','1st':'#FDD835','2nd':'#66BB6A',
  '3rd':'#26C6DA','4th':'#42A5F5','5th':'#7E57C2','6th':'#EC407A',
  '7th':'#8D6E63','8th':'#546E7A','default':'#9C6FDE'
};
function gradeColor(g) {
  for (const k of Object.keys(GRADE_C)) {
    if (k !== 'default' && g && g.includes(k)) return GRADE_C[k];
  }
  return GRADE_C.default;
}

const ROLE_C = {
  'Arts and Crafts':'#FF7043','Decoration':'#26C6DA','Media':'#42A5F5',
  'Food':'#FF9800','Worship Team':'#9C6FDE','Bible Lessons':'#66BB6A',
  'Medical Team':'#F06292','Crew Leader':'#EC407A','Recreation':'#26A69A',
  'Registration':'#5C6BC0','Facility Management':'#FFB300',
  'Facility Mangement':'#FFB300','default':'#7A8BAD'
};
const SHIRT_C    = ['#4FC3F7','#FF7043','#66BB6A','#9C6FDE','#FFB300','#26C6DA'];
const AGE_C      = {'19-30':'#4FC3F7','31-45':'#9C6FDE','46+':'#66BB6A','Under 18':'#FF7043'};
const DAY_COLORS = ['#26C6DA','#9C6FDE','#FF7043'];

/* ── T-Shirt Summary ── */
// Size display order — full descriptive names matching sheet values
const SIZE_ORDER = [
  '2T','3T','4T',
  '2-4 (Kids)','6-8 (Kids)','10-12 (Kids)','14-16 (Kids)',
  'Small (Adult)','Medium (Adult)','Large (Adult)','X-Large (Adult)','XXL (Adult)','2XL (Adult)','3XL (Adult)',
  'Small (Youth)','Medium (Youth)','Large (Youth)','X-Large (Youth)','X-Large (Youth)','XXL (Youth)',
];

// Normalize raw sheet value → full descriptive display name
// Sheet values look like: "Small (Adult)", "2 - 4 (kids size)", "X- Large (Youth)", "Medium (Youth)"
function normalizeSize(raw) {
  if (!raw) return null;
  const s = raw.trim();
  if (!s) return null;

  // Strip whitespace inside and normalize dashes for matching
  const u = s.toUpperCase().replace(/[\s\-–—]+/g, '');

  // ── Kids numeric sizes ──
  // Matches: "2 - 4 (kids size)", "2-4 (kids size)", "2-4 kids", "2-4"
  if (/^2[-–—\s]*4(\s*\(?\s*kids?\s*size?\s*\)?)?$/i.test(s))  return '2-4 (Kids)';
  if (/^6[-–—\s]*8(\s*\(?\s*kids?\s*size?\s*\)?)?$/i.test(s))  return '6-8 (Kids)';
  if (/^10[-–—\s]*12(\s*\(?\s*kids?\s*size?\s*\)?)?$/i.test(s)) return '10-12 (Kids)';
  if (/^14[-–—\s]*16(\s*\(?\s*kids?\s*size?\s*\)?)?$/i.test(s)) return '14-16 (Kids)';

  // ── Toddler ──
  if (u === '2T') return '2T';
  if (u === '3T') return '3T';
  if (u === '4T') return '4T';

  // ── Detect type suffix ──
  const isAdult = /adult/i.test(s);
  const isYouth = /youth/i.test(s);

  // Strip suffix to get the base size letter
  const base = s
    .replace(/\s*\(?\s*adult\s*\)?\s*$/i, '')
    .replace(/\s*\(?\s*youth\s*\)?\s*$/i, '')
    .replace(/\s*\(?\s*kids?\s*size?\s*\)?\s*$/i, '')
    .trim();
  const ub = base.toUpperCase().replace(/[\s\-–—]+/g, '');

  // Map base to full word
  let fullBase = null;
  if (ub === 'XS' || ub === 'XSMALL' || ub === 'XTRASMALL')            fullBase = 'XS';
  else if (ub === 'S'  || ub === 'SM'   || ub === 'SMALL')              fullBase = 'Small';
  else if (ub === 'M'  || ub === 'MED'  || ub === 'MEDIUM')             fullBase = 'Medium';
  else if (ub === 'L'  || ub === 'LG'   || ub === 'LARGE')              fullBase = 'Large';
  else if (ub === 'XL' || ub === 'XLARGE' || ub === 'EXTRALARGE')       fullBase = 'X-Large';
  else if (ub === 'XXL'|| ub === '2XL'  || ub === 'XXLARGE')            fullBase = 'XXL';
  else if (ub === '3XL'|| ub === 'XXXL' || ub === 'XXXLARGE')           fullBase = '3XL';
  // Handle "X- Large" → base becomes "X Large" after dash strip → ub = XLARGE
  else if (ub === 'XLARGE')                                              fullBase = 'X-Large';

  if (fullBase) {
    if (isAdult) return fullBase + ' (Adult)';
    if (isYouth) return fullBase + ' (Youth)';
    return fullBase; // bare size with no qualifier
  }

  // Fallback — return cleaned original
  return s.replace(/\s+/g, ' ').trim() || null;
}

function countShirts(people, getSize) {
  const counts = {};
  people.forEach(p => {
    const size = normalizeSize(getSize(p));
    if (size) counts[size] = (counts[size] || 0) + 1;
  });
  return counts;
}

function sortedSizes(counts) {
  return Object.keys(counts).sort((a, b) => {
    const ai = SIZE_ORDER.indexOf(a), bi = SIZE_ORDER.indexOf(b);
    if (ai === -1 && bi === -1) return a.localeCompare(b);
    if (ai === -1) return 1; if (bi === -1) return -1;
    return ai - bi;
  });
}

function renderShirtBars(counts, color) {
  const sizes = sortedSizes(counts);
  if (!sizes.length) return '<p style="color:var(--muted);font-size:.8rem">No data yet</p>';
  const maxN = Math.max(...Object.values(counts), 1);
  return sizes.map(s => `
    <div class="tshirt-size-row">
      <div class="tshirt-size-lbl">${s}</div>
      <div class="tshirt-bar-track">
        <div class="tshirt-bar-fill" style="width:${counts[s]/maxN*100}%;background:${color}">${counts[s]} shirt${counts[s]>1?'s':''}</div>
      </div>
      <div class="tshirt-bar-n">${counts[s]}</div>
    </div>`).join('');
}

function refreshTshirtSummary() {
  const el = document.getElementById('k-tshirt');
  if (!el) return;
  const kids = window._allKids || null;
  const vols = window._allVols || null;
  if (!kids && !vols) { el.innerHTML = ''; return; }

  const kCounts = kids ? countShirts(kids, k => k.tshirt) : {};
  const vCounts = vols ? countShirts(vols, v => v.tshirt) : {};
  const combined = { ...kCounts };
  Object.entries(vCounts).forEach(([s, n]) => combined[s] = (combined[s] || 0) + n);

  const grandTotal = Object.values(combined).reduce((a, b) => a + b, 0);
  const kTotal     = Object.values(kCounts).reduce((a, b) => a + b, 0);
  const vTotal     = Object.values(vCounts).reduce((a, b) => a + b, 0);

  const grandChips = sortedSizes(combined).map(s =>
    '<div class="tshirt-grand-chip">' +
      '<div class="gc-size">' + s + '</div>' +
      '<div class="gc-n">' + combined[s] + '</div>' +
      '<div class="gc-sub">' + (kCounts[s]||0) + 'K + ' + (vCounts[s]||0) + 'V</div>' +
    '</div>'
  ).join('');

  // ── Class-wise shirt breakdown (respects display mode) ──
  var displayMode = window._displayMode || 'grade';

  var GRADE_GROUPS = [
    { key:'Pre-K',       label:'Pre-K',        emoji:'🎈', grades:['Pre K'] },
    { key:'Pre-Primary', label:'Pre-Primary',  emoji:'🎠', grades:['K','1st','2nd'] },
    { key:'Primary',     label:'Primary',      emoji:'📘', grades:['3rd','4th','5th'] },
    { key:'Junior',      label:'Junior',       emoji:'🎓', grades:['6th','7th','8th'] }
  ];

  var GRADE_ORDER = [
    { key:'Pre K', label:'Pre-K',        emoji:'🎈' },
    { key:'K',     label:'Kindergarten', emoji:'🌟' },
    { key:'1st',   label:'1st Grade',    emoji:'📚' },
    { key:'2nd',   label:'2nd Grade',    emoji:'✏️' },
    { key:'3rd',   label:'3rd Grade',    emoji:'🚀' },
    { key:'4th',   label:'4th Grade',    emoji:'⚽' },
    { key:'5th',   label:'5th Grade',    emoji:'🔥' },
    { key:'6th',   label:'6th Grade',    emoji:'🎨' },
    { key:'7th',   label:'7th Grade',    emoji:'👑' },
    { key:'8th',   label:'8th Grade',    emoji:'🏆' }
  ];

  var classShirtRows = '<p style="color:var(--muted);font-size:.8rem">⏳ Loading kids data…</p>';
  if (kids) {
    var classRowsHtml = '';
    var groupList = displayMode === 'class' ? GRADE_GROUPS : GRADE_ORDER;
    groupList.forEach(function(grp) {
      var groupKids;
      if (displayMode === 'class') {
        groupKids = kids.filter(function(k) {
          return grp.grades.some(function(g) { return k.grade && k.grade.trim().toLowerCase() === g.toLowerCase(); });
        });
      } else {
        groupKids = kids.filter(function(k) { return k.grade && k.grade.trim().toLowerCase() === grp.key.toLowerCase(); });
      }
      var counts = countShirts(groupKids, function(k) { return k.tshirt; });
      var total  = Object.values(counts).reduce(function(a, b) { return a + b; }, 0);
      if (!total) return;
      classRowsHtml +=
        '<div style="margin-bottom:14px">' +
          '<div class="tshirt-group-title">' + grp.emoji + ' ' + grp.label +
            '<span class="tshirt-group-badge" style="background:rgba(26,39,68,.06);border:1px solid var(--border);color:var(--navy)">' +
              total + ' shirt' + (total > 1 ? 's' : '') +
            '</span>' +
          '</div>' +
          renderShirtBars(counts, gradeColor(grp.label)) +
        '</div>';
    });
    classShirtRows = classRowsHtml || '<p style="color:var(--muted);font-size:.8rem">No shirt data per class</p>';
  }

  el.innerHTML =
    '<div class="ts-accordion">' +
      '<div class="ts-header" onclick="toggleTshirt(this)">' +
        '<div class="ts-header-left">' +
          '<span style="font-size:1.3rem">👕</span>' +
          '<span class="ts-title">T-Shirt Order Summary</span>' +
          '<span class="tshirt-total-badge">🛒 ' + grandTotal + ' total shirts</span>' +
          '<button onclick="event.stopPropagation();showStoreOrder()" style="margin-left:6px;background:#7C3AED;color:white;border:none;border-radius:20px;padding:3px 12px;font-size:.7rem;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap">🛍️ Store Order</button>' +
        '</div>' +
        '<span class="ts-chevron">▼</span>' +
      '</div>' +
      '<div class="ts-body">' +
        '<div class="tshirt-summary">' +
          '<div>' +
            '<div class="tshirt-group-title">🧒 Kids' +
              '<span class="tshirt-group-badge" style="background:rgba(79,195,247,.12);border:1px solid rgba(79,195,247,.3);color:#0369a1">' + kTotal + ' shirts</span>' +
            '</div>' +
            (kids ? renderShirtBars(kCounts,'#4FC3F7') : '<p class="tshirt-waiting">⏳ Loading kids data…</p>') +
          '</div>' +
          '<div>' +
            '<div class="tshirt-group-title">🙋 Volunteers' +
              '<span class="tshirt-group-badge" style="background:rgba(102,187,106,.12);border:1px solid rgba(102,187,106,.3);color:#166534">' + vTotal + ' shirts</span>' +
            '</div>' +
            (vols ? renderShirtBars(vCounts,'#66BB6A') : '<p class="tshirt-waiting">⏳ Loading volunteer data…</p>') +
          '</div>' +
        '</div>' +
        '<hr class="tshirt-divider">' +
        '<div class="tshirt-group-title" style="margin-bottom:12px">🎒 Kids by ' + (displayMode === 'class' ? 'Class Group' : 'Grade') +
          '<span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);font-size:.72rem"> (' + (displayMode === 'class' ? 'Pre-K · Pre-Primary · Primary · Junior' : 'shirts per grade') + ')</span>' +
        '</div>' +
        classShirtRows +
        '<hr class="tshirt-divider">' +
        '<div class="tshirt-group-title" style="margin-bottom:12px">📦 Grand Total to Order' +
          '<span style="font-weight:400;text-transform:none;letter-spacing:0;color:var(--muted);font-size:.72rem"> (kids + volunteers combined)</span>' +
        '</div>' +
        '<div class="tshirt-grand">' + (grandChips || '<p style="color:var(--muted);font-size:.8rem">No shirt data</p>') + '</div>' +
        '<p style="font-size:.7rem;color:var(--muted);margin-top:12px;font-weight:700">💡 Each chip: total · K = kids · V = volunteers</p>' +
      '</div>' +
    '</div>';
}

function toggleTshirt(header) {
  const chevron = header.querySelector('.ts-chevron');
  const body    = header.nextElementSibling;
  const isOpen  = body.classList.contains('open');
  body.classList.toggle('open', !isOpen);
  chevron.classList.toggle('open', !isOpen);
}

/* ── STORE ORDER MODAL ──────────────────────────────────────────────
   Conversion metric (store SKU → our size names):
   1. 2-4 (Kids) / XS (Youth)  → same store SKU
   2. 6-8 (Kids) / S (Youth)   → same store SKU
   3. 10-12 (Kids) / M (Youth) → same store SKU
   4. 14-16 (Kids) / L (Youth) → same store SKU
   5. Small (Adult)
   6. Medium (Adult)
   7. Large (Adult)
   8. X-Large (Adult) / XL (Adult)
   9. XXL (Adult)
───────────────────────────────────────────────────────────────────── */
var STORE_BUCKETS = [
  {
    label: 'Youth XS  /  2-4 (Kids)',
    keys:  ['2-4 (Kids)', 'XS (Youth)'],
    note:  'Kids size 2-4 = Youth XS'
  },
  {
    label: 'Youth S  /  6-8 (Kids)',
    keys:  ['6-8 (Kids)', 'Small (Youth)'],
    note:  'Kids size 6-8 = Youth Small'
  },
  {
    label: 'Youth M  /  10-12 (Kids)',
    keys:  ['10-12 (Kids)', 'Medium (Youth)'],
    note:  'Kids size 10-12 = Youth Medium'
  },
  {
    label: 'Youth L  /  14-16 (Kids)',
    keys:  ['14-16 (Kids)', 'Large (Youth)', 'X-Large (Youth)'],
    note:  'Kids size 14-16 = Youth Large/XL'
  },
  { label: 'Small (Adult)',    keys: ['Small (Adult)', 'Small'],              note: '' },
  { label: 'Medium (Adult)',   keys: ['Medium (Adult)', 'Medium'],            note: '' },
  { label: 'Large (Adult)',    keys: ['Large (Adult)', 'Large'],              note: '' },
  { label: 'X-Large (Adult)', keys: ['X-Large (Adult)', 'X-Large'],         note: '' },
  { label: 'XXL (Adult)',     keys: ['XXL (Adult)', 'XXL'],                  note: '' },
  { label: '2XL (Adult)',     keys: ['2XL (Adult)', '2XL'],                  note: '' },
  { label: '3XL (Adult)',     keys: ['3XL (Adult)', '3XL'],                  note: '' },
];

function showStoreOrder() {
  // Inject modal once
  if (!document.getElementById('store-order-modal')) {
    document.body.insertAdjacentHTML('beforeend',
      '<div id="store-order-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;padding:16px" onclick="if(event.target===this)this.style.display=\'none\'">' +
        '<div style="background:white;border-radius:20px;width:100%;max-width:520px;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.25);overflow:hidden">' +
          '<div style="background:linear-gradient(135deg,#7C3AED,#4F46E5);color:white;padding:18px 22px;display:flex;align-items:center;justify-content:space-between;flex-shrink:0">' +
            '<div>' +
              '<div style="font-family:Fredoka One,cursive;font-size:1.2rem">🛍️ Store Order Sheet</div>' +
              '<div style="font-size:.72rem;opacity:.8;margin-top:2px">Converted to store sizes · kids + volunteers combined</div>' +
            '</div>' +
            '<button onclick="document.getElementById(\'store-order-modal\').style.display=\'none\'" style="width:30px;height:30px;border-radius:50%;border:none;background:rgba(255,255,255,.2);color:white;font-size:1rem;cursor:pointer">✕</button>' +
          '</div>' +
          '<div id="store-order-body" style="overflow-y:auto;padding:18px 22px 24px"></div>' +
        '</div>' +
      '</div>'
    );
  }

  // Build combined counts from kids + volunteers
  var kids = window._allKids || [];
  var vols = window._allVols || [];
  var kCounts = countShirts(kids, function(k) { return k.tshirt; });
  var vCounts = countShirts(vols, function(v) { return v.tshirt; });
  var combined = Object.assign({}, kCounts);
  Object.entries(vCounts).forEach(function(e) {
    combined[e[0]] = (combined[e[0]] || 0) + e[1];
  });

  // Apply conversion buckets
  var rows = '';
  var grandTotal = 0;
  var unmatched  = Object.assign({}, combined);

  STORE_BUCKETS.forEach(function(bucket) {
    var total = 0;
    var breakdown = [];
    bucket.keys.forEach(function(key) {
      if (combined[key]) {
        total += combined[key];
        var kN = kCounts[key] || 0;
        var vN = vCounts[key] || 0;
        breakdown.push(key + ': ' + combined[key] + (kN && vN ? ' (' + kN + 'K + ' + vN + 'V)' : kN ? ' (Kids)' : ' (Vol)'));
        delete unmatched[key];
      }
    });
    if (!total) return;
    grandTotal += total;

    var color = total >= 10 ? '#7C3AED' : total >= 5 ? '#1D4ED8' : '#374151';
    rows +=
      '<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #F1F5F9">' +
        '<div style="min-width:170px;font-weight:800;font-size:.88rem;color:#1A2744">' + bucket.label + '</div>' +
        '<div style="flex:1;background:#F1F5F9;border-radius:30px;height:24px;overflow:hidden">' +
          '<div style="height:100%;background:' + color + ';border-radius:30px;display:flex;align-items:center;padding:0 10px;font-size:.7rem;font-weight:800;color:white;min-width:40px;transition:width .6s">' +
            total + ' shirt' + (total > 1 ? 's' : '') +
          '</div>' +
        '</div>' +
        '<div style="font-family:Fredoka One,cursive;font-size:1.2rem;color:#1A2744;width:28px;text-align:right">' + total + '</div>' +
      '</div>' +
      (bucket.note || breakdown.length > 1 ?
        '<div style="font-size:.67rem;color:#9CA3AF;font-weight:600;margin:-6px 0 4px 0;padding-left:2px">' +
          (bucket.note ? '↳ ' + bucket.note + (breakdown.length ? '  ·  ' : '') : '') +
          breakdown.join('  ·  ') +
        '</div>' : '');
  });

  // Any unmatched sizes
  var unmatchedKeys = Object.keys(unmatched).filter(function(k) { return unmatched[k] > 0; });
  if (unmatchedKeys.length) {
    rows +=
      '<div style="margin-top:12px;background:#FFF7ED;border:1.5px solid #FDE68A;border-radius:10px;padding:10px 14px">' +
        '<div style="font-size:.7rem;font-weight:800;color:#92400E;margin-bottom:6px">⚠️ Unmatched sizes (check sheet values)</div>' +
        unmatchedKeys.map(function(k) {
          return '<div style="font-size:.75rem;color:#92400E">' + k + ': ' + unmatched[k] + '</div>';
        }).join('') +
      '</div>';
  }

  var body = document.getElementById('store-order-body');
  body.innerHTML =
    '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">' +
      '<div style="font-size:.72rem;color:#6B7280;font-weight:600">Kids + Volunteers · ' + (kids.length + vols.length) + ' people total</div>' +
      '<div style="background:#7C3AED;color:white;border-radius:20px;padding:4px 14px;font-size:.78rem;font-weight:800">🛒 ' + grandTotal + ' total</div>' +
    '</div>' +
    rows +
    '<div style="margin-top:16px;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:10px;padding:12px 14px">' +
      '<div style="font-size:.7rem;font-weight:800;color:#15803D;margin-bottom:4px">📋 Conversion reference</div>' +
      '<div style="font-size:.68rem;color:#166534;font-weight:600;line-height:1.8">' +
        '2-4 (Kids) = Youth XS &nbsp;·&nbsp; 6-8 (Kids) = Youth Small<br>' +
        '10-12 (Kids) = Youth Medium &nbsp;·&nbsp; 14-16 (Kids) = Youth Large' +
      '</div>' +
    '</div>';

  var modal = document.getElementById('store-order-modal');
  modal.style.display = 'flex';
}

/* ── Display Mode (localStorage, per device) ── */
window._displayMode = localStorage.getItem('vbs_display_mode') || 'grade';

function toggleDisplayMode() {
  var newMode = window._displayMode === 'grade' ? 'class' : 'grade';
  window._displayMode = newMode;
  localStorage.setItem('vbs_display_mode', newMode);
  // Re-render everything that depends on display mode
  if (window._allKids_raw) renderKids(window._allKids_raw);
  refreshTshirtSummary();
  if (typeof renderClassGrid === 'function') renderClassGrid();
  if (typeof renderCiRows === 'function') renderCiRows();
  // Update all toggle buttons on the page
  document.querySelectorAll('.display-mode-toggle').forEach(function(btn) {
    updateToggleBtn(btn, newMode);
  });
}

function updateToggleBtn(btn, mode) {
  if (!btn) return;
  mode = mode || window._displayMode;
  var isClass = mode === 'class';
  btn.innerHTML =
    '<span class="dm-opt' + (!isClass ? ' dm-active' : '') + '">📋 Grade</span>' +
    '<span class="dm-sep">|</span>' +
    '<span class="dm-opt' + (isClass  ? ' dm-active' : '') + '">🏫 Class</span>';
}

/* ── Init ── */
function refreshAll() {
  loadKids();
  loadVols();
  document.getElementById('ts').textContent =
    'Updated ' + new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}
/* ── Info Button Modal ── */
const PAGE_INFO = {
  'command-center': {
    icon: '⛪',
    title: 'Command Center',
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
    icon: '✅',
    title: 'Check In',
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
    icon: '🚪',
    title: 'Check Out',
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
    icon: '🙋',
    title: 'Volunteer Check-In',
    desc: 'Allows volunteers to sign in at the start of each VBS day.',
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
    icon: '🎽',
    title: 'Merch Station',
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
  const info = PAGE_INFO[pageKey];
  if (!info) return;
  const existing = document.getElementById('info-overlay');
  if (existing) existing.remove();
  const stepsHtml = info.steps.map((s, i) =>
    `<div class="info-step"><div class="info-step-num">${i+1}</div><div class="info-step-text">${s}</div></div>`
  ).join('');
  const tipHtml = info.tip ? `<div class="info-modal-tip">${info.tip}</div>` : '';
  const el = document.createElement('div');
  el.id = 'info-overlay';
  el.className = 'info-overlay';
  el.innerHTML = `
    <div class="info-modal" role="dialog" aria-modal="true">
      <div class="info-modal-header">
        <div style="display:flex;align-items:center;gap:10px">
          <div class="info-modal-icon">${info.icon}</div>
          <div class="info-modal-title">${info.title}</div>
        </div>
        <button class="info-modal-close" onclick="document.getElementById('info-overlay').remove()" aria-label="Close">✕</button>
      </div>
      <div class="info-modal-body">
        <div class="info-modal-desc">${info.desc}</div>
        <div class="info-modal-steps">${stepsHtml}</div>
        ${tipHtml}
      </div>
    </div>`;
  el.addEventListener('click', function(e) { if (e.target === el) el.remove(); });
  document.addEventListener('keydown', function esc(e) {
    if (e.key === 'Escape') { el.remove(); document.removeEventListener('keydown', esc); }
  });
  document.body.appendChild(el);
}

/* ── Data Version Polling (reset detection) ── */
(function() {
  const API = 'https://pypaonline.org/vbs/api.php';
  const KEY  = 'vbs_data_version';
  let _timer = null;

  function startDataVersionPolling(onReset) {
    if (_timer) clearInterval(_timer);
    // Store current version on first load
    fetch(API + '?action=getDataVersion')
      .then(r => r.json())
      .then(d => {
        if (d.status === 'ok') {
          const stored = sessionStorage.getItem(KEY);
          if (!stored) {
            sessionStorage.setItem(KEY, d.version);
          } else if (stored !== d.version) {
            sessionStorage.setItem(KEY, d.version);
            if (typeof onReset === 'function') onReset();
          }
        }
      }).catch(() => {});

    _timer = setInterval(function() {
      fetch(API + '?action=getDataVersion')
        .then(r => r.json())
        .then(d => {
          if (d.status !== 'ok') return;
          const stored = sessionStorage.getItem(KEY);
          if (stored && stored !== d.version) {
            sessionStorage.setItem(KEY, d.version);
            if (typeof onReset === 'function') onReset();
          }
        }).catch(() => {});
    }, 15000);
  }

  window.startDataVersionPolling = startDataVersionPolling;
})();