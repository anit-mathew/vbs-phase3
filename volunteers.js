/* ══════════════════════════════════════
   volunteers.js — Volunteers tab logic.
══════════════════════════════════════ */

const DAY_INFO = [
  { key:'July 9',  label:'Day 1', date:'July 9 · Thu'  },
  { key:'July 10', label:'Day 2', date:'July 10 · Fri' },
  { key:'July 11', label:'Day 3', date:'July 11 · Sat' }
];

/* ══════════════════════════════════════
   TEAM DEFINITIONS — edit here
══════════════════════════════════════ */
const SERVANT_LEADERS = ['Feba Thomas', 'Anit Mathew'];
const SERVANT_TITLE   = "Got questions, chaos, or a 'brilliant' 11pm idea? We don't have all the answers — but we'll pretend we do. Come find us.";

const TEAMS = [
  { id:'activities',   icon:'🏃',  name:'Activities & Games',      sub:'3 Age Groups', leads:['Roshan','Stephanie'],  roleKey:['Recreation','Activities','Games'],           color:'#26C6DA' },
  { id:'decoration',   icon:'🎨',  name:'Decoration',              sub:'',             leads:['Jasmin','Janita'],     roleKey:['Decoration'],                               color:'#FF7043' },
  { id:'crafts',       icon:'✂️',  name:'Crafts',                  sub:'',             leads:['Jessica'],             roleKey:['Arts and Crafts','Crafts'],                 color:'#9C6FDE' },
  { id:'worship',      icon:'🎵',  name:'VBS Songs & Worship',     sub:'',             leads:['Hope','Jayden'],       roleKey:['Worship Team','Praise','Worship'],           color:'#F48FB1' },
  { id:'registration', icon:'📋',  name:'Registration',            sub:'',             leads:['Sarah','Maria'],       roleKey:['Registration'],                             color:'#66BB6A' },
  { id:'media',        icon:'🎥',  name:'Media',                   sub:'',             leads:['Jason'],               roleKey:['Media'],                                    color:'#42A5F5' },
  { id:'curriculum',   icon:'📖',  name:'Lesson Plan & Curriculum',sub:'',             leads:['Sheba Samuel','Feba'], roleKey:['Bible Lessons','Curriculum','Lesson'],       color:'#FFB300' },
  { id:'food',         icon:'🍱',  name:'Food',                    sub:'',             leads:['Pr Kevin','Br Anit'],  roleKey:['Food'],                                     color:'#FF9800' },
  { id:'medical',      icon:'🏥',  name:'Medical Team',            sub:'',             leads:['Janita'],              roleKey:['Medical Team','Medical'],                   color:'#F06292' },
  { id:'crew',         icon:'🦺',  name:'Crew Leader',             sub:'',             leads:[],                     roleKey:['Crew Leader'],                              color:'#EC407A' },
  { id:'facility',     icon:'🔧',  name:'Facility Management',     sub:'',             leads:[],                     roleKey:['Facility Management','Facility Mangement'],  color:'#8D6E63' }
];

/* ── Match volunteer to team ── */
function volMatchesTeam(v, team) {
  return v.roles.some(function(r) {
    return team.roleKey.some(function(k) {
      return r.toLowerCase().includes(k.toLowerCase());
    });
  });
}

/* ── Map CSV row → volunteer object ── */
function mapVol(row) {
  function get() {
    for (var i = 0; i < arguments.length; i++) {
      var k = arguments[i];
      var found = Object.keys(row).find(function(h) {
        return h.toLowerCase().includes(k.toLowerCase());
      });
      if (found && row[found]) return row[found];
    }
    return '';
  }
  var name = get("what's your name", "name");
  if (!name) return null;

  /* grab timestamp */
  var tsRaw = '';
  var tsKey = Object.keys(row).find(function(h) {
    var k = h.trim().toLowerCase();
    return k === 'submission time' || k === 'timestamp' || k.includes('submission time') || k.includes('timestamp');
  }) || Object.keys(row).find(function(h) {
    var k = h.trim().toLowerCase();
    return k.includes('time') && !k.includes('id');
  });
  
  if (tsKey) {
    tsRaw = row[tsKey] || '';
  } else {
    tsRaw = row[Object.keys(row)[0]] || '';
  }

  var ts = 0;
  if (tsRaw) {
    var raw = tsRaw.trim();
    // 1. Try YYYY-MM-DD HH:MM:SS or YYYY-MM-DD HH:MM — parse as LOCAL time
    var isoMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2}))?/);
    if (isoMatch) {
      var parsed = new Date(
        parseInt(isoMatch[1]), parseInt(isoMatch[2])-1, parseInt(isoMatch[3]),
        parseInt(isoMatch[4]), parseInt(isoMatch[5]), parseInt(isoMatch[6]||0)
      );
      ts = isNaN(parsed.getTime()) ? 0 : parsed.getTime();
    }
    // 2. Try M/D/YYYY H:MM:SS — parse as LOCAL time
    if (!ts) {
      var mdyMatch = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})[T ](\d{1,2}):(\d{2})(?::(\d{2}))?/);
      if (mdyMatch) {
        var parsed2 = new Date(
          parseInt(mdyMatch[3]), parseInt(mdyMatch[1])-1, parseInt(mdyMatch[2]),
          parseInt(mdyMatch[4]), parseInt(mdyMatch[5]), parseInt(mdyMatch[6]||0)
        );
        ts = isNaN(parsed2.getTime()) ? 0 : parsed2.getTime();
      }
    }
    // 3. Last resort
    if (!ts) {
      var p3 = new Date(raw);
      ts = isNaN(p3.getTime()) ? 0 : p3.getTime();
    }
  }

  return {
    name:   name,
    email:  get('email'),
    city:   get('city'),
    state:  get('state'),
    age:    get('age'),
    church: get('church'),
    tshirt: get('t - shirt', 't-shirt', 'shirt'),
    days:   get('day(s) available', 'days available', 'available')
              .split(',').map(function(d){ return d.trim(); }).filter(Boolean),
    roles:  get('type of volunteer', 'volunteer work')
              .split(',').map(function(r){ return r.trim(); }).filter(Boolean),
    level:  get('lead or an assistant', 'lead or assistant', 'lead'),
    ts:     ts
  };
}

/* ── Format timestamp as YYYY-MM-DD HH:MM:SS ── */
function formatTs(ts) {
  if (!ts) return '';
  var d   = new Date(ts);
  var pad = function(n) { return String(n).padStart(2, '0'); };
  var h   = d.getHours(), m = d.getMinutes();
  var ampm = h >= 12 ? 'PM' : 'AM';
  var h12  = h % 12 || 12;
  return d.getFullYear() + '-' + pad(d.getMonth()+1) + '-' + pad(d.getDate()) +
    ' ' + h12 + ':' + pad(m) + ' ' + ampm;
}

/* ── Volunteer card (All Volunteers accordion) ── */
function buildVolCard(v, query) {
  query = query || '';
  function hl(str) {
    if (!query || !str) return str || '';
    return str.replace(
      new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi'),
      '<mark>$1</mark>'
    );
  }
  var lv = v.level.toLowerCase();
  var badge = lv.includes('lead')
    ? '<span class="badge b-lead">⭐ Lead</span>'
    : lv.includes('assistant')
      ? '<span class="badge b-asst">🤝 Assistant</span>'
      : '<span class="badge b-tbd">— TBD</span>';

  var isNew    = v.ts && (Date.now() - v.ts) < 86400000;
  var newBadge = isNew ? '<span class="badge b-new">🆕 New</span>' : '';
  var tsLabel  = v.ts ? '<span class="vol-ts">🕐 ' + formatTs(v.ts) + '</span>' : '';

  var dayTags = v.days.map(function(d) {
    var n = d.includes('July 9') ? '1' : d.includes('July 10') ? '2' : d.includes('July 11') ? '3' : d;
    return '<span class="tag">Day ' + n + '</span>';
  }).join('');
  var roleTags = v.roles.map(function(r) {
    return '<span class="tag">' + hl(r) + '</span>';
  }).join('');
  return '<div class="person-card' + (isNew ? ' person-card-new' : '') + '">' +
    '<div class="vol-top"><div>' +
      '<div class="vol-name">' + hl(v.name) + ' ' + newBadge + '</div>' +
    '</div>' + badge + '</div>' +
    '<div class="tag-row">' + (dayTags  || '<span class="tag" style="opacity:.5">—</span>') + '</div>' +
    '<div class="tag-row">' + (roleTags || '<span class="tag" style="opacity:.5">No role</span>') + '</div>' +
    '<div class="vol-foot">' +
      (v.age    ? '<span class="age-chip">'   + v.age    + '</span>' : '') +
      (v.church ? '<span class="vol-loc">⛪ ' + hl(v.church) + '</span>' : '') +
      (v.city   ? '<span class="vol-loc">📍 ' + v.city + (v.state ? ', ' + v.state : '') + '</span>' : '') +
      (v.tshirt ? '<span class="vol-loc">👕 ' + v.tshirt + '</span>' : '') +
    '</div></div>';
}

/* ── Team member mini-card (inside modal) ── */
function buildTeamMemberCard(v) {
  var lv = v.level.toLowerCase();
  var badge = lv.includes('lead')
    ? '<span class="badge b-lead" style="font-size:.62rem">⭐ Lead</span>'
    : lv.includes('assistant')
      ? '<span class="badge b-asst" style="font-size:.62rem">🤝 Asst</span>'
      : '';
  var dayTags = v.days.map(function(d) {
    var n = d.includes('July 9') ? '1' : d.includes('July 10') ? '2' : d.includes('July 11') ? '3' : d;
    return '<span class="tag" style="font-size:.62rem">Day ' + n + '</span>';
  }).join('');
  return '<div class="team-member-card">' +
    '<div class="tm-name">'  + v.name + '</div>' +
    '<div class="tm-badges">' +
      badge +
      (dayTags || '<span class="tag" style="opacity:.5;font-size:.62rem">—</span>') +
      (v.tshirt ? '<span class="tag" style="font-size:.62rem">👕 ' + v.tshirt + '</span>' : '') +
    '</div></div>';
}

/* ══════════════════════════════════════
   MODAL — open / close
══════════════════════════════════════ */
function openTeamModal(teamId) {
  var vols = window._allVols || [];
  var team = TEAMS.find(function(t) { return t.id === teamId; });
  if (!team) return;

  var members = vols.filter(function(v) { return volMatchesTeam(v, team); });

  document.getElementById('team-modal-title').innerHTML =
    '<span>' + team.icon + '</span> ' + team.name;
  document.getElementById('team-modal-meta').innerHTML =
    (team.leads.filter(Boolean).length ? '👑 ' + team.leads.join(' &amp; ') + ' &nbsp;·&nbsp; ' : '') +
    '<strong>' + members.length + '</strong> registered';

  var body = members.length
    ? '<div class="team-member-grid">' + members.map(buildTeamMemberCard).join('') + '</div>'
    : '<div class="team-modal-empty"><div class="nr-icon">📭</div>No registered volunteers for this team yet.</div>';

  document.getElementById('team-modal-body').innerHTML = body;

  var overlay = document.getElementById('team-modal-overlay');
  overlay.classList.add('open');
  document.body.style.overflow = 'hidden';

  document.querySelectorAll('.team-card').forEach(function(c) { c.classList.remove('active'); });
  var card = document.getElementById('card-' + teamId);
  if (card) card.classList.add('active');
}

function closeTeamModal() {
  document.getElementById('team-modal-overlay').classList.remove('open');
  document.body.style.overflow = '';
  document.querySelectorAll('.team-card').forEach(function(c) { c.classList.remove('active'); });
}

function handleModalOverlayClick(e) {
  if (e.target === document.getElementById('team-modal-overlay')) closeTeamModal();
}

document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeTeamModal();
});

/* ── All Volunteers accordion ── */
function toggleAllVols() {
  var body   = document.getElementById('vol-acc-body');
  var chev   = document.getElementById('vol-acc-chev');
  var isOpen = body.classList.contains('open');
  body.classList.toggle('open', !isOpen);
  chev.classList.toggle('open', !isOpen);
}

/* ══════════════════════════════════════
   MAIN RENDER
══════════════════════════════════════ */
function renderVols(vols) {
  /* sort newest first — this relies on the ts being parsed correctly in mapVol */
  vols = vols.slice().sort(function(a, b) { return b.ts - a.ts; });

  var total   = vols.length;
  var leads   = vols.filter(function(v){ return v.level.toLowerCase().includes('lead'); }).length;
  var assists = vols.filter(function(v){ return v.level.toLowerCase().includes('assistant'); }).length;
  var allDays = vols.filter(function(v){ return v.days.length >= 3; }).length;
  var rm = {};
  vols.forEach(function(v){ v.roles.forEach(function(r){ if (r) rm[r] = (rm[r]||0) + 1; }); });

  /* stat cards */
  document.getElementById('v-stats').innerHTML =
    '<div class="stat-card"><div class="stat-e">🙋</div><div class="stat-n">' + total   + '</div><div class="stat-l">Volunteers</div></div>' +
    '<div class="stat-card sc-amber"><div class="stat-e">⭐</div><div class="stat-n">'  + leads   + '</div><div class="stat-l">Leaders</div></div>' +
    '<div class="stat-card sc-teal"><div class="stat-e">🤝</div><div class="stat-n">'   + assists + '</div><div class="stat-l">Assistants</div></div>' +
    '<div class="stat-card sc-purple"><div class="stat-e">📅</div><div class="stat-n">' + allDays + '</div><div class="stat-l">All 3 Days</div></div>' +
    '<div class="stat-card sc-indigo"><div class="stat-e">🎯</div><div class="stat-n">' + Object.keys(rm).length + '</div><div class="stat-l">Role Types</div></div>';

  /* day boxes */
  var dayBoxes = DAY_INFO.map(function(d, i) {
    var dv = vols.filter(function(v){ return v.days.some(function(x){ return x.includes(d.key); }); });
    return '<div class="day-box">' +
      '<div class="day-lbl">'  + d.label + '</div>' +
      '<div class="day-date">' + d.date  + '</div>' +
      '<div class="day-num" style="color:' + DAY_COLORS[i] + '">' + dv.length + '</div>' +
      '<div class="day-names">' + dv.map(function(v){ return v.name.split(' ')[0]; }).join(' · ') + '</div>' +
    '</div>';
  }).join('');

  /* age pills */
  var ac = {};
  vols.forEach(function(v){ if (v.age) ac[v.age] = (ac[v.age]||0) + 1; });
  var agePills = Object.entries(ac).map(function(e) {
    return '<div class="pill"><div class="pill-dot" style="background:' + (AGE_C[e[0]]||'#7A8BAD') + '"></div>' + e[0] + ' <span class="pill-ct">(' + e[1] + ')</span></div>';
  }).join('');

  /* shirt pills */
  var shc = {};
  vols.forEach(function(v){ var s = (v.tshirt||'').replace(/\(adult\)/i,'').trim(); if (s) shc[s] = (shc[s]||0) + 1; });
  var shirtPills = Object.entries(shc).sort(function(a,b){ return b[1]-a[1]; }).map(function(e, i) {
    return '<div class="pill"><div class="pill-dot" style="background:' + SHIRT_C[i % SHIRT_C.length] + '"></div>' + e[0] + ' <span class="pill-ct">×' + e[1] + '</span></div>';
  }).join('');

  /* servant leaders banner */
  var servantBanner =
    '<div class="servant-banner">' +
      '<div class="servant-left">' +
        '<div class="servant-crown">🕊️</div>' +
        '<div>' +
          '<div class="servant-label">Servants of All</div>' +
          '<div class="servant-names">' + SERVANT_LEADERS.join(' &amp; ') + '</div>' +
          '<div class="servant-title">' + SERVANT_TITLE + '</div>' +
        '</div>' +
      '</div>' +
    '</div>';

  /* team cards */
  var maxTeamCount = Math.max.apply(null,
    TEAMS.map(function(t){ return vols.filter(function(v){ return volMatchesTeam(v,t); }).length; }).concat([1])
  );

  var teamCards = TEAMS.map(function(team) {
    var count = vols.filter(function(v){ return volMatchesTeam(v, team); }).length;
    var pct   = Math.round(count / maxTeamCount * 100);
    var leadsHtml = team.leads.filter(Boolean).length
      ? '<div class="team-leads">👑 ' + team.leads.join(' &amp; ') + '</div>'
      : '';
    return '<div class="team-card" id="card-' + team.id + '" onclick="openTeamModal(\'' + team.id + '\')">' +
      '<div class="team-card-header">' +
        '<div class="team-icon">' + team.icon + '</div>' +
        '<div style="flex:1;min-width:0">' +
          '<div class="team-name">' + team.name + '</div>' +
          (team.sub ? '<div style="font-size:.65rem;color:var(--muted);font-weight:700;margin-top:1px">' + team.sub + '</div>' : '') +
          leadsHtml +
        '</div>' +
      '</div>' +
      '<div class="team-card-right" style="padding:0 16px 14px">' +
        '<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">' +
          '<span class="team-count-pill" style="border-color:' + team.color + ';color:' + team.color + '">' + count + ' registered</span>' +
          '<span class="team-view-btn">View team ›</span>' +
        '</div>' +
        '<div style="background:#EEF3FB;border-radius:30px;height:8px;overflow:hidden">' +
          '<div style="height:100%;border-radius:30px;background:' + team.color + ';width:' + pct + '%;transition:width 1s ease"></div>' +
        '</div>' +
      '</div>' +
    '</div>';
  }).join('');

  /* all volunteers accordion */
  var volCards = vols.map(function(v){ return buildVolCard(v, ''); }).join('');
  var allVolsAccordion =
    '<div class="vol-accordion">' +
      '<div class="vol-acc-header" onclick="toggleAllVols()">' +
        '<div class="vol-acc-left">' +
          '<span style="font-size:1.2rem">🙋</span>' +
          '<span class="vol-acc-title">All Volunteers</span>' +
          '<span class="tshirt-total-badge">' + total + ' total</span>' +
        '</div>' +
        '<span class="vol-acc-chev" id="vol-acc-chev">▼</span>' +
      '</div>' +
      '<div class="vol-acc-body" id="vol-acc-body">' +
        '<div class="search-bar">' +
          '<div class="search-wrap"><span class="search-icon">🔍</span>' +
            '<input class="search-input" id="v-search" type="text" placeholder="Search by name, role, or church…" oninput="filterVolsList(this.value)">' +
            '<button class="btn-clear" id="v-clear" onclick="clearVolsSearch()" title="Clear search">✕</button>' +
          '</div>' +
          '<div class="search-count" id="v-count"><strong>' + total + '</strong> of ' + total + ' volunteers</div>' +
        '</div>' +
        '<div id="v-list" class="person-list">' + volCards + '</div>' +
        '<div class="no-results" id="v-no-results" style="display:none"><div class="nr-icon">🔎</div><div>No volunteers found</div></div>' +
      '</div>' +
    '</div>';

  document.getElementById('v-content').innerHTML =
    '<div class="grid2">' +
      '<div class="card"><div class="card-title">📅 Availability by Day</div><div class="day-grid">' + dayBoxes + '</div></div>' +
      '<div class="card"><div class="card-title">👥 Age Groups &amp; T-Shirts</div>' +
        '<div class="pill-section-lbl">Age Range</div>' +
        '<div class="pill-row">' + (agePills || '<span style="color:var(--muted)">No data</span>') + '</div>' +
        '<div class="pill-section-lbl">T-Shirt Sizes</div>' +
        '<div class="pill-row">' + (shirtPills || '<span style="color:var(--muted)">No data</span>') + '</div>' +
      '</div>' +
    '</div>' +
    servantBanner +
    '<div class="card" style="margin-bottom:18px">' +
      '<div class="card-title" style="margin-bottom:16px">👥 Teams &amp; Roster</div>' +
      '<div class="team-grid">' + teamCards + '</div>' +
    '</div>' +
    allVolsAccordion;

  window._allVols = vols;
  refreshTshirtSummary();
}

/* ── Search / filter ── */
function filterVolsList(query) {
  var vols     = window._allVols || [];
  var q        = query.trim().toLowerCase();
  var clearBtn = document.getElementById('v-clear');
  var countEl  = document.getElementById('v-count');
  var listEl   = document.getElementById('v-list');
  var noRes    = document.getElementById('v-no-results');

  if (clearBtn) clearBtn.classList.toggle('vis', q.length > 0);

  var filtered = q
    ? vols.filter(function(v) {
        return v.name.toLowerCase().includes(q) ||
          v.roles.some(function(r){ return r.toLowerCase().includes(q); }) ||
          (v.church||'').toLowerCase().includes(q) ||
          (v.email||'').toLowerCase().includes(q);
      })
    : vols;

  if (listEl)  listEl.innerHTML = filtered.map(function(v){ return buildVolCard(v, q); }).join('');
  if (countEl) countEl.innerHTML = '<strong>' + filtered.length + '</strong> of ' + vols.length + ' volunteers';
  if (noRes && listEl) {
    noRes.style.display  = filtered.length === 0 ? 'block' : 'none';
    listEl.style.display = filtered.length === 0 ? 'none'  : '';
  }
}

function clearVolsSearch() {
  var inp = document.getElementById('v-search');
  if (inp) { inp.value = ''; inp.focus(); }
  filterVolsList('');
}

/* ── Fetch & load ── */
async function loadVols() {
  document.getElementById('v-stats').innerHTML =
    '<div class="loading-box" style="grid-column:1/-1"><span class="spin">⏳</span> Loading…</div>';
  document.getElementById('v-content').innerHTML = '';
  try {
    var res = await fetch(CSV_VOLS + '&t=' + Date.now());
    if (!res.ok) throw new Error('HTTP ' + res.status);
    var vols = parseCSV(await res.text()).map(mapVol).filter(Boolean);
    if (!vols.length) throw new Error('No rows found — check headers');
    renderVols(vols);
  } catch(e) {
    document.getElementById('v-stats').innerHTML =
      '<div class="err-box" style="grid-column:1/-1">⚠️ Could not load volunteer data.<br>' +
      '<small>' + e.message + '</small><br>' +
      '<button class="btn-refresh" onclick="loadVols()" style="margin-top:10px">↻ Try Again</button></div>';
  }
}