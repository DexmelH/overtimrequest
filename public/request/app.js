import { fetchHistory } from "./api/fetchHistory.js";
import { fetchLocations } from "./api/fetchLocations.js";
import { fetchGroups } from "./api/fetchGroups.js";
import { addOvertimeRequest } from "./api/addOvertime.js";
import { renderHistory } from "./ui/renderHistory.js";
import { setFilter, setSearchQuery } from "./services/state.js";
import { createProjectAllocations } from "./ui/projectAllocations.js";
import { showToast } from "../shared/js/toast.js";
import { cancelOvertimeRequest } from "./api/cancelOvertime.js";
import { getCurrentRequestId } from "./components/modal.js";
import { confirmAction } from "../shared/js/confirm.js";
import { initShell } from "../shared/js/shell.js";
import { apiUrl } from "../shared/js/api.js";
import { apiGet } from "../shared/js/http.js";
import {
  applyDateConstraints,
  isAllowedRequestDate,
  loadBlockedHolidays,
  setDefaultRequestDate,
  validateDateInput,
} from "./ui/requestDate.js";

const projectAllocations = createProjectAllocations({
  containerId: "projectAllocations",
  addButtonId: "addProjectAllocation",
  totalId: "projectHoursTotal",
  groupSelector: "#group",
});

let requestLocked = false;
let requestLockMessage =
  "Overtime requests are locked from 3:00 PM onwards. Please ask your approver to submit on your behalf if you still need to request overtime.";

function setDefaultDate() {
  setDefaultRequestDate();
}

function setSubmitLoading(loading) {
  const $btn = $("#submitBtn");
  if (loading) {
    $btn
      .prop("disabled", true)
      .html('<span class="ot-spinner"></span> Submitting...');
  } else if (requestLocked) {
    $btn
      .prop("disabled", true)
      .html('<i class="bi bi-lock-fill"></i> Locked after cutoff');
  } else {
    $btn
      .prop("disabled", false)
      .html('<i class="bi bi-send"></i> Submit Request');
  }
}

function applyRequestCutoffLock(locked, message) {
  requestLocked = !!locked;
  if (message) {
    requestLockMessage = message;
  }

  const $banner = $("#requestCutoffLock");
  const $form = $("#overtimeForm");
  const $fields = $form.find("input, select, textarea, button");

  if (requestLocked) {
    $("#requestCutoffLockMessage").text(requestLockMessage);
    $banner.removeClass("d-none");
    $form.addClass("is-cutoff-locked");
    $fields.prop("disabled", true);
    $("#submitBtn")
      .prop("disabled", true)
      .html('<i class="bi bi-lock-fill"></i> Locked after cutoff');
  } else {
    $banner.addClass("d-none");
    $form.removeClass("is-cutoff-locked");
    $fields.prop("disabled", false);
    setSubmitLoading(false);
  }
}

async function loadRequestCutoffLock() {
  try {
    const json = await apiGet(apiUrl("/session"));
    applyRequestCutoffLock(
      !!json?.request_locked,
      json?.request_lock_message || requestLockMessage,
    );
  } catch {
    /* keep form usable if session probe fails; server still enforces lock */
  }
}

$("#group").on("change", function () {
  if (requestLocked) return;
  projectAllocations.loadProjects().catch(() => {});
});

// History filters & search
$(".ot-filter-btn").on("click", function () {
  $(".ot-filter-btn").removeClass("active");
  $(this).addClass("active");
  setFilter($(this).data("filter"));
  renderHistory();
});

$("#historySearch").on("input", function () {
  setSearchQuery($(this).val());
  renderHistory();
});

function refreshHistoryOnRevisit() {
  fetchHistory().catch(() => {});
  loadRequestCutoffLock().catch(() => {});
}

$(window).on("focus", refreshHistoryOnRevisit);
$(window).on("pageshow", refreshHistoryOnRevisit);
document.addEventListener("visibilitychange", () => {
  if (document.visibilityState === "visible") {
    refreshHistoryOnRevisit();
  }
});

// Form submit
$("#overtimeForm").on("submit", async function (e) {
  e.preventDefault();

  if (requestLocked) {
    showToast(requestLockMessage, { type: "warning" });
    return;
  }

  const payload = {
    date: $("#date").val(),
    group: $("#group").val(),
    location: $("#location").val(),
    projects: projectAllocations.getAllocations(),
    remarks: $("#remarks").val().trim(),
  };

  if (
    !payload.date ||
    !isAllowedRequestDate(payload.date) ||
    !payload.group ||
    !payload.location ||
    !projectAllocations.isValid()
  ) {
    if (payload.date && !isAllowedRequestDate(payload.date)) {
      validateDateInput(true);
      return;
    }
    showToast("Please fill all required fields with valid values.", {
      type: "warning",
    });
    return;
  }

  setSubmitLoading(true);
  try {
    await addOvertimeRequest(payload);
    this.reset();
    setDefaultDate();
    projectAllocations.reset();
  } finally {
    setSubmitLoading(false);
  }
});

$("#btnCancelRequest").on("click", async function () {
  const requestId = getCurrentRequestId();
  if (!requestId) return;

  const confirmed = await confirmAction({
    title: "Cancel this request?",
    message: "PIC will be notified by email. This action cannot be undone.",
    confirmText: "Yes, cancel",
    cancelText: "Keep request",
    variant: "danger",
    icon: "bi-x-circle-fill",
  });
  if (!confirmed) return;
  const $btn = $(this);
  $btn.prop("disabled", true);
  try {
    await cancelOvertimeRequest(requestId);
  } finally {
    $btn.prop("disabled", false);
  }
});

$("#resetBtn").on("click", function () {
  if (requestLocked) return;
  $("#overtimeForm")[0].reset();
  setDefaultDate();
  projectAllocations.reset();
});

$("#date").on("change input", function () {
  if (requestLocked) return;
  validateDateInput(true);
});

// Init
initShell();
applyDateConstraints();
setDefaultDate();
loadBlockedHolidays().catch(() => {});
loadRequestCutoffLock().catch(() => {});
fetchHistory().catch(() => {});
fetchLocations().catch(() => {});
fetchGroups().catch(() => {});
