/* ══════════════════════════════════════
   kids.js — Kids Registration tab logic.
   Edit this file to change anything in the
   🧒 Kids Registration tab.
══════════════════════════════════════ */

function extractChildren(rows) {
  const kids = [];
  rows.forEach(r => {
    const p = r["Parent's Full Name"] || '', ch = r["Church Name"] || '';

    // Parse registration timestamp — same logic as volunteers.js
    var tsRaw = '';
    var tsKey = Object.keys(r).find(function(h) {
      var k = h.trim().toLowerCase();
      return k === 'submission time' || k === 'timestamp' || k.includes('submission time') || k.includes('timestamp');
    }) || Object.keys(r)[0];
    if (tsKey) tsRaw = r[tsKey] || '';
    var ts = 0;
    if (tsRaw) {
      var normalized = tsRaw.trim().replace(/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2})/, '$1T$2');
      var parsed = new Date(normalized);
      if (isNaN(parsed.getTime())) parsed = new Date(tsRaw.trim());
      if (isNaN(parsed.getTime())) {
        parsed = new Date(tsRaw.replace(/(\d+)\/(\d+)\/(\d+)/, '$3-$1-$2').replace(' ', 'T'));
      }
      ts = isNaN(parsed.getTime()) ? 0 : parsed.getTime();
    }

    const normalizeGrade = (gr) => {
      if (!gr) return '';
      const g = gr.trim().replace(/\s+/g, ' ');
      if (/pre/i.test(g) && !/primary/i.test(g)) return 'Pre K';
      return g;
    };
    const add = (n, g, gr, tshirt, al) => {
      if (n && n.trim()) kids.push({ name:n, gender:g||'', grade:normalizeGrade(gr), tshirt:tshirt||'', allergies:al||'None', parent:p, church:ch, ts });
    };
    add(r["Child #1 Name"], r["Gender"],    r["Grade in Spetmber"],    r["T Shirt Size"],    r["Allergies"]);
    add(r["Child #2 Name"], r["Gender (1)"], r["Grade in Spetmber (1)"], r["T Shirt Size (1)"], r["Allergies (1)"]);
    add(r["Child #3 Name"], r["Gender (2)"], r["Grade in Spetmber (2)"], r["T Shirt Size (2)"], r["Allergies (2)"]);
  });
  // Sort newest first
  kids.sort((a, b) => b.ts - a.ts);
  return kids.filter(k => k.name.trim());
}

