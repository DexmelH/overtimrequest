import { apiUrl } from "../shared/js/api.js";
import { apiGet } from "../shared/js/http.js";
import { fetchRequest } from "./api/fetchRequest.js";
import {
  approveOvertimeRequest,
  approveOvertimeRequestsBulk,
} from "./api/approveRequest.js";
import { renderTable, syncBulkBar } from "./ui/renderOvertime.js";
import {
  clearSelection,
  getSelectedCount,
  getSelectedIds,
  selectPendingInFilter,
  setFilter,
} from "./services/state.js";
import { showToast } from "../shared/js/toast.js";
import { confirmAction } from "../shared/js/confirm.js";
import { initShell } from "../shared/js/shell.js";
import { initOnBehalf } from "./onBehalf.js";

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
    showToast("Remarks are required when rejecting a request.", {
      type: "warning",
    });
    $("#approvalRemarks").trigger("focus");
    return;
  }

  const confirmed = await confirmAction({
    title: isApprove ? "Approve this request?" : "Reject this request?",
    message: isApprove
      ? "Your decision will be recorded. Final status is set at cutoff (or immediately if you are Level 4)."
      : "Your rejection will be recorded. Final status is set at cutoff (or immediately if you are Level 4).",
    confirmText: isApprove ? "Approve" : "Reject",
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
  $("#btnBulkApprove, #btnBulkReject, #btnBulkClear, #btnBulkSelectPending").prop(
    "disabled",
    true,
  );

  showToast(
    `Updating ${count} request(s)…`,
    { type: "default", duration: 2000 },
  );

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
        isApprove
          ? `Approved ${ok} request(s).`
          : `Rejected ${ok} request(s).`,
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

  const confirmed = await confirmAction({
    title: `Approve ${count} request(s)?`,
    message:
      "Each selected pending request will be approved with your decision. Final status still follows Level 4 / cutoff rules.",
    confirmText: "Approve all",
    cancelText: "Go back",
    variant: "success",
    icon: "bi-check-circle-fill",
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
  getBulkRejectModal()?.show();
  setTimeout(() => $("#bulkRejectRemarks").trigger("focus"), 200);
}

async function confirmBulkReject() {
  if (actionInProgress) return;

  const remarks = $("#bulkRejectRemarks").val().trim();
  if (!remarks) {
    showToast("Remarks are required when rejecting requests.", {
      type: "warning",
    });
    $("#bulkRejectRemarks").trigger("focus");
    return;
  }

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

$("#btnApproveRequest").on("click", () => handleApproval(1));
$("#btnRejectRequest").on("click", () => handleApproval(0));

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

let lastListRefreshAt = 0;
const LIST_REFRESH_MIN_MS = 5000;

function refreshListOnRevisit() {
  if (actionInProgress) return;
  const now = Date.now();
  if (now - lastListRefreshAt < LIST_REFRESH_MIN_MS) {
    return;
  }
  lastListRefreshAt = now;
  fetchRequest().catch(() => {});
}

document.addEventListener("visibilitychange", () => {
  if (document.visibilityState === "visible") {
    refreshListOnRevisit();
  }
});

$(".ot-filter-btn").on("click", function () {
  $(".ot-filter-btn").removeClass("active");
  $(this).addClass("active");
  setFilter($(this).data("filter"));
  renderTable();
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
  lastListRefreshAt = Date.now();
  fetchRequest().catch(() => {});
}

bootstrapApprovePage();
