import { apiUrl } from "../shared/js/api.js";
import { apiGet } from "../shared/js/http.js";
import { fetchRequest } from "./api/fetchRequest.js";
import { approveOvertimeRequest } from "./api/approveRequest.js";
import { renderTable } from "./ui/renderOvertime.js";
import { setFilter } from "./services/state.js";
import { showToast } from "../shared/js/toast.js";
import { confirmAction } from "../shared/js/confirm.js";
import { initShell } from "../shared/js/shell.js";
import { initOnBehalf } from "./onBehalf.js";

let actionInProgress = false;

async function handleApproval(status) {
  if (actionInProgress) return;

  const requestId = $("#rd-requestID").val();
  if (!requestId) return;

  const isApprove = status === 1;
  const remarks = $("#approvalRemarks").val().trim();

  if (!isApprove && !remarks) {
    showToast("Remarks are required when rejecting a request.", { type: "warning" });
    $("#approvalRemarks").trigger("focus");
    return;
  }

  const confirmed = await confirmAction({
    title: isApprove ? "Approve this request?" : "Reject this request?",
    message: isApprove
      ? "The employee will be notified once this request is finalized."
      : "The employee will be notified that their request was rejected.",
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
    bootstrap.Modal.getInstance(document.getElementById("detailsModal"))?.hide();
  } finally {
    actionInProgress = false;
    $approve.prop("disabled", false);
    $reject.prop("disabled", false);
  }
}

$("#btnApproveRequest").on("click", () => handleApproval(1));
$("#btnRejectRequest").on("click", () => handleApproval(0));

$("#refreshBtn").on("click", function () {
  const $btn = $(this);
  $btn.prop("disabled", true);
  fetchRequest()
    .then(() => showToast("List refreshed.", { type: "success", duration: 2500 }))
    .catch(() => showToast("Could not refresh requests.", { type: "error" }))
    .finally(() => $btn.prop("disabled", false));
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
  fetchRequest().catch(() => {});
}

bootstrapApprovePage();