function buildDonut(boys, girls, total) {
  if (!total) return '';
  const boysP  = Math.round(boys  / total * 100);
  const girlsP = Math.round(girls / total * 100);
  const uid = 'gd' + Date.now();
  // Inject keyframe styles once
  if (!document.getElementById('gender-flip-styles')) {
    const s = document.createElement('style');
    s.id = 'gender-flip-styles';
    s.textContent = `
      .gf-wrap { display:flex; gap:16px; justify-content:center; align-items:stretch; padding:8px 0; }
      .gf-card {
        flex:1; border-radius:16px; padding:18px 12px 14px;
        text-align:center; position:relative; overflow:hidden;
        box-shadow:0 4px 18px rgba(0,0,0,.08);
        transition:transform .2s;
      }
      .gf-card:hover { transform:translateY(-3px); }
      .gf-card-boys  { background:linear-gradient(135deg,#E0F7FF,#B3E8FF); border:2px solid #7DD3F7; }
      .gf-card-girls { background:linear-gradient(135deg,#FFE8F4,#FFD0EB); border:2px solid #F48FB1; }
      .gf-emoji { font-size:1.8rem; margin-bottom:6px; display:block; }
      .gf-num {
        font-family:Fredoka One,cursive; font-size:2.6rem; line-height:1;
        display:block; margin-bottom:4px;
      }
      .gf-card-boys  .gf-num  { color:#0369a1; }
      .gf-card-girls .gf-num  { color:#be185d; }
      .gf-label { font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.5px; color:#78716C; }
      .gf-pct {
        display:inline-block; margin-top:8px;
        font-size:.72rem; font-weight:800; border-radius:99px; padding:2px 10px;
      }
      .gf-card-boys  .gf-pct { background:rgba(79,195,247,.2); color:#0369a1; }
      .gf-card-girls .gf-pct { background:rgba(244,143,177,.2); color:#be185d; }
      .gf-total {
        text-align:center; margin-top:14px;
        font-family:Fredoka One,cursive; font-size:.95rem; color:var(--muted);
      }
      @keyframes gf-flip {
        0%   { opacity:0; transform:translateY(18px) scale(.85); }
        60%  { transform:translateY(-4px) scale(1.08); }
        80%  { transform:translateY(2px) scale(.97); }
        100% { opacity:1; transform:translateY(0) scale(1); }
      }
      @keyframes gf-pulse {
        0%,100% { box-shadow:0 4px 18px rgba(0,0,0,.08); }
        50%      { box-shadow:0 6px 28px rgba(0,0,0,.18); }
      }
      .gf-animate { animation:gf-flip .55s cubic-bezier(.34,1.56,.64,1) both, gf-pulse .6s ease .55s 2; }
      .gf-animate-delay { animation:gf-flip .55s cubic-bezier(.34,1.56,.64,1) .12s both, gf-pulse .6s ease .67s 2; }
      .gf-num-counting { animation:gf-flip .4s cubic-bezier(.34,1.56,.64,1) both; }
    `;
    document.head.appendChild(s);
  }
  // Count-up animation function (stored globally, keyed by uid)
  setTimeout(function() {
    var boysEl  = document.getElementById(uid + '-b');
    var girlsEl = document.getElementById(uid + '-g');
    if (!boysEl || !girlsEl) return;
    function countUp(el, target, duration) {
      var start = 0, step = duration / target;
      var t = setInterval(function() {
        start++;
        el.textContent = start;
        if (start >= target) { clearInterval(t); }
      }, step);
    }
    countUp(boysEl,  boys,  600);
    countUp(girlsEl, girls, 600);
  }, 100);

  return '<div class="gf-wrap">' +
    '<div class="gf-card gf-card-boys gf-animate">' +
      '<span class="gf-emoji">👦</span>' +
      '<span class="gf-num" id="' + uid + '-b">0</span>' +
      '<div class="gf-label">Boys</div>' +
      '<span class="gf-pct">' + boysP + '%</span>' +
    '</div>' +
    '<div class="gf-card gf-card-girls gf-animate-delay">' +
      '<span class="gf-emoji">👧</span>' +
      '<span class="gf-num" id="' + uid + '-g">0</span>' +
      '<div class="gf-label">Girls</div>' +
      '<span class="gf-pct">' + girlsP + '%</span>' +
    '</div>' +
  '</div>' +
  '<div class="gf-total">Total · ' + total + ' kids</div>';
}

function initAllergyCardStyles() {
  if (document.getElementById('allergy-card-styles')) return;
  const s = document.createElement('style');
  s.id = 'allergy-card-styles';
  s.textContent =
    '@keyframes ac-pop { 0%{opacity:0;transform:scale(.6) translateY(10px)} 60%{transform:scale(1.12) translateY(-2px)} 100%{opacity:1;transform:scale(1) translateY(0)} }' +
    '@keyframes ac-chip { 0%{opacity:0;transform:translateY(8px)} 100%{opacity:1;transform:none} }' +
    '@keyframes ac-pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.06)} }' +
    '.ac-num  { animation: ac-pop 0.6s cubic-bezier(.34,1.56,.64,1) both; }' +
    '.ac-chip { animation: ac-chip .4s ease both; }' +
    '.ac-hint { animation: ac-pulse 2s ease-in-out 1s infinite; }';
  document.head.appendChild(s);
}

function buildAllergyCard(kids) {
  const allergic = kids.filter(function(k) { return k.allergies && k.allergies.toLowerCase() !== 'none' && k.allergies.trim() !== ''; });
  const total = allergic.length;

  if (!total) {
    return '<div style="text-align:center;padding:20px 0">' +
      '<div style="font-size:2.5rem;margin-bottom:8px">✅</div>' +
      '<div style="font-family:Fredoka One,cursive;font-size:1.4rem;color:var(--navy)">No Allergies</div>' +
      '<div style="font-size:.78rem;color:var(--muted);margin-top:4px">All ' + kids.length + ' kids are allergy-free</div>' +
    '</div>';
  }

  const types = {};
  allergic.forEach(function(k) { const a = k.allergies.trim(); types[a] = (types[a] || 0) + 1; });

  const chips = Object.entries(types).sort(function(a,b){return b[1]-a[1];}).map(function(e, i) {
    return '<span class="ac-chip" style="display:inline-flex;align-items:center;gap:5px;background:#FEF3C7;color:#92400E;border:1.5px solid #FDE68A;border-radius:99px;padding:4px 12px;font-size:.72rem;font-weight:800;margin:3px;animation-delay:' + (300 + i*80) + 'ms">' +
      e[0] + '<span style="background:#F59E0B;color:white;border-radius:99px;padding:1px 7px;font-size:.68rem">' + e[1] + '</span>' +
    '</span>';
  }).join('');

  return '<div style="text-align:center;padding:8px 0 4px" id="ac-wrap">' +
    '<div class="ac-num" style="font-family:Fredoka One,cursive;font-size:3rem;color:#EF4444;line-height:1;margin-bottom:2px"><span id="ac-num-val">0</span></div>' +
    '<div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin-bottom:14px">Kids with Allergies</div>' +
    '<div style="display:flex;flex-wrap:wrap;justify-content:center;gap:2px;margin-bottom:12px">' + chips + '</div>' +
    '<div class="ac-hint" style="font-size:.72rem;color:var(--muted);font-weight:700;transition:color .2s">Tap to see full list →</div>' +
  '</div>';
}

function runAllergyCardAnim(total) {
  initAllergyCardStyles();
  const el = document.getElementById('ac-num-val');
  if (!el) return;
  let n = 0;
  const step = Math.max(1, Math.floor(total / 20));
  const t = setInterval(function() {
    n = Math.min(n + step, total);
    el.textContent = n;
    if (n >= total) clearInterval(t);
  }, 40);
}

