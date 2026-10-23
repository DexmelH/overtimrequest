import { apiUrl } from "../shared/js/api.js";
import { apiGet } from "../shared/js/http.js";
import { fetchRequest, resetRequestSignature } from "./api/fetchRequest.js";
import { fetchGroupOt } from "./api/fetchGroupOt.js";
import { fetchEmployeeMonthOt } from "./api/fetchEmployeeMonthOt.js";
import { initDashboardPeriod } from "./ui/dashboardPeriod.js";
import { initGroupOt } from "./ui/groupOt.js";
import {
  approveOvertimeRequest,
  approveOvertimeRequestsBulk,
  followUpRequest,
} from "./api/approveRequest.js";
import { renderTable, syncBulkBar } from "./ui/renderOvertime.js";
import {
  clearSelection,
  getSelectedCount,
  getSelectedIds,
  getSelectedRequests,
  listQuery,
  overtime,
  pagination,
  removeEmployeeFromSelection,
  selectPendingInFilter,
  setFilter,
  setListDates,
  setListPage,
} from "./services/state.js";
import { showToast } from "../shared/js/toast.js";
import {
  closeConfirm,
  confirmAction,
  updateConfirmContent,
} from "../shared/js/confirm.js";
import { initShell } from "../shared/js/shell.js";
import { createLivePoll } from "../shared/js/livePoll.js";
import {
  bindClearInvalidOnEdit,
  clearFieldInvalid,
  focusFirstInvalid,
  requireFilled,
} from "../shared/js/formValidation.js";
import { initOnBehalf } from "./onBehalf.js";
import { escapeHtml } from "../shared/js/escapeHtml.js";
import { formatDuration, toTotalMinutes } from "../shared/js/formatDuration.js";

function yearMonthFromDate(requestDate) {
  const ym = String(requestDate || "").slice(0, 7);
  return /^\d{4}-\d{2}$/.test(ym) ? ym : "";
}

function formatMonthLabel(yearMonth) {
  if (!/^\d{4}-\d{2}$/.test(yearMonth)) return yearMonth || "";
  const d = new Date(`${yearMonth}-01T00:00:00`);
  if (Number.isNaN(d.getTime())) return yearMonth;
  return d.toLocaleString("en-US", { month: "short", year: "numeric" });
}

function labelFromMinutes(totalMinutes) {
  const total = Math.max(0, Math.floor(Number(totalMinutes) || 0));
  return formatDuration(Math.floor(total / 60), total % 60);
}

function employeeDisplayName(name, employeeId) {
  const label = String(name || "Employee").trim() || "Employee";
  const id = employeeId != null && employeeId !== "" ? String(employeeId) : "";
  return id ? `${label} (${id})` : label;
}

/**
 * One list item per employee. Multiple selected requests are summed per month.
 *
 * @param {Array<{
 *   employeeId?: number|string,
 *   name: string,
 *   months: Array<{ yearMonth: string, monthMinutes: number, afterMinutes: number }>
 * }>} rows
 * @param {{ allowRemove?: boolean }} [options]
 */
function buildOtConfirmDetailHtml(rows, { allowRemove = false } = {}) {
  if (!Array.isArray(rows) || !rows.length) return "";
  const items = rows
    .map((row) => {
      const displayName = employeeDisplayName(row.name, row.employeeId);
      const removeBtn =
        allowRemove && row.employeeId != null && row.employeeId !== ""
          ? `<button type="button" class="ot-confirm-detail-remove" data-ot-confirm-remove data-employee-id="${escapeHtml(String(row.employeeId))}">Remove</button>`
          : "";
      const monthLines = (row.months || [])
        .map((m) => {
          const monthLabel = formatMonthLabel(m.yearMonth);
          return `<span class="ot-confirm-detail-meta">Total OT (${escapeHtml(monthLabel)}): <strong>${escapeHtml(labelFromMinutes(m.monthMinutes))}</strong></span>
        <span class="ot-confirm-detail-meta">After approval: <strong>${escapeHtml(labelFromMinutes(m.afterMinutes))}</strong></span>`;
        })
        .join("");
      return `<li class="ot-confirm-detail-item">
        <div class="ot-confirm-detail-head">
          <span class="ot-confirm-detail-name">${escapeHtml(displayName)}</span>
          ${removeBtn}
        </div>
        ${monthLines}
      </li>`;
    })
    .join("");
  return `<ul class="ot-confirm-detail-list">${items}</ul>`;
}

/**
 * Group selected requests by employee (one confirm row each), summing hours
 * per calendar month for Total OT / After approval.
 *
 * @param {object[]} requests
 * @param {{ allowRemove?: boolean }} [options]
 */
async function buildApproveOtDetailHtml(
  requests,
  { allowRemove = false } = {},
) {
  const list = Array.isArray(requests) ? requests.filter(Boolean) : [];
  if (!list.length) return "";

  /**
   * @type {Map<number, {
   *   employee_id: number,
   *   name: string,
   *   months: Map<string, number>
   * }>}
   */
  const byEmployee = new Map();

  for (const req of list) {
    const employeeId = Number(req.employee_id) || 0;
    const yearMonth = yearMonthFromDate(req.request_date);
    if (employeeId <= 0 || !yearMonth) continue;

    const selectedMinutes = toTotalMinutes(req.duration, req.duration_minutes);
    let emp = byEmployee.get(employeeId);
    if (!emp) {
      emp = {
        employee_id: employeeId,
        name: String(req.employee_name || "Employee"),
        months: new Map(),
      };
      byEmployee.set(employeeId, emp);
    }
    emp.months.set(
      yearMonth,
      (emp.months.get(yearMonth) || 0) + selectedMinutes,
    );
  }

  if (!byEmployee.size) return "";

  /** @type {Array<{employee_id: number, year_month: string}>} */
  const lookupItems = [];
  for (const emp of byEmployee.values()) {
    for (const yearMonth of emp.months.keys()) {
      lookupItems.push({
        employee_id: emp.employee_id,
        year_month: yearMonth,
      });
    }
  }

  let otItems = [];
  try {
    otItems = await fetchEmployeeMonthOt(lookupItems);
  } catch (error) {
    console.error("Failed to load employee month OT:", error);
    return "";
  }

  const minutesByKey = new Map(
    (otItems || []).map((item) => [
      `${Number(item.employee_id)}|${item.year_month}`,
      Number(item.minutes) || 0,
    ]),
  );

  const rows = [...byEmployee.values()].map((emp) => {
    const months = [...emp.months.entries()]
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([yearMonth, selectedMinutes]) => {
        const monthMinutes =
          minutesByKey.get(`${emp.employee_id}|${yearMonth}`) ?? 0;
        return {
          yearMonth,
          monthMinutes,
          afterMinutes: monthMinutes + selectedMinutes,
        };
      });

    return {
      employeeId: emp.employee_id,
      name: emp.name,
      months,
    };
  });

  return buildOtConfirmDetailHtml(rows, { allowRemove });
}

let actionInProgress = false;
let bulkRejectModal = null;

function getBulkRejectModal() {
  if (!bulkRejectModal) {
    const el = document.getElementById("bulkRejectModal");
    if (el) {
      bulkRejectModal = bootstrap.Modal.getOrCreateInstance(el);
    }
  }
  return bulkRejectModal;
}

async function handleApproval(status) {
  if (actionInProgress) return;

  const requestId = $("#rd-requestID").val();
  if (!requestId) return;

  const isApprove = status === 1;
  const remarks = $("#approvalRemarks").val().trim();

  if (!isApprove && !remarks) {
    requireFilled("#approvalRemarks");
    showToast("Remarks are required when rejecting a request.", {
      type: "warning",
    });
    focusFirstInvalid("#detailsModal");
    return;
  }
  clearFieldInvalid("#approvalRemarks");

  const current = overtime.find((r) => String(r.id) === String(requestId));
  const isChange = current?.my_decision === 0 || current?.my_decision === 1;

  let detailHtml = "";
  if (isApprove && current) {
    detailHtml = await buildApproveOtDetailHtml([current], {
      allowRemove: false,
    });
  }

  const confirmed = await confirmAction({
    title: isChange
      ? `Change your decision to ${isApprove ? "approve" : "reject"}?`
      : isApprove
        ? "Approve this request?"
        : "Reject this request?",
    message: isChange
      ? "This replaces your previous decision. Final status is set at cutoff (or immediately if you are Level 4)."
      : isApprove
        ? "Your decision will be recorded. Final status is set at cutoff (or immediately if you are Level 4)."
        : "Your rejection will be recorded. Final status is set at cutoff (or immediately if you are Level 4).",
    detailHtml,
    size: detailHtml ? "lg" : "default",
    confirmText: isChange
      ? `Change to ${isApprove ? "Approve" : "Reject"}`
      : isApprove
        ? "Approve"
        : "Reject",
    cancelText: "Go back",
    variant: isApprove ? "success" : "danger",
    icon: isApprove ? "bi-check-circle-fill" : "bi-x-circle-fill",
  });
  if (!confirmed) return;

  actionInProgress = true;
  const $approve = $("#btnApproveRequest");
  const $reject = $("#btnRejectRequest");
  $approve.prop("disabled", true);
  $reject.prop("disabled", true);

  try {
    await approveOvertimeRequest(requestId, status, remarks);
    bootstrap.Modal.getInstance(
      document.getElementById("detailsModal"),
    )?.hide();
  } finally {
    actionInProgress = false;
    $approve.prop("disabled", false);
    $reject.prop("disabled", false);
  }
}

async function runBulkAction(status, remarks = "") {
  const ids = getSelectedIds();
  if (!ids.length || actionInProgress) return;

  const isApprove = status === 1;
  const count = ids.length;

  actionInProgress = true;
  $(
    "#btnBulkApprove, #btnBulkReject, #btnBulkClear, #btnBulkSelectPending",
  ).prop("disabled", true);

  showToast(`Updating ${count} request(s)…`, {
    type: "default",
    duration: 2000,
  });

  try {
    const { ok, failed } = await approveOvertimeRequestsBulk(
      ids,
      status,
      remarks,
    );

    clearSelection();
    syncBulkBar();

    if (failed === 0) {
      showToast(
        isApprove ? `Approved ${ok} request(s).` : `Rejected ${ok} request(s).`,
        { type: "success" },
      );
    } else {
      showToast(
        `${isApprove ? "Approved" : "Rejected"} ${ok}, ${failed} failed.`,
        { type: "warning" },
      );
    }
  } catch (error) {
    console.error("Bulk approval failed:", error);
    showToast("Bulk update failed. Please try again.", { type: "error" });
    await fetchRequest().catch(() => {});
  } finally {
    actionInProgress = false;
    syncBulkBar();
  }
}

async function handleBulkApprove() {
  const count = getSelectedCount();
  if (!count || actionInProgress) return;

  const detailHtml = await buildApproveOtDetailHtml(getSelectedRequests(), {
    allowRemove: true,
  });

  const confirmed = await confirmAction({
    title: `Approve ${count} request(s)?`,
    message:
      "Each selected pending request will be approved with your decision. Final status still follows Level 4 / cutoff rules.",
    detailHtml,
    size: detailHtml ? "lg" : "default",
    confirmText: "Approve all",
    cancelText: "Go back",
    variant: "success",
    icon: "bi-check-circle-fill",
    onRemoveEmployee: async (employeeId) => {
      removeEmployeeFromSelection(employeeId);
      renderTable();
      syncBulkBar();

      const remaining = getSelectedCount();
      if (remaining <= 0) {
        closeConfirm(false);
        return;
      }

      const nextDetail = await buildApproveOtDetailHtml(getSelectedRequests(), {
        allowRemove: true,
      });
      updateConfirmContent({
        title: `Approve ${remaining} request(s)?`,
        detailHtml: nextDetail,
        size: nextDetail ? "lg" : "default",
      });
    },
  });
  if (!confirmed) return;

  await runBulkAction(1, "");
}

function openBulkRejectModal() {
  const count = getSelectedCount();
  if (!count || actionInProgress) return;

  $("#bulkRejectSummary").text(
    `Shared remarks will be applied to ${count} selected request(s).`,
  );
  $("#bulkRejectRemarks").val("");
  clearFieldInvalid("#bulkRejectRemarks");
  getBulkRejectModal()?.show();
  setTimeout(() => $("#bulkRejectRemarks").trigger("focus"), 200);
}

async function confirmBulkReject() {
  if (actionInProgress) return;

  const remarks = $("#bulkRejectRemarks").val().trim();
  if (!remarks) {
    requireFilled("#bulkRejectRemarks");
    showToast("Remarks are required when rejecting requests.", {
      type: "warning",
    });
    focusFirstInvalid("#bulkRejectModal");
    return;
  }
  clearFieldInvalid("#bulkRejectRemarks");

  const count = getSelectedCount();
  const confirmed = await confirmAction({
    title: `Reject ${count} request(s)?`,
    message:
      "The same remarks will be saved on each selected decision. Final status still follows Level 4 / cutoff rules.",
    confirmText: "Reject all",
    cancelText: "Go back",
    variant: "danger",
    icon: "bi-x-circle-fill",
  });
  if (!confirmed) return;

  getBulkRejectModal()?.hide();
  await runBulkAction(0, remarks);
}

