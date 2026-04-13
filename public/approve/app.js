/* Overtime Approval App
   - Uses jQuery and Bootstrap
   - Persists demo data in localStorage
   - Rows are clickable: clicking a row opens the details modal
   - Clicking action buttons (Approve, Reset, etc.) will not trigger row click
*/

const MANAGERS = [
  { id: "MGR1001", name: "Aileen Santos" },
  { id: "MGR1002", name: "Carlos Reyes" },
  { id: "MGR1003", name: "Bea Navarro" },
  { id: "MGR1004", name: "Daniel Cruz" },
];

const DEMO_REQUESTS = [
  {
    requestId: "OT-2026-001",
    employeeName: "Juan Dela Cruz",
    employeeId: "EMP2001",
    date: "2026-04-10",
    hours: 3,
    reason: "Finish client deliverable and testing",
    approvals: {},
    viewed: false,
  },
  {
    requestId: "OT-2026-002",
    employeeName: "Maria Lopez",
    employeeId: "EMP2002",
    date: "2026-04-11",
    hours: 2.5,
    reason: "Urgent bug fix after hours",
    approvals: {},
    viewed: false,
  },
  {
    requestId: "OT-2026-003",
    employeeName: "Ramon Santos",
    employeeId: "EMP2003",
    date: "2026-04-12",
    hours: 4,
    reason: "System migration support",
    approvals: {},
    viewed: false,
  },
];

const STORAGE_KEY = "overtime_approval_demo_v1";

function loadData() {
  const raw = localStorage.getItem(STORAGE_KEY);
  if (!raw) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(DEMO_REQUESTS));
    return JSON.parse(JSON.stringify(DEMO_REQUESTS));
  }
  try {
    return JSON.parse(raw);
  } catch (e) {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(DEMO_REQUESTS));
    return JSON.parse(JSON.stringify(DEMO_REQUESTS));
  }
}

function saveData(data) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
}

function renderTable() {
  const data = loadData();
  const $tbody = $("#requestsTable tbody").empty();
  data.forEach((req) => {
    const approvedCount = Object.values(req.approvals || {}).filter(
      (a) => a.approved,
    ).length;

    // Actions cell: show View button only if not viewed
    const actionHtml = req.viewed
      ? `<div class="viewed-label">Viewed</div>`
      : `<button class="btn btn-sm btn-outline-primary btn-view">View</button>`;

    const $tr = $(`
      <tr data-id="${req.requestId}" class="clickable-row">
        <td class="nowrap"><strong>${req.requestId}</strong></td>
        <td>
          <div><strong>${req.employeeName}</strong></div>
          <div class="small-muted">ID: ${req.employeeId}</div>
        </td>
        <td>${req.date}</td>
        <td>${req.hours}</td>
        <td class="text-truncate" style="max-width:220px">${req.reason}</td>
        <td>
          <div class="small-muted">${approvedCount} / ${MANAGERS.length} approved</div>
          <div class="mt-1 approval-list"></div>
        </td>
        <td class="text-end">
          ${actionHtml}
        </td>
      </tr>
    `);

    const $approvalList = $tr.find(".approval-list");
    MANAGERS.forEach((m) => {
      const a =
        req.approvals && req.approvals[m.id] && req.approvals[m.id].approved;
      const badge = $("<span>")
        .addClass("badge")
        .addClass(a ? "bg-success text-white" : "bg-secondary text-white")
        .text(m.name.split(" ")[0])
        .attr("title", m.name + " (" + m.id + ")");
      $approvalList.append(badge);
    });

    $tbody.append($tr);
  });
}

function showDetails(requestId) {
  const data = loadData();
  const req = data.find((r) => r.requestId === requestId);
  if (!req) return;

  $("#requestDetails").html(`
    <div class="d-flex justify-content-between">
      <div>
        <div><strong>${req.requestId}</strong></div>
        <div class="small-muted">Employee: ${req.employeeName} — ID: ${req.employeeId}</div>
      </div>
      <div class="text-end small-muted">
        <div>Date: ${req.date}</div>
        <div>Hours: ${req.hours}</div>
      </div>
    </div>
  `);

  $("#otInfo").html(`
    <p><strong>Reason</strong></p>
    <p class="mb-0">${req.reason}</p>
  `);

  const $managers = $("#managersList").empty();
  MANAGERS.forEach((m) => {
    const approved =
      req.approvals && req.approvals[m.id] && req.approvals[m.id].approved;
    const ts = req.approvals && req.approvals[m.id] && req.approvals[m.id].ts;
    const $row = $(`
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div>
          <div><strong>${m.name}</strong></div>
          <div class="small-muted">Manager ID: ${m.id}</div>
        </div>
        <div class="text-end">
          <button class="btn btn-sm manager-btn ${approved ? "btn-success" : "btn-outline-primary"}" data-mgr="${m.id}">
            ${approved ? "Approved" : "Approve"}
          </button>
          ${approved ? `<div class="timestamp mt-1">at ${new Date(ts).toLocaleString()}</div>` : ""}
        </div>
      </div>
    `);
    $managers.append($row);
  });

  renderApprovalSummary(req);

  // manager buttons should not propagate to row click
  $("#managersList .manager-btn")
    .off("click")
    .on("click", function (e) {
      e.stopPropagation();
      const mgrId = $(this).data("mgr");
      toggleApproval(requestId, mgrId);
      showDetails(requestId);
      renderTable();
    });

  const modal = new bootstrap.Modal(document.getElementById("detailsModal"));
  modal.show();
}

function markViewed(requestId) {
  const data = loadData();
  const req = data.find((r) => r.requestId === requestId);
  if (!req) return;
  req.viewed = true;
  saveData(data);
}

function toggleApproval(requestId, managerId) {
  const data = loadData();
  const req = data.find((r) => r.requestId === requestId);
  if (!req) return;
  req.approvals = req.approvals || {};
  const current = req.approvals[managerId];
  if (current && current.approved) {
    req.approvals[managerId] = { approved: false, ts: null };
  } else {
    req.approvals[managerId] = { approved: true, ts: Date.now() };
  }
  saveData(data);
}

function renderApprovalSummary(req) {
  const $summary = $("#approvalSummary").empty();
  MANAGERS.forEach((m) => {
    const a = req.approvals && req.approvals[m.id];
    if (a && a.approved) {
      const ts = new Date(a.ts).toLocaleString();
      const $b = $(
        `<span class="badge bg-success text-white">✔ ${m.name} (${m.id}) — <span class="timestamp">${ts}</span></span>`,
      );
      $summary.append($b);
    }
  });
  if ($summary.children().length === 0) {
    $summary.html('<div class="small-muted">No approvals yet.</div>');
  }
}

$(function () {
  renderTable();

  // Row click opens modal. Use delegation so newly rendered rows work.
  $("#requestsTable tbody").on("click", "tr.clickable-row", function (e) {
    // If the click originated from a control (button, link, input), ignore here.
    const $target = $(e.target);
    if (
      $target.is("button") ||
      $target.closest("button").length ||
      $target.is("a") ||
      $target.closest("a").length
    ) {
      return;
    }

    const id = $(this).data("id");
    // Mark viewed and re-render so action cell updates immediately
    markViewed(id);
    renderTable();
    showDetails(id);
  });

  // Keep View button behavior (if present) — prevent row click propagation
  $("#requestsTable").on("click", ".btn-view", function (e) {
    e.stopPropagation();
    const id = $(this).closest("tr").data("id");
    markViewed(id);
    renderTable();
    showDetails(id);
  });

  // Prevent manager buttons in table approval badges from triggering row click
  // (approval badges are not interactive in the table; manager actions happen in modal)

  $("#resetStorage").on("click", function () {
    if (!confirm("Reset demo data to initial state?")) return;
    localStorage.removeItem(STORAGE_KEY);
    renderTable();
    alert("Demo data reset.");
  });

  $("#detailsModal").on("hidden.bs.modal", function () {
    renderTable();
  });

  // Seed a demo approval if none exist
  const data = loadData();
  if (Object.keys(data[0].approvals || {}).length === 0) {
    data[0].approvals = {};
    data[0].approvals[MANAGERS[0].id] = {
      approved: true,
      ts: Date.now() - 1000 * 60 * 60,
    };
    saveData(data);
    renderTable();
  }
});
