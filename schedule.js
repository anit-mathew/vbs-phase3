/* ══════════════════════════════════════
   schedule.js — VBS Schedule tab logic
══════════════════════════════════════ */

const CSV_SCHEDULE = "https://docs.google.com/spreadsheets/d/e/2PACX-1vTnEdAcAFBerMxaovY8D69R0qZvvHGC50asn8uqWKZCE_7FXJ3_6z2JlobPwx2EdqtPTaIu0TUOb3Wh/pub?output=csv";

let _scheduleRows = [];
let _scheduleDay  = 1;

/* ── Row color mapping based on keywords ── */
function scheduleRowColor(activity) {
  if (!activity) return null;
  const a = activity.toLowerCase();
  if (a.includes('faith zone') || a.includes('mission'))    return { bg:'#FFF3CD', border:'#F59E0B', text:'#92400E' };
  if (a.includes('music zone') || a.includes('vbs songs') || a.includes('worship') || a.includes('praise')) return { bg:'#EDE9FE', border:'#8B5CF6', text:'#5B21B6' };
  if (a.includes('theme zone'))                              return { bg:'#FEF3C7', border:'#F97316', text:'#C2410C' };
  if (a.includes('craft'))                                   return { bg:'#FCE7F3', border:'#EC4899', text:'#9D174D' };
  if (a.includes('game') || a.includes('recreation'))        return { bg:'#D1FAE5', border:'#10B981', text:'#065F46' };
  if (a.includes('lunch') || a.includes('snack') || a.includes('food')) return { bg:'#FEE2E2', border:'#EF4444', text:'#991B1B' };
  if (a.includes('fun zone'))                                return { bg:'#DBEAFE', border:'#3B82F6', text:'#1E40AF' };
  if (a.includes('classroom breakout') || a.includes('object lesson')) return { bg:'#D1FAE5', border:'#059669', text:'#065F46' };
  if (a.includes('check') || a.includes('registration'))    return { bg:'#F3F4F6', border:'#9CA3AF', text:'#374151' };
  if (a.includes('carnival') || a.includes('certificate'))  return { bg:'#FEF9C3', border:'#EAB308', text:'#713F12' };
  return null;
}

/* ── Parse CSV rows into day blocks ── */
function parseScheduleCSV(rows) {
  // rows[0] = logo/header row, rows[1] = activity headers, rows[2] = col headers (Start/End/Duration)
  // rows[3+] = data
  const data = rows.slice(3);
  const days = { 1: [], 2: [], 3: [] };
  let currentDay = 1;
  data.forEach(function(r) {
    const start    = r['A'] || r['Start'] || Object.values(r)[0] || '';
    const end      = r['B'] || r['End']   || Object.values(r)[1] || '';
    const duration = r['C'] || r['Duration'] || Object.values(r)[2] || '';
    const pre      = r['D'] || Object.values(r)[3] || '';
    const primary  = r['E'] || Object.values(r)[4] || '';
    const junior   = r['F'] || Object.values(r)[5] || '';
    const team     = r['G'] || Object.values(r)[6] || '';

    // Detect day header row
    const combined = (start + pre + primary + junior).toLowerCase();
    if (combined.includes('day 1') || combined.includes('july 9')) { currentDay = 1; return; }
    if (combined.includes('day 2') || combined.includes('july 10')) { currentDay = 2; return; }
    if (combined.includes('day 3') || combined.includes('july 11')) { currentDay = 3; return; }

    // Skip rows with no valid time (header rows, empty rows, merged label rows)
    if (!start || !start.match(/^\d{1,2}:\d{2}/)) return;
    if (!pre && !primary && !junior) return;

    days[currentDay].push({ start, end, duration, pre, primary, junior, team });
  });
  return days;
}

/* ── Render schedule for selected day ── */
function renderSchedule() {
  const el = document.getElementById('sched-content');
  if (!el) return;

  if (!_scheduleRows.length) {
    el.innerHTML = '<div class="loading-box"><span class="spin">⏳</span> Loading schedule…</div>';
    return;
  }

  const days = parseScheduleCSV(_scheduleRows);
  const rows = days[_scheduleDay] || [];

  if (!rows.length) {
    el.innerHTML = '<div class="loading-box">No schedule data for this day.</div>';
    return;
  }

  // Inject schedule styles once
  if (!document.getElementById('sched-styles')) {
    const s = document.createElement('style');
    s.id = 'sched-styles';
    s.textContent = `
      .sched-wrap {
        overflow-x: auto;
        overflow-y: auto;
        max-height: calc(100vh - 280px);
      }
      /* Desktop: show table, hide cards */
      .sched-cards { display: none; }
      .sched-table-wrap { display: block; }

      @media (max-width: 680px) {
        .sched-wrap { max-height: none; overflow: visible; }
        .sched-table-wrap { display: none; }
        .sched-cards { display: flex; flex-direction: column; gap: 10px; }
        .sched-card {
          background: white; border-radius: 14px;
          border: 1.5px solid var(--border);
          padding: 12px 14px;
          box-shadow: 0 2px 8px rgba(26,39,68,.06);
          animation: sched-fade .3s ease both;
        }
        .sched-card-time {
          display: flex; align-items: center; gap: 8px;
          margin-bottom: 8px;
        }
        .sched-card-time-range {
          font-family: 'Fredoka One', cursive; font-size: 1rem; color: var(--navy);
        }
        .sched-card-dur {
          font-size: .7rem; font-weight: 800; color: white;
          background: var(--navy); border-radius: 99px; padding: 2px 8px;
        }
        .sched-card-team {
          font-size: .72rem; color: var(--muted); font-style: italic;
          margin-top: 6px; font-weight: 600;
        }
        .sched-card-group-label {
          font-size: .62rem; font-weight: 800; text-transform: uppercase;
          letter-spacing: .4px; color: var(--muted); margin-bottom: 3px;
        }
        .sched-card-activity {
          font-weight: 700; font-size: .82rem; border-radius: 8px;
          padding: 5px 10px; display: block; width: 100%; box-sizing: border-box;
        }
        .sched-card-split { display: flex; flex-direction: column; gap: 6px; }
      }
      .sched-table { width: 100%; border-collapse: collapse; font-size: .84rem; min-width: 700px; }
      .sched-table thead { position: sticky; top: 0; z-index: 2; }
      .sched-table th {
        background: var(--navy); color: white; padding: 10px 12px;
        text-align: left; font-size: .72rem; font-weight: 800;
        text-transform: uppercase; letter-spacing: .4px; white-space: nowrap;
      }
      .sched-table th:first-child { border-radius: 10px 0 0 0; }
      .sched-table th:last-child  { border-radius: 0 10px 0 0; }
      .sched-table td { padding: 10px 12px; vertical-align: middle; border-bottom: 1px solid var(--border); }
      .sched-table tr:last-child td { border-bottom: none; }
      .sched-table tr:hover td { filter: brightness(.97); }
      .sched-time { font-family: 'Fredoka One', cursive; font-size: .92rem; color: var(--navy); white-space: nowrap; }
      .sched-dur  { font-size: .72rem; font-weight: 800; color: var(--muted); white-space: nowrap; }
      .sched-activity {
        font-weight: 700; font-size: .84rem; border-radius: 8px;
        padding: 5px 10px; display: inline-block; width: 100%;
      }
      .sched-team { font-size: .75rem; color: var(--muted); font-weight: 600; font-style: italic; }
      .sched-full { font-weight: 700; font-size: .86rem; }
      .sched-day-btn {
        padding: 8px 20px; border-radius: 99px; border: 2px solid var(--border);
        background: var(--card-bg); font-family: 'Nunito', sans-serif;
        font-size: .82rem; font-weight: 800; cursor: pointer; color: var(--muted);
        transition: all .15s; white-space: nowrap; flex-shrink: 0;
      }
      .sched-day-btn.active {
        background: var(--navy); color: var(--sun); border-color: var(--navy);
      }
      .sched-day-btn:hover:not(.active) { border-color: var(--navy); color: var(--navy); }
      .sched-day-sel {
        display: flex; gap: 8px; margin-bottom: 14px;
        position: sticky; top: 0; z-index: 3;
        background: var(--page-bg); padding: 12px 0 8px;
        overflow-x: auto; -webkit-overflow-scrolling: touch;
      }
      .sched-legend {
        display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px;
        position: sticky; top: 52px; z-index: 3;
        background: var(--page-bg); padding-bottom: 8px;
      }
      @media (max-width: 480px) {
        .sched-day-btn { padding: 7px 16px; font-size: .76rem; }
        .sched-day-sel { gap: 6px; padding: 10px 0 6px; }
        .sched-legend { top: 46px; gap: 5px; }
        .sched-legend-chip { font-size: .62rem; padding: 2px 8px; }
      }
      .sched-legend-chip {
        font-size: .68rem; font-weight: 800; padding: 3px 10px;
        border-radius: 99px; border: 1.5px solid;
      }
      @keyframes sched-fade { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
      .sched-table tbody tr { animation: sched-fade .3s ease both; }
    `;
    document.head.appendChild(s);
  }

  const dayLabels = { 1:'Day 1 · Thu Jul 9', 2:'Day 2 · Fri Jul 10', 3:'Day 3 · Sat Jul 11' };

  // Legend
  const legendItems = [
    { label:'Faith Zone', bg:'#FFF3CD', border:'#F59E0B', text:'#92400E' },
    { label:'Music/Worship', bg:'#EDE9FE', border:'#8B5CF6', text:'#5B21B6' },
    { label:'Theme Zone', bg:'#FEF3C7', border:'#F97316', text:'#C2410C' },
    { label:'Craft', bg:'#FCE7F3', border:'#EC4899', text:'#9D174D' },
    { label:'Games', bg:'#D1FAE5', border:'#10B981', text:'#065F46' },
    { label:'Food', bg:'#FEE2E2', border:'#EF4444', text:'#991B1B' },
    { label:'Fun Zone', bg:'#DBEAFE', border:'#3B82F6', text:'#1E40AF' },
  ];
  const legend = '<div class="sched-legend">' +
    legendItems.map(function(l) {
      return '<span class="sched-legend-chip" style="background:' + l.bg + ';border-color:' + l.border + ';color:' + l.text + '">' + l.label + '</span>';
    }).join('') + '</div>';

  // Table rows
  const tableRows = rows.map(function(r, i) {
    const isFull = r.pre === r.primary && r.primary === r.junior && r.pre;
    const delay  = i * 30;

    if (isFull || (!r.primary && !r.junior && r.pre)) {
      // Full-width activity
      const activity = r.pre || r.primary || r.junior;
      const col = scheduleRowColor(activity);
      const bg  = col ? col.bg : (i % 2 === 0 ? 'white' : '#FAFAF9');
      const textStyle = col ? 'color:' + col.text + ';background:' + col.bg + ';border:1.5px solid ' + col.border : '';
      return '<tr style="background:' + bg + ';animation-delay:' + delay + 'ms">' +
        '<td class="sched-time">' + r.start + '</td>' +
        '<td class="sched-time">' + r.end + '</td>' +
        '<td class="sched-dur">' + r.duration + '</td>' +
        '<td colspan="3"><span class="sched-activity" style="' + textStyle + '">' + activity + '</span></td>' +
        '<td class="sched-team">' + r.team + '</td>' +
      '</tr>';
    }

    // Split activities
    const bg = i % 2 === 0 ? 'white' : '#FAFAF9';
    function cell(act) {
      if (!act) return '<td></td>';
      const col = scheduleRowColor(act);
      const style = col ? 'background:' + col.bg + ';border:1.5px solid ' + col.border + ';color:' + col.text : 'background:#F5F5F4';
      return '<td><span class="sched-activity" style="' + style + '">' + act + '</span></td>';
    }
    return '<tr style="background:' + bg + ';animation-delay:' + delay + 'ms">' +
      '<td class="sched-time">' + r.start + '</td>' +
      '<td class="sched-time">' + r.end + '</td>' +
      '<td class="sched-dur">' + r.duration + '</td>' +
      cell(r.pre) + cell(r.primary) + cell(r.junior) +
      '<td class="sched-team">' + r.team + '</td>' +
    '</tr>';
  }).join('');

  // ── Mobile card layout ──
  const cardRows = rows.map(function(r, i) {
    const isFull = r.pre === r.primary && r.primary === r.junior && r.pre;
    const activity = r.pre || r.primary || r.junior;
    const delay = i * 30;
    const col = scheduleRowColor(activity || r.pre);

    if (isFull || (!r.primary && !r.junior && r.pre)) {
      const style = col ? 'background:' + col.bg + ';border:1.5px solid ' + col.border + ';color:' + col.text : 'background:#F5F5F4';
      return '<div class="sched-card" style="animation-delay:' + delay + 'ms' + (col ? ';border-color:' + col.border : '') + '">' +
        '<div class="sched-card-time">' +
          '<span class="sched-card-time-range">' + r.start + ' – ' + r.end + '</span>' +
          '<span class="sched-card-dur">' + r.duration + '</span>' +
        '</div>' +
        '<span class="sched-card-activity" style="' + style + '">' + activity + '</span>' +
        (r.team ? '<div class="sched-card-team">👤 ' + r.team + '</div>' : '') +
      '</div>';
    }

    function mobileCell(label, act) {
      if (!act) return '';
      const c = scheduleRowColor(act);
      const s = c ? 'background:' + c.bg + ';border:1.5px solid ' + c.border + ';color:' + c.text : 'background:#F5F5F4';
      return '<div>' +
        '<div class="sched-card-group-label">' + label + '</div>' +
        '<span class="sched-card-activity" style="' + s + '">' + act + '</span>' +
      '</div>';
    }
    return '<div class="sched-card" style="animation-delay:' + delay + 'ms">' +
      '<div class="sched-card-time">' +
        '<span class="sched-card-time-range">' + r.start + ' – ' + r.end + '</span>' +
        '<span class="sched-card-dur">' + r.duration + '</span>' +
      '</div>' +
      '<div class="sched-card-split">' +
        mobileCell('🎠 Pre-Primary', r.pre) +
        mobileCell('📘 Primary', r.primary) +
        mobileCell('🎓 Junior', r.junior) +
      '</div>' +
      (r.team ? '<div class="sched-card-team">👤 ' + r.team + '</div>' : '') +
    '</div>';
  }).join('');

  el.innerHTML =
    '<div class="sched-day-sel">' +
      [1,2,3].map(function(d) {
        return '<button class="sched-day-btn' + (d === _scheduleDay ? ' active' : '') + '" onclick="setScheduleDay(' + d + ')">' + dayLabels[d] + '</button>';
      }).join('') +
    '</div>' +
    legend +
    // Desktop table
    '<div class="sched-table-wrap">' +
      '<div class="card" style="padding:0;overflow:hidden">' +
        '<div class="sched-wrap">' +
          '<table class="sched-table">' +
            '<thead><tr>' +
              '<th>Start</th><th>End</th><th>Duration</th>' +
              '<th>🎠 Pre-Primary<br><span style="font-weight:500;text-transform:none;letter-spacing:0;font-size:.68rem">K – 2nd Grade</span></th>' +
              '<th>📘 Primary<br><span style="font-weight:500;text-transform:none;letter-spacing:0;font-size:.68rem">3rd – 5th Grade</span></th>' +
              '<th>🎓 Junior<br><span style="font-weight:500;text-transform:none;letter-spacing:0;font-size:.68rem">6th – 8th Grade</span></th>' +
              '<th>👤 Team / Person</th>' +
            '</tr></thead>' +
            '<tbody>' + tableRows + '</tbody>' +
          '</table>' +
        '</div>' +
      '</div>' +
    '</div>' +
    // Mobile cards
    '<div class="sched-cards">' + cardRows + '</div>';
}

function setScheduleDay(d) {
  _scheduleDay = d;
  renderSchedule();
}

async function loadSchedule() {
  const el = document.getElementById('sched-content');
  if (!el) return;
  el.innerHTML = '<div class="loading-box"><span class="spin">⏳</span> Loading schedule…</div>';
  try {
    const res = await fetch(CSV_SCHEDULE + '&t=' + Date.now());
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const text = await res.text();
    // Parse CSV with column letters as keys
    const lines = text.trim().split('\n');
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    _scheduleRows = lines.map(function(line) {
      const vals = parseCSV(line + '\n')[0] || {};
      // Also map by position letter
      const cells = splitLine(line);
      const obj = {};
      cells.forEach(function(v, i) {
        if (i < 26) obj[alphabet[i]] = v.trim().replace(/^"|"$/g,'');
      });
      return obj;
    });
    renderSchedule();
  } catch(e) {
    if (el) el.innerHTML = '<div class="err-box">⚠️ Could not load schedule.<br><small>' + e.message + '</small></div>';
  }
}