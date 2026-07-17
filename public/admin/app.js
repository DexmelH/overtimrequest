import { apiUrl } from "../shared/js/api.js";
import { apiGet } from "../shared/js/http.js";
import { showToast } from "../shared/js/toast.js";
import { initApprovers } from "./approvers.js";
import { initAdminMembers } from "./members.js";
import { initShell } from "../shared/js/shell.js";

let currentPage = 1;
let totalPages = 1;
const filters = { search: "", action: "", user_id: "", from: "", to: "" };

const ACTION_META = {
  "request.submit": {
    label: "Submitted request",
    icon: "bi-send",
    tone: "primary",
  },
  "request.submit.on_behalf": {
    label: "Submitted member request",
    icon: "bi-person-check",
    tone: "primary",
  },
  "request.cancel": {
    label: "Cancelled request",
    icon: "bi-x-circle",
    tone: "muted",
  },
  "request.approve": {
    label: "Approved request",
    icon: "bi-check-circle",
    tone: "success",
  },
  "request.reject": {
    label: "Rejected request",
    icon: "bi-slash-circle",
    tone: "danger",
  },
  "admin.approvers.save": {
    label: "Saved group approvers",
    icon: "bi-people",
    tone: "admin",
  },
  "admin.approvers.preview.add": {
    label: "Added preview approver",
    icon: "bi-person-plus",
    tone: "admin",
  },
  "admin.approvers.preview.clear": {
    label: "Cleared preview approver",
    icon: "bi-person-dash",
    tone: "muted",
  },
  "admin.members.add": {
    label: "Added admin member",
    icon: "bi-shield-plus",
    tone: "admin",
  },
  "admin.members.update": {
    label: "Updated admin member",
    icon: "bi-shield-check",
    tone: "admin",
  },
  "admin.members.remove": {
    label: "Removed admin member",
    icon: "bi-shield-x",
    tone: "danger",
  },
};

const ENTITY_META = {
  overtime_request: { label: "Overtime Request", icon: "bi-clock-history" },
  group: { label: "Group", icon: "bi-diagram-3" },
  admin_member: { label: "Admin Member", icon: "bi-shield-check" },
};

const DETAIL_LABELS = {
  group_id: "Group",
  hours: "Hours",
  request_date: "Request date",
  group: "Group",
  remarks: "Remarks",
  finalized: "Outcome",
  levels: "Approver levels",
  level: "Level",
  approver_id: "Approver",
  approver_name: "Approver",
  group_abbr: "Group",
  employee_name: "Employee",
  employee_id: "Employee ID",
  auto_approved: "Auto-approved",
  notes: "Notes",
};

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function getActionMeta(action) {
  return (
    ACTION_META[action] || {
      label: action.replace(/\./g, " · "),
      icon: "bi-activity",
      tone: "muted",
    }
  );
}

function formatAction(action) {
  return getActionMeta(action).label;
}

function humanizeKey(key) {
  return (
    DETAIL_LABELS[key] ||
    String(key)
      .replace(/_/g, " ")
      .replace(/\b\w/g, (c) => c.toUpperCase())
  );
}