async function handleFollowUp() {
  if (actionInProgress) return;

  const requestId = $("#rd-requestID").val();
  if (!requestId) return;

  const confirmed = await confirmAction({
    title: "Re-submit this request as approved?",
    message:
      "A new approved request will be created with the same employee, date, group and projects. The auto-rejected request stays as it is.",
    confirmText: "Re-submit",
    cancelText: "Go back",
    variant: "primary",
    icon: "bi-arrow-repeat",
  });
  if (!confirmed) return;

  actionInProgress = true;
  const $followUp = $("#btnFollowUpRequest");
  $followUp.prop("disabled", true);

  try {
    const payload = await followUpRequest(requestId);
    if (payload?.success) {
      bootstrap.Modal.getInstance(
        document.getElementById("detailsModal"),
      )?.hide();
    }
  } finally {
    actionInProgress = false;
    $followUp.prop("disabled", false);
  }
}

$("#btnApproveRequest").on("click", () => handleApproval(1));
$("#btnRejectRequest").on("click", () => handleApproval(0));
$("#btnFollowUpRequest").on("click", () => handleFollowUp());

$("#btnBulkApprove").on("click", () => handleBulkApprove());
$("#btnBulkReject").on("click", () => openBulkRejectModal());
$("#btnBulkRejectConfirm").on("click", () => confirmBulkReject());

$("#btnBulkSelectPending").on("click", function () {
  selectPendingInFilter();
  renderTable();
});

$("#btnBulkClear").on("click", function () {
  clearSelection();
  renderTable();
});

$("#selectAllPending").on("change", function () {
  if (this.checked) {
    selectPendingInFilter();
  } else {
    clearSelection();
  }
  renderTable();
});

function markListUpdated() {
  const stamp = new Date().toLocaleTimeString(undefined, {
    hour: "2-digit",
    minute: "2-digit",
  });
  $("#listUpdated").text(`Updated ${stamp}`);
}

/**
 * Brings in newly submitted requests and decisions from other approvers on its
 * own. Paused while any modal is open: the details and bulk-reject modals hold
 * remarks the user is typing, which a re-render would discard.
 */
const listPoll = createLivePoll({
  interval: 15000,
  idleInterval: 60000,
  isPaused: () => actionInProgress || !!document.querySelector(".modal.show"),
  fetcher: async () => {
    const [changed] = await Promise.all([
      fetchRequest({ silent: true }),
      fetchGroupOt().catch(() => null),
    ]);
    markListUpdated();
    return changed;
  },
});

function reloadList() {
  resetRequestSignature();
  return Promise.all([
    fetchRequest().then(markListUpdated),
    fetchGroupOt().catch(() => null),
  ]).catch(() => {});
}

// Closing a modal lifts the pause above, so catch up right away instead of
// waiting out the rest of the interval.
$(document).on("hidden.bs.modal", function () {
  listPoll.refreshNow();
});

$(".ot-filter-btn").on("click", function () {
  $(".ot-filter-btn").removeClass("active");
  $(this).addClass("active");
  setFilter($(this).data("filter"));
  reloadList();
});

$("#listFrom, #listTo").on("change", function () {
  setListDates($("#listFrom").val(), $("#listTo").val());
  reloadList();
});

$("#listPrevPage").on("click", function () {
  if (listQuery.page <= 1) return;
  setListPage(listQuery.page - 1);
  reloadList();
});

$("#listNextPage").on("click", function () {
  if (pagination.pages > 0 && listQuery.page >= pagination.pages) return;
  setListPage(listQuery.page + 1);
  reloadList();
});

async function bootstrapApprovePage() {
  try {
    const json = await apiGet(apiUrl("/session"));
    if (!json?.is_approver) {
      window.location.replace("../request/");
      return;
    }
  } catch {
    window.location.replace("../request/");
    return;
  }

  initShell();
  initOnBehalf();
  initDashboardPeriod(() => {
    fetchGroupOt().catch(() => null);
  });
  initGroupOt();
  bindClearInvalidOnEdit("#detailsModal");
  bindClearInvalidOnEdit("#bulkRejectModal");
  $("#listFrom").val(listQuery.from);
  $("#listTo").val(listQuery.to);

  await Promise.all([
    fetchRequest()
      .then(markListUpdated)
      .catch(() => {}),
    fetchGroupOt().catch(() => null),
  ]);
  listPoll.start();
}

bootstrapApprovePage();
