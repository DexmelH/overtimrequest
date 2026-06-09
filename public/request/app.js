import { fetchHistory } from "./api/fetchHistory.js";
import { fetchLocations } from "./api/fetchLocations.js";
import { fetchGroups } from "./api/fetchGroups.js";
import { fetchProjects } from "./api/fetchProjects.js";
import { fetchItems } from "./api/fetchItems.js";
import { fetchJobs } from "./api/fetchJobs.js";
import { fetchWorks } from "./api/fetchWorks.js";
import { addOvertimeRequest } from "./api/addOvertime.js";
import { renderHistory } from "./ui/renderHistory.js";
import { setFilter, setSearchQuery } from "./services/state.js";
import { resetDependentFields, enableField } from "./ui/selectCascade.js";
import { showToast } from "../shared/js/toast.js";
import { cancelOvertimeRequest } from "./api/cancelOvertime.js";
import { getCurrentRequestId } from "./components/modal.js";
import { confirmAction } from "../shared/js/confirm.js";

function setDefaultDate() {
  const today = new Date().toISOString().slice(0, 10);
  $("#date").val(today);
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

// Cascade selects
$("#group").on("change", function () {
  resetDependentFields("project");
  if ($(this).val()) {
    enableField("project");
    fetchProjects().catch(() => {});
  }
});

$("#project").on("change", function () {
  resetDependentFields("item");
  if ($(this).val()) {
    enableField("item");
    fetchItems().catch(() => {});
  }
});

$("#item").on("change", function () {
  resetDependentFields("jobdesc");
  if ($(this).val()) {
    enableField("jobdesc");
    fetchJobs().catch(() => {});
  }
});

$("#jobdesc").on("change", function () {
  resetDependentFields("work");
  if ($(this).val()) {
    enableField("work");
    fetchWorks().catch(() => {});
  }
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
    project: $("#project").val(),
    item: $("#item").val(),
    jobdesc: $("#jobdesc").val(),
    work: $("#work").val(),
    hours: parseFloat($("#hours").val()),
    remarks: $("#remarks").val().trim(),
  };

  if (
    !payload.date ||
    !payload.group ||
    !payload.location ||
    !payload.project ||
    !payload.item ||
    !payload.jobdesc ||
    !payload.work ||
    !payload.hours ||
    payload.hours <= 0
  ) {
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
    resetDependentFields("project");
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
  resetDependentFields("project");
});

// Init
setDefaultDate();
fetchHistory().catch(() => {});
fetchLocations().catch(() => {});
fetchGroups().catch(() => {});
