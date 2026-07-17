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

function setDefaultDate() {
  setDefaultRequestDate();
}

function setSubmitLoading(loading) {
  const $btn = $("#submitBtn");
  if (loading) {
    $btn
      .prop("disabled", true)
      .html('<span class="ot-spinner"></span> Submitting...');
  } else {
    $btn
      .prop("disabled", false)
      .html('<i class="bi bi-send"></i> Submit Request');
  }
}

$("#group").on("change", function () {
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
  $("#overtimeForm")[0].reset();
  setDefaultDate();
  projectAllocations.reset();
});

$("#date").on("change input", function () {
  validateDateInput(true);
});

// Init
initShell();
applyDateConstraints();
setDefaultDate();
loadBlockedHolidays().catch(() => {});
fetchHistory().catch(() => {});
fetchLocations().catch(() => {});
fetchGroups().catch(() => {});