function openAllergyModal() {
  const kids = window._allKids || [];
  const allergic = kids.filter(function(k) { return k.allergies && k.allergies.toLowerCase() !== 'none' && k.allergies.trim() !== ''; });
  if (!allergic.length) return;

  // Inject modal once
  if (!document.getElementById('allergy-modal-overlay')) {
    document.body.insertAdjacentHTML('beforeend',
      '<div id="allergy-modal-overlay" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:600;display:flex;align-items:flex-end;justify-content:center;opacity:0;pointer-events:none;transition:opacity .2s" onclick="if(event.target===this)closeAllergyModal()">' +
        '<div id="allergy-modal" style="background:white;border-radius:24px 24px 0 0;width:100%;max-width:580px;max-height:85vh;display:flex;flex-direction:column;transform:translateY(100%);transition:transform .3s cubic-bezier(.34,1.2,.64,1);overflow:hidden">' +
          '<div style="display:flex;align-items:center;justify-content:space-between;padding:18px 22px 14px;border-bottom:1px solid var(--border);flex-shrink:0">' +
            '<div style="font-family:Fredoka One,cursive;font-size:1.3rem;color:var(--navy)">⚠️ Allergy List</div>' +
            '<button onclick="closeAllergyModal()" style="width:30px;height:30px;border-radius:50%;border:none;background:var(--page-bg);cursor:pointer;font-size:.9rem">✕</button>' +
          '</div>' +
          '<div id="allergy-modal-body" style="overflow-y:auto;padding:14px 22px 28px"></div>' +
        '</div>' +
      '</div>'
    );
  }

  const mode = window._displayMode || 'grade';

  // ── GRADE mode: group by individual grade ──
  const GRADE_ORDER = [
    { key:'Pre K', label:'Pre-K',        emoji:'🎈', color:'#FF7043' },
    { key:'K',     label:'Kindergarten', emoji:'🌟', color:'#FF9800' },
    { key:'1st',   label:'1st Grade',    emoji:'📚', color:'#FDD835' },
    { key:'2nd',   label:'2nd Grade',    emoji:'✏️', color:'#66BB6A' },
    { key:'3rd',   label:'3rd Grade',    emoji:'🚀', color:'#26C6DA' },
    { key:'4th',   label:'4th Grade',    emoji:'⚽', color:'#42A5F5' },
    { key:'5th',   label:'5th Grade',    emoji:'🔥', color:'#7E57C2' },
    { key:'6th',   label:'6th Grade',    emoji:'🎨', color:'#EC407A' },
    { key:'7th',   label:'7th Grade',    emoji:'👑', color:'#8D6E63' },
    { key:'8th',   label:'8th Grade',    emoji:'🏆', color:'#546E7A' },
  ];

  var groups;
  if (mode === 'class') {
    groups = CLASS_GROUP_MAP.map(function(grp) {
      return {
        label: grp.label, emoji: grp.emoji, color: grp.color, bg: grp.bg,
        kids: allergic.filter(function(k) {
          return grp.grades.some(function(g) {
            return k.grade && k.grade.trim().toLowerCase() === g.toLowerCase();
          });
        })
      };
    }).filter(function(g) { return g.kids.length > 0; });
  } else {
    groups = GRADE_ORDER.map(function(grp) {
      return {
        label: grp.label, emoji: grp.emoji, color: grp.color, bg: 'rgba(0,0,0,.04)',
        kids: allergic.filter(function(k) {
          return k.grade && k.grade.trim().toLowerCase() === grp.key.toLowerCase();
        })
      };
    }).filter(function(g) { return g.kids.length > 0; });
    // Any unmatched grades → "Other"
    var matchedNames = groups.reduce(function(acc, g) { return acc.concat(g.kids.map(function(k){return k.name;})); }, []);
    var others = allergic.filter(function(k) { return matchedNames.indexOf(k.name) === -1; });
    if (others.length) groups.push({ label:'Other', emoji:'🎒', color:'#9C6FDE', bg:'rgba(156,111,222,.06)', kids: others });
  }

  // Build HTML
  var html = '<div style="font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);margin-bottom:14px">' +
    allergic.length + ' kid' + (allergic.length !== 1 ? 's' : '') + ' with allergies · grouped by ' + (mode === 'class' ? 'class group' : 'grade') +
  '</div>';

  groups.forEach(function(grp) {
    html += '<div style="margin-bottom:18px">' +
      '<div style="display:flex;align-items:center;gap:8px;padding:7px 12px;border-radius:10px;background:' + grp.bg + ';margin-bottom:8px">' +
        '<span style="font-size:1.1rem">' + grp.emoji + '</span>' +
        '<span style="font-family:Fredoka One,cursive;font-size:.95rem;color:' + grp.color + '">' + grp.label + '</span>' +
        '<span style="margin-left:auto;background:' + grp.color + ';color:white;border-radius:99px;font-size:.68rem;font-weight:800;padding:2px 8px">' + grp.kids.length + '</span>' +
      '</div>';
    grp.kids.forEach(function(k) {
      html += '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:9px 12px;border-bottom:1px solid var(--border)">' +
        '<div>' +
          '<div style="font-weight:800;font-size:.88rem;color:var(--navy)">' + k.name + '</div>' +
          '<div style="font-size:.72rem;color:var(--muted);font-weight:600;margin-top:1px">👨‍👩‍👧 ' + k.parent + '</div>' +
        '</div>' +
        '<span style="background:#FEF3C7;color:#92400E;border:1px solid #FDE68A;border-radius:8px;padding:4px 10px;font-size:.73rem;font-weight:800;white-space:nowrap;flex-shrink:0">⚠️ ' + k.allergies + '</span>' +
      '</div>';
    });
    html += '</div>';
  });

  document.getElementById('allergy-modal-body').innerHTML = html;
  const ov = document.getElementById('allergy-modal-overlay');
  const md = document.getElementById('allergy-modal');
  ov.style.opacity = '1'; ov.style.pointerEvents = 'all';
  md.style.transform = 'translateY(0)';
  document.body.style.overflow = 'hidden';
}

function closeAllergyModal() {
  const ov = document.getElementById('allergy-modal-overlay');
  const md = document.getElementById('allergy-modal');
  if (ov) { ov.style.opacity = '0'; ov.style.pointerEvents = 'none'; }
  if (md) md.style.transform = 'translateY(100%)';
  document.body.style.overflow = '';
}

const CLASS_GROUP_MAP = [
  { label:'Pre-K',       emoji:'🎈', grades:['Pre K'],           color:'#FF7043', bg:'rgba(255,112,67,.10)' },
  { label:'Pre-Primary', emoji:'🎠', grades:['K','Kindergarten','Kinder','1st','2nd'],   color:'#42A5F5', bg:'rgba(66,165,245,.10)' },
  { label:'Primary',     emoji:'📘', grades:['3rd','4th','5th'], color:'#66BB6A', bg:'rgba(102,187,106,.10)' },
  { label:'Junior',      emoji:'🎓', grades:['6th','7th','8th'], color:'#9C6FDE', bg:'rgba(156,111,222,.10)' },
];

function getClassGroup(grade) {
  if (!grade) return null;
  const g = grade.trim().toLowerCase();
  return CLASS_GROUP_MAP.find(grp => grp.grades.some(x => x.toLowerCase() === g)) || null;
}

function buildKidsRow(c, query = '') {
  const hl = (str) => {
    if (!query || !str) return str || '';
    const re = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
    return str.replace(re, '<mark>$1</mark>');
  };
  const isNew = c.ts && (Date.now() - c.ts) < 86400000;
  const newBadge = isNew
    ? '<span style="display:inline-block;margin-left:6px;background:rgba(74,222,128,.15);color:#15803d;border:1px solid rgba(74,222,128,.4);border-radius:99px;padding:1px 7px;font-size:.62rem;font-weight:800;vertical-align:middle">🆕 New</span>'
    : '';
  const grp = getClassGroup(c.grade);
  const groupBadge = grp
    ? '<span style="display:inline-flex;align-items:center;gap:3px;background:' + grp.bg + ';color:' + grp.color + ';border:1.5px solid ' + grp.color + '55;border-radius:99px;padding:2px 9px;font-size:.68rem;font-weight:800;white-space:nowrap">' + grp.emoji + ' ' + grp.label + '</span>'
    : '<span style="color:var(--muted);font-size:.75rem">—</span>';
  return `<tr${isNew ? ' style="background:rgba(74,222,128,.04)"' : ''}>
    <td><strong>${hl(c.name)}</strong>${newBadge}</td>
    <td><span class="chip" style="background:${gradeColor(c.grade)}">${c.grade||'—'}</span></td>
    <td>${groupBadge}</td>
    <td><span class="${c.gender.toLowerCase()==='male'?'chip-m':'chip-f'}">${c.gender}</span></td>
    <td>${c.tshirt||'—'}</td>
    <td>${c.allergies||'None'}</td>
    <td style="color:var(--muted);font-size:.78rem">${hl(c.parent)}</td>
  </tr>`;
}