function formatDetailDate(value) {
  if (!value) return "—";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatDate(iso) {
  if (!iso) return "—";
  const d = new Date(iso);
  return d.toLocaleString(undefined, {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function formatEntityHtml(entityType, entityId, details, entityLabel) {
  if (!entityType) {
    return '<span class="ot-muted">—</span>';
  }

  const meta = ENTITY_META[entityType] || {
    label: humanizeKey(entityType),
    icon: "bi-tag",
  };

  let idPart = "";
  if (entityType === "group") {
    const abbr =
      entityLabel ||
      (details && typeof details === "object" ? details.group_abbr : null);
    if (abbr) {
      idPart = `<span class="log-entity-id">${escapeHtml(abbr)}</span>`;
    } else if (entityId) {
      idPart = `<span class="log-entity-id">#${escapeHtml(entityId)}</span>`;
    }
  } else if (entityId) {
    idPart = `<span class="log-entity-id">#${escapeHtml(entityId)}</span>`;
  }

  return `<span class="log-entity"><i class="bi ${meta.icon}"></i><span>${escapeHtml(meta.label)}</span>${idPart}</span>`;
}

function formatGroupLabel(details) {
  if (!details || typeof details !== "object") return "—";
  if (details.group_abbr) return String(details.group_abbr);
  if (details.group) return String(details.group);
  if (details.group_id != null) return `#${details.group_id}`;
  return "—";
}

function formatDetailValue(key, value) {
  if (key === "hours") return `${value} hr${Number(value) === 1 ? "" : "s"}`;
  if (key === "request_date") return formatDetailDate(value);
  if (key === "group_id") return `#${value}`;
  if (key === "finalized")
    return value ? "Final decision recorded" : "Partial approval step";
  if (typeof value === "boolean") return value ? "Yes" : "No";
  if (value === null || value === undefined || value === "") return "—";
  return String(value);
}

function formatDetailsHtml(action, details) {
  if (
    !details ||
    (typeof details === "object" && !Object.keys(details).length)
  ) {
    return '<span class="log-detail-empty">No additional details</span>';
  }

  const items = [];

  switch (action) {
    case "request.submit":
    case "request.submit.on_behalf":
      if (details.employee_name) {
        items.push({ label: "Employee", value: details.employee_name });
      } else if (details.employee_id != null) {
        items.push({ label: "Employee", value: `#${details.employee_id}` });
      }
      if (details.group_id != null || details.group_abbr || details.group) {
        items.push({ label: "Group", value: formatGroupLabel(details) });
      }
      if (details.hours != null)
        items.push({
          label: "Duration",
          value: formatDetailValue("hours", details.hours),
        });
      if (details.request_date)
        items.push({
          label: "Date",
          value: formatDetailValue("request_date", details.request_date),
        });
      if (details.auto_approved) {
        items.push({
          badge: "final",
          value: "Approved automatically upon submission",
        });
      }
      break;

    case "request.cancel":
      if (details.group) items.push({ label: "Group", value: details.group });
      break;

    case "request.approve":
    case "request.reject":
      if (details.remarks)
        items.push({ label: "Remarks", value: details.remarks, full: true });
      if (details.finalized) {
        items.push({ badge: "final", value: "This was the final decision" });
      }
      break;

    case "admin.approvers.save":
      if (details.group_abbr)
        items.push({ label: "Group", value: details.group_abbr });
      if (details.level) {
        const label = details.cleared ? "Cleared level" : "Level";
        items.push({ label, value: details.level });
      }
      if (details.approver_name) {
        items.push({ label: "Approver", value: details.approver_name });
      } else if (details.approver_id) {
        items.push({
          label: "Approver",
          value: `Employee #${details.approver_id}`,
        });
      }
      if (details.levels && typeof details.levels === "object") {
        Object.entries(details.levels)
          .sort(([a], [b]) => Number(a) - Number(b))
          .forEach(([level, employeeId]) => {
            items.push({
              label: `Level ${level}`,
              value: `Employee #${employeeId}`,
            });
          });
      }
      break;

    case "admin.approvers.preview.add":
    case "admin.approvers.preview.clear":
      if (details.group_abbr)
        items.push({ label: "Group", value: details.group_abbr });
      if (details.level) items.push({ label: "Level", value: details.level });
      if (details.approver_name) {
        items.push({ label: "Approver", value: details.approver_name });
      } else if (details.approver_id) {
        items.push({
          label: "Approver",
          value: `Employee #${details.approver_id}`,
        });
      }
      break;

    default:
      if (typeof details === "object") {
        Object.entries(details).forEach(([key, value]) => {
          if (key === "group_id" && (details.group_abbr || details.group))
            return;
          if (key === "group_abbr" && details.group) return;
          if (typeof value === "object" && value !== null) {
            items.push({
              label: humanizeKey(key),
              value: JSON.stringify(value),
              full: true,
            });
          } else {
            items.push({
              label: humanizeKey(key),
              value: formatDetailValue(key, value),
            });
          }
        });
      } else {
        return `<span class="log-detail-value">${escapeHtml(String(details))}</span>`;
      }
  }

  if (!items.length) {
    return '<span class="log-detail-empty">No additional details</span>';
  }

  return `<ul class="log-detail-list">${items
    .map((item) => {
      if (item.badge === "final") {
        return `<li><span class="log-detail-badge">${escapeHtml(item.value)}</span></li>`;
      }
      const fullClass = item.full ? " log-detail-item--full" : "";
      return `<li class="log-detail-item${fullClass}"><span class="log-detail-label">${escapeHtml(item.label)}</span><span class="log-detail-value">${escapeHtml(item.value)}</span></li>`;
    })
    .join("")}</ul>`;
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
    const meta = getActionMeta(item.action);
    $wrap.append(`
      <div class="col-6 col-md-4 col-lg-2">
        <div class="ot-card summary-card summary-card--${meta.tone} h-100">
          <div class="ot-card-body py-3">
            <div class="summary-icon"><i class="bi ${meta.icon}"></i></div>
            <div class="count">${item.total}</div>
            <div class="label">${escapeHtml(meta.label)}</div>
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
    $sel.append(
      `<option value="${item.action}">${formatAction(item.action)} (${item.total})</option>`,
    );
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
    const meta = getActionMeta(row.action);

    $tbody.append(`
      <tr>
        <td class="log-col-when">
          <span class="log-time-full">${escapeHtml(formatDate(row.created_at))}</span>
        </td>
        <td class="log-col-user">
          <div class="log-user-name" title="${escapeHtml(row.user_name || "Unknown user")}">${escapeHtml(row.user_name || "Unknown user")}</div>
          <small class="ot-muted">#${row.user_id ?? "—"}</small>
        </td>
        <td class="log-col-action">
          <span class="log-action-badge log-action-badge--${meta.tone}" title="${escapeHtml(meta.label)}">
            <i class="bi ${meta.icon}"></i><span class="log-action-text">${escapeHtml(meta.label)}</span>
          </span>
        </td>
        <td class="log-col-entity">${formatEntityHtml(row.entity_type, row.entity_id, row.details, row.entity_label)}</td>
        <td class="log-col-details">${formatDetailsHtml(row.action, row.details)}</td>
        <td class="log-col-ip d-none d-xl-table-cell">${row.ip_address ? escapeHtml(row.ip_address) : '<span class="ot-muted">—</span>'}</td>
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
    $("#paginationInfo").text(
      `Page ${p.page || 1} of ${totalPages} · ${p.total || 0} entries`,
    );
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
  initShell();
  if (ok) {
    initApprovers();
    initAdminMembers();
  }
});
