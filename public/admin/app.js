import { apiUrl } from "../shared/js/api.js";
import { apiGet } from "../shared/js/http.js";
import { showToast } from "../shared/js/toast.js";
import { initApprovers } from "./approvers.js";

let currentPage = 1;
let totalPages = 1;
const filters = { search: "", action: "", user_id: "", from: "", to: "" };

const ACTION_LABELS = {
  "request.submit": "Submit request",
  "request.cancel": "Cancel request",
  "request.approve": "Approve request",
  "request.reject": "Reject request",
  "admin.approvers.save": "Save group approvers",
};

function formatAction(action) {
  return ACTION_LABELS[action] || action;
}

function formatDetails(details) {
  if (!details) return "—";
  if (typeof details === "object") {
    return JSON.stringify(details);
  }
  return String(details);
}

function formatDate(iso) {
  if (!iso) return "—";
  const d = new Date(iso);
  return d.toLocaleString();
}

async function checkAccess() {
  try {
    const json = await apiGet(apiUrl("/admin/session"));
    if (json?.is_admin) {
      $("#accessDenied").addClass("d-none");
      $("#adminContent").removeClass("d-none");
      return true;
    }
  } catch (e) {
    console.error(e);
  }
  $("#adminContent").addClass("d-none");
  $("#accessDenied").removeClass("d-none");
  return false;
}

function renderSummary(summary) {
  const $wrap = $("#summaryCards").empty();
  (summary || []).slice(0, 6).forEach((item) => {
    $wrap.append(`
      <div class="col-md-4 col-lg-2">
        <div class="ot-card summary-card h-100">
          <div class="ot-card-body py-3">
            <div class="count">${item.total}</div>
            <div class="label">${formatAction(item.action)}</div>
          </div>
        </div>
      </div>
    `);
  });
}

function populateActionFilter(summary) {
  const $sel = $("#filterAction");
  const current = $sel.val();
  $sel.find("option:not(:first)").remove();
  (summary || []).forEach((item) => {
    $sel.append(`<option value="${item.action}">${formatAction(item.action)} (${item.total})</option>`);
  });
  if (current) $sel.val(current);
}

function renderLogs(rows) {
  const $tbody = $("#logsTable tbody").empty();
  if (!rows?.length) {
    $("#logsEmpty").removeClass("d-none");
    return;
  }
  $("#logsEmpty").addClass("d-none");

  rows.forEach((row) => {
    const entity = row.entity_type
      ? `${row.entity_type}${row.entity_id ? " #" + row.entity_id : ""}`
      : "—";
    $tbody.append(`
      <tr>
        <td class="text-nowrap">${formatDate(row.created_at)}</td>
        <td>
          <div class="fw-semibold">${row.user_name || "—"}</div>
          <small class="ot-muted">ID ${row.user_id ?? "—"}</small>
        </td>
        <td><span class="log-action">${formatAction(row.action)}</span></td>
        <td>${entity}</td>
        <td class="log-details">${formatDetails(row.details)}</td>
        <td class="text-nowrap ot-muted">${row.ip_address || "—"}</td>
      </tr>
    `);
  });
}

async function loadLogs(page = 1) {
  $("#logsLoading").removeClass("d-none");
  $("#logsEmpty").addClass("d-none");
  currentPage = page;

  const params = new URLSearchParams({
    page: String(page),
    limit: "50",
  });
  if (filters.search) params.set("search", filters.search);
  if (filters.action) params.set("action", filters.action);
  if (filters.user_id) params.set("user_id", filters.user_id);
  if (filters.from) params.set("from", filters.from);
  if (filters.to) params.set("to", filters.to);

  try {
    const json = await apiGet(apiUrl("/admin/logs") + "?" + params.toString());
    if (!json?.success) {
      showToast("Could not load logs.", { type: "error" });
      return;
    }
    renderSummary(json.summary);
    populateActionFilter(json.summary);
    renderLogs(json.data);
    const p = json.pagination || {};
    totalPages = p.pages || 1;
    $("#paginationInfo").text(`Page ${p.page || 1} of ${totalPages} · ${p.total || 0} entries`);
    $("#prevPage").prop("disabled", (p.page || 1) <= 1);
    $("#nextPage").prop("disabled", (p.page || 1) >= totalPages);
  } catch (e) {
    if (e?.message?.includes("403") || e?.message?.includes("401")) {
      $("#adminContent").addClass("d-none");
      $("#accessDenied").removeClass("d-none");
    } else {
      showToast("Failed to load activity logs.", { type: "error" });
    }
  } finally {
    $("#logsLoading").addClass("d-none");
  }
}

$("#filterForm").on("submit", function (e) {
  e.preventDefault();
  filters.search = $("#filterSearch").val().trim();
  filters.action = $("#filterAction").val();
  filters.user_id = $("#filterUserId").val().trim();
  filters.from = $("#filterFrom").val();
  filters.to = $("#filterTo").val();
  loadLogs(1);
});

$("#prevPage").on("click", () => {
  if (currentPage > 1) loadLogs(currentPage - 1);
});
$("#nextPage").on("click", () => {
  if (currentPage < totalPages) loadLogs(currentPage + 1);
});
$("#refreshBtn").on("click", () => loadLogs(currentPage));

let logsLoaded = false;

$("#tab-logs").on("shown.bs.tab", () => {
  if (!logsLoaded) {
    logsLoaded = true;
    loadLogs(1);
  }
});

checkAccess().then((ok) => {
  if (ok) initApprovers();
});