function renderKids(rows) {
  const kids  = extractChildren(rows);
  const total = kids.length;
  const boys  = kids.filter(c => c.gender.toLowerCase() === 'male').length;
  const girls = kids.filter(c => c.gender.toLowerCase() === 'female').length;

  const displayMode = window._displayMode || 'grade';

  // ── Grade bar data ──
  const CLASS_GROUPS = [
    { key:'Pre-K',       label:'Pre-K',       emoji:'🎈', grades:['Pre K'] },
    { key:'Pre-Primary', label:'Pre-Primary', emoji:'🎠', grades:['K','1st','2nd'] },
    { key:'Primary',     label:'Primary',     emoji:'📘', grades:['3rd','4th','5th'] },
    { key:'Junior',      label:'Junior',      emoji:'🎓', grades:['6th','7th','8th'] }
  ];

  let gc = {}, maxG, gradeBars, barTitle;

  const GRADE_DISPLAY = {
    'pre k':'Pre K', 'pre-k':'Pre K', 'prek':'Pre K', 'pre':'Pre K',
    'k':'K', 'kindergarten':'K',
    '1st':'1st', '2nd':'2nd', '3rd':'3rd', '4th':'4th',
    '5th':'5th', '6th':'6th', '7th':'7th', '8th':'8th'
  };
  const GROUP_ORDER = ['🎈 Pre-K', '🎠 Pre-Primary', '📘 Primary', '🎓 Junior'];
  const GRADE_ORDER = ['Pre K','K','1st','2nd','3rd','4th','5th','6th','7th','8th'];

  if (displayMode === 'class') {
    CLASS_GROUPS.forEach(grp => {
      const n = kids.filter(c => grp.grades.some(g => c.grade && c.grade.trim().toLowerCase() === g.toLowerCase())).length;
      if (n > 0) gc[grp.emoji + ' ' + grp.label] = n;
    });
    barTitle = '🏫 Kids by Class Group';
  } else {
    kids.forEach(c => {
      const raw = c.grade.trim();
      const normalized = GRADE_DISPLAY[raw.toLowerCase()] || raw || 'Unknown';
      gc[normalized] = (gc[normalized] || 0) + 1;
    });
    barTitle = '📊 Kids by Grade';
  }

  maxG = Math.max(...Object.values(gc), 1);

  if (!document.getElementById('bar-anim-styles')) {
    const s = document.createElement('style');
    s.id = 'bar-anim-styles';
    s.textContent = `
      @keyframes bar-slide-in {
        from { width: 0 !important; opacity: 0; }
        to   { opacity: 1; }
      }
      .bar-fill-anim {
        animation: bar-slide-in .7s cubic-bezier(.34,1.2,.64,1) both;
      }
    `;
    document.head.appendChild(s);
  }

  gradeBars = Object.entries(gc).sort((a, b) => {
    if (displayMode === 'class') {
      return GROUP_ORDER.indexOf(a[0]) - GROUP_ORDER.indexOf(b[0]);
    }
    const ai = GRADE_ORDER.indexOf(a[0]), bi = GRADE_ORDER.indexOf(b[0]);
    if (ai === -1 && bi === -1) return a[0].localeCompare(b[0]);
    if (ai === -1) return 1;
    if (bi === -1) return -1;
    return ai - bi;
  }).map(([g, n], i) => `
    <div class="bar-row">
      <div class="bar-lbl">${g}</div>
      <div class="bar-track">
        <div class="bar-fill bar-fill-anim" style="width:${n/maxG*100}%;background:${gradeColor(g)};animation-delay:${i * 80}ms">
          ${n} kid${n>1?'s':''}
        </div>
      </div>
      <div class="bar-n">${n}</div>
    </div>`).join('');

  document.getElementById('k-stats').innerHTML = `
    <div class="stat-card"><div class="stat-e">🧒</div><div class="stat-n">${total}</div><div class="stat-l">Total Children</div></div>
    <div class="stat-card sc-coral"><div class="stat-e">👨‍👩‍👧</div><div class="stat-n">${rows.length}</div><div class="stat-l">Families</div></div>
    <div class="stat-card sc-grass"><div class="stat-e">👦</div><div class="stat-n">${boys}</div><div class="stat-l">Boys</div></div>
    <div class="stat-card sc-purple"><div class="stat-e">👧</div><div class="stat-n">${girls}</div><div class="stat-l">Girls</div></div>`;

  const tRows = kids.map(c => buildKidsRow(c, '')).join('');

  document.getElementById('k-content').innerHTML = `
    <div class="grid2">
      <div class="card">
        <div class="card-title">${barTitle}</div>
        ${gradeBars || '<p style="color:var(--muted)">No grade data</p>'}
      </div>
      <div class="card" id="allergy-card" style="cursor:pointer" onclick="openAllergyModal()">
        <div class="card-title">⚠️ Allergy Summary</div>
        ${buildAllergyCard(kids)}
      </div>
    </div>
    
    <div class="vol-accordion">
      <div class="vol-acc-header" onclick="toggleKidsTable()">
        <div class="vol-acc-left">
          <span style="font-size:1.2rem">🧒</span>
          <span class="vol-acc-title">All Registered Children</span>
          <span class="tshirt-total-badge">${total} total</span>
        </div>
        <span class="vol-acc-chev" id="k-acc-chev">▼</span>
      </div>
      <div class="vol-acc-body" id="k-acc-body">
        <div class="search-bar">
          <div class="search-wrap">
            <span class="search-icon">🔍</span>
            <input class="search-input" id="k-search" type="text" placeholder="Search by child or parent name…" oninput="filterKidsTable(this.value)">
            <button class="btn-clear" id="k-clear" onclick="clearKidsSearch()" title="Clear search">✕</button>
          </div>
          <div class="search-count" id="k-count"><strong>${total}</strong> of ${total} children</div>
        </div>
        <div class="tbl-wrap">
          <table class="tbl" id="k-table">
            <thead><tr><th>Name</th><th>Grade</th><th>Class Group</th><th>Gender</th><th>T-Shirt</th><th>Allergies</th><th>Parent</th></tr></thead>
            <tbody id="k-tbody">${tRows}</tbody>
          </table>
          <div class="no-results" id="k-no-results" style="display:none">
            <div class="nr-icon">🔎</div>
            <div>No children found matching your search</div>
          </div>
        </div>
      </div>
    </div>`;

  window._allKids = kids;
  window._allKids_raw = rows; // store raw rows so pollDisplayMode can re-render
  // Run allergy card animation after DOM is updated
  const _allergyTotal = kids.filter(function(k){ return k.allergies && k.allergies.toLowerCase() !== 'none' && k.allergies.trim() !== ''; }).length;
  if (_allergyTotal) setTimeout(function(){ runAllergyCardAnim(_allergyTotal); }, 50);
  refreshTshirtSummary();
}

