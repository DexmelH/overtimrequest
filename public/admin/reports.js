import { apiUrl } from "../shared/js/api.js";
import { apiGet, fetchWithTimeout } from "../shared/js/http.js";
import { showToast } from "../shared/js/toast.js";
import { escapeHtml } from "../shared/js/escapeHtml.js";
import { formatIsoDate } from "../shared/js/listQuery.js";

let reportRows = [];
let reportSummary = null;
let groupsLoaded = false;
let reportLoaded = false;
let syncingDates = false;

function pad(n) {
  return String(n).padStart(2, "0");
}

function currentMonthValue(date = new Date()) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

function monthBounds(monthValue) {
  if (!monthValue || !/^\d{4}-\d{2}$/.test(monthValue)) {
    const now = new Date();
    monthValue = currentMonthValue(now);
  }
  const [y, m] = monthValue.split("-").map(Number);
  const from = new Date(y, m - 1, 1);
  const to = new Date(y, m, 0);
  return { from: formatIsoDate(from), to: formatIsoDate(to) };
}

function setCurrentMonthDefaults() {
  const month = currentMonthValue();
  const bounds = monthBounds(month);
  syncingDates = true;
  $("#reportMonth").val(month);
  $("#reportFrom").val(bounds.from);
  $("#reportTo").val(bounds.to);
  syncingDates = false;
}

function readFilters() {
  return {
    from: $("#reportFrom").val(),
    to: $("#reportTo").val(),
    group: $("#reportGroup").val(),
    status: $("#reportStatus").val() || "approved",
    group_by: $("#reportGroupBy").val() || "project",
    q: ($("#reportSearch").val() || "").trim().toLowerCase(),
  };
}

function buildReportParams(extra = {}) {
  const filters = readFilters();
  const params = new URLSearchParams();
  if (filters.from) params.set("from", filters.from);
  if (filters.to) params.set("to", filters.to);
  if (filters.group) params.set("group", filters.group);
  if (filters.status) params.set("status", filters.status);
  if (filters.group_by) params.set("group_by", filters.group_by);
  Object.entries(extra).forEach(([key, value]) => {
    if (value !== null && value !== undefined && String(value) !== "") {
      params.set(key, String(value));
    }
  });
  return params;
}

async function loadReportGroups() {
  if (groupsLoaded) return;
  try {
    const json = await apiGet(apiUrl("/admin/groups"));
    const $sel = $("#reportGroup").empty().append('<option value="">All groups</option>');
    (json?.data || []).forEach((g) => {
      const label = g.abbreviation
        ? `${g.abbreviation}${g.name ? ` — ${g.name}` : ""}`
        : g.name || `Group #${g.id}`;
      $sel.append(
        `<option value="${escapeHtml(g.id)}">${escapeHtml(label)}</option>`,
      );
    });
    groupsLoaded = true;
  } catch {
    showToast("Could not load groups for the report filter.", { type: "error" });
  }
}

function matchesSearch(row, q) {
  if (!q) return true;
  const hay = [
    row.employee_id,
    row.employee_name,
    row.group_name,
    row.project_id,
    row.project_name,
  ]
    .filter((v) => v !== null && v !== undefined && v !== "")
    .join(" ")
    .toLowerCase();
  return hay.includes(q);
}

function renderSummary(summary) {
  const s = summary || {};
  const cards = [
    { label: "Total hours", value: s.total_hours ?? 0, tone: "accent" },
    { label: "Employees", value: s.employee_count ?? 0, tone: "pending" },
    { label: "Projects", value: s.project_count ?? 0, tone: "done" },
    { label: "Requests", value: s.request_count ?? 0, tone: "" },
  ];

  const $wrap = $("#reportSummaryCards").empty();
  cards.forEach((card) => {
    $wrap.append(`
      <div class="col-6 col-lg-3">
        <div class="ot-card stat-card ${card.tone === "pending" ? "stat-pending" : ""} ${
          card.tone === "done" ? "stat-done" : ""
        }">
          <div class="ot-card-body">
            <div class="stat-label">${escapeHtml(card.label)}</div>
            <div class="stat-value">${escapeHtml(card.value)}</div>
          </div>
        </div>
      </div>
    `);
  });
}

function tableHeaders(groupBy) {
  if (groupBy === "group") {
    return ["Group", "Employees", "Projects", "Requests", "Hours"];
  }
  if (groupBy === "employee") {
    return ["Employee ID", "Employee", "Group", "Projects", "Requests", "Hours"];
  }
  return ["Employee ID", "Employee", "Group", "Project", "Requests", "Hours"];
}

function renderTable(rows, groupBy) {
  const headers = tableHeaders(groupBy);
  $("#reportTableHead").html(
    headers.map((h) => `<th>${escapeHtml(h)}</th>`).join(""),
  );

  const $tbody = $("#reportTable tbody").empty();
  if (!rows.length) {
    $("#reportEmpty").removeClass("d-none");
    $("#reportPagerInfo").text("No rows");
    return;
  }
  $("#reportEmpty").addClass("d-none");

  rows.forEach((row) => {
    let cells;
    if (groupBy === "group") {
      cells = [
        row.group_name || "—",
        row.employee_count ?? "—",
        row.project_count ?? "—",
        row.request_count ?? 0,
        row.hours ?? 0,
      ];
    } else if (groupBy === "employee") {
      cells = [
        row.employee_id ?? "—",
        row.employee_name || "—",
        row.group_name || "—",
        row.project_count ?? "—",
        row.request_count ?? 0,
        row.hours ?? 0,
      ];
    } else {
      cells = [
        row.employee_id ?? "—",
        row.employee_name || "—",
        row.group_name || "—",
        row.project_name || "—",
        row.request_count ?? 0,
        row.hours ?? 0,
      ];
    }

    $tbody.append(
      `<tr>${cells
        .map((value, index) => {
          const isHours = index === cells.length - 1;
          return `<td${isHours ? ' class="report-hours"' : ""}>${escapeHtml(value)}</td>`;
        })
        .join("")}</tr>`,
    );
  });

  const hoursShown = rows.reduce((sum, row) => sum + Number(row.hours || 0), 0);
  $("#reportPagerInfo").text(
    `${rows.length} row${rows.length === 1 ? "" : "s"} · ${hoursShown.toFixed(2)} hrs shown`,
  );
}

function applyClientFilter() {
  const filters = readFilters();
  const filtered = reportRows.filter((row) => matchesSearch(row, filters.q));
  renderTable(filtered, filters.group_by);
}

export async function loadOtReport() {
  $("#reportLoading").removeClass("d-none");
  $("#reportEmpty").addClass("d-none");

  const params = buildReportParams();
  try {
    const json = await apiGet(apiUrl("/admin/reports/ot") + "?" + params.toString(), {
      timeout: 20000,
    });
    if (!json?.success) {
      showToast(json?.message || "Could not load OT report.", { type: "error" });
      return;
    }

    reportRows = Array.isArray(json.data) ? json.data : [];
    reportSummary = json.summary || null;
    renderSummary(reportSummary);
    applyClientFilter();
  } catch {
    showToast("Failed to load OT report.", { type: "error" });
    reportRows = [];
    reportSummary = null;
    renderSummary({});
    renderTable([], readFilters().group_by);
  } finally {
    $("#reportLoading").addClass("d-none");
  }
}

async function downloadCsv() {
  const params = buildReportParams({ format: "csv" });
  const url = apiUrl("/admin/reports/ot") + "?" + params.toString();
  try {
    const response = await fetchWithTimeout(
      url,
      { method: "GET", credentials: "same-origin" },
      30000,
    );
    if (!response.ok) {
      throw new Error(`CSV download failed (${response.status})`);
    }
    const blob = await response.blob();
    const disposition = response.headers.get("Content-Disposition") || "";
    const match = disposition.match(/filename="?([^"]+)"?/i);
    const filename = match?.[1] || "ot-report.csv";

    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement("a");
    anchor.href = objectUrl;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(objectUrl);
    showToast("CSV download started.", { type: "success", duration: 2500 });
  } catch {
    showToast("Could not download CSV.", { type: "error" });
  }
}

export function initOtReports() {
  setCurrentMonthDefaults();
  renderSummary({});
  $("#reportTableHead").html(
    tableHeaders("project")
      .map((h) => `<th>${escapeHtml(h)}</th>`)
      .join(""),
  );

  $("#reportMonth").on("change", function () {
    if (syncingDates) return;
    const bounds = monthBounds($(this).val());
    syncingDates = true;
    $("#reportFrom").val(bounds.from);
    $("#reportTo").val(bounds.to);
    syncingDates = false;
  });

  $("#reportFrom, #reportTo").on("change", function () {
    if (syncingDates) return;
    $("#reportMonth").val("");
  });

  $("#reportFilterForm").on("submit", function (e) {
    e.preventDefault();
    loadOtReport().catch(() => {});
  });

  $("#reportSearch").on("input", function () {
    applyClientFilter();
  });

  $("#reportRefreshBtn").on("click", () => loadOtReport().catch(() => {}));
  $("#reportCsvBtn").on("click", () => downloadCsv().catch(() => {}));

  $("#tab-reports").on("shown.bs.tab", async () => {
    await loadReportGroups();
    if (!reportLoaded) {
      reportLoaded = true;
      await loadOtReport();
    }
  });
}