function filterKidsTable(query) {
  const kids = window._allKids || [];
  const q = query.trim().toLowerCase();
  const clearBtn = document.getElementById('k-clear');
  const countEl  = document.getElementById('k-count');
  const tbody    = document.getElementById('k-tbody');
  const noRes    = document.getElementById('k-no-results');
  const table    = document.getElementById('k-table');

  if (clearBtn) clearBtn.classList.toggle('vis', q.length > 0);
  const filtered = q ? kids.filter(c => c.name.toLowerCase().includes(q) || c.parent.toLowerCase().includes(q)) : kids;
  if (tbody)  tbody.innerHTML = filtered.map(c => buildKidsRow(c, q)).join('');
  if (countEl) countEl.innerHTML = `<strong>${filtered.length}</strong> of ${kids.length} children`;
  if (noRes && table) {
    noRes.style.display = filtered.length === 0 ? 'block' : 'none';
    table.style.display = filtered.length === 0 ? 'none'  : '';
  }
}

function clearKidsSearch() {
  const inp = document.getElementById('k-search');
  if (inp) { inp.value = ''; inp.focus(); }
  filterKidsTable('');
}

function toggleKidsTable() {
  const body = document.getElementById('k-acc-body');
  const chev = document.getElementById('k-acc-chev');
  const isOpen = body.classList.contains('open');
  body.classList.toggle('open', !isOpen);
  chev.classList.toggle('open', !isOpen);
}

async function loadKids() {
  document.getElementById('k-stats').innerHTML = `<div class="loading-box" style="grid-column:1/-1"><span class="spin">⏳</span> Loading…</div>`;
  document.getElementById('k-content').innerHTML = '';
  try {
    const res = await fetch(CSV_KIDS + '&t=' + Date.now());
    if (!res.ok) throw new Error('HTTP ' + res.status);
    renderKids(parseCSV(await res.text()));
  } catch(e) {
    document.getElementById('k-stats').innerHTML = `<div class="err-box" style="grid-column:1/-1">⚠️ Could not load kids data.<br><small>${e.message}</small></div>`;
  }
}