import { fetchHistory } from "./api/fetchHistory.js";
import { fetchLocations } from "./api/fetchLocations.js";
import { fetchGroups } from "./api/fetchGroups.js";
import { addOvertimeRequest } from "./api/addOvertime.js";
import { renderHistory } from "./ui/renderHistory.js";
import { setFilter, setSearchQuery } from "./services/state.js";
import { createProjectAllocations } from "../shared/js/projectAllocations.js";
import { showToast } from "../shared/js/toast.js";
import { cancelOvertimeRequest } from "./api/cancelOvertime.js";
import { getCurrentRequestId, refreshOpenModal } from "./components/modal.js";
import { confirmAction } from "../shared/js/confirm.js";
import { initShell } from "../shared/js/shell.js";
import { apiUrl } from "../shared/js/api.js";
import { apiGet } from "../shared/js/http.js";
import { createLivePoll } from "../shared/js/livePoll.js";
import {
  applyDateConstraints,
  isAllowedRequestDate,
  loadBlockedHolidays,
  setDefaultRequestDate,
  validateDateInput,
} from "../shared/js/requestDate.js";

const projectAllocations = createProjectAllocations({
  containerId: "projectAllocations",
  addButtonId: "addProjectAllocation",
  totalId: "projectHoursTotal",
  groupSelector: "#group",
});

let requestLocked = false;
let lockApplied = false;
let actionInProgress = false;
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
  const nextLocked = !!locked;
  const nextMessage = message || requestLockMessage;

  // The lock is re-checked on every background refresh, so only touch the form
  // when the state actually flips. Re-applying it would fight the user for
  // control of the fields and the submit button.
  if (
    lockApplied &&
    nextLocked === requestLocked &&
    nextMessage === requestLockMessage
  ) {
    return;
  }

  lockApplied = true;
  requestLocked = nextLocked;
  requestLockMessage = nextMessage;

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

const LOCK_RECHECK_MS = 120000;
let lastLockCheckAt = 0;

/**
 * The cutoff lock flips at most once a day, so it does not need to ride along
 * with every history poll. Never rejects.
 */
async function maybeRefreshCutoffLock() {
  const now = Date.now();
  if (now - lastLockCheckAt < LOCK_RECHECK_MS) return;
  lastLockCheckAt = now;
  await loadRequestCutoffLock();
}

function markHistoryUpdated() {
  const stamp = new Date().toLocaleTimeString(undefined, {
    hour: "2-digit",
    minute: "2-digit",
  });
  $("#historyUpdated").text(`Updated ${stamp}`);
}

/**
 * Keeps the history list and the cutoff lock current without a manual refresh,
 * so an approver's decision (or the 3 PM lock) shows up on its own.
 */
const historyPoll = createLivePoll({
  interval: 20000,
  idleInterval: 90000,
  isPaused: () => actionInProgress,
  fetcher: async () => {
    const [changed] = await Promise.all([
      fetchHistory(),
      maybeRefreshCutoffLock(),
    ]);
    markHistoryUpdated();
    if (changed) {
      refreshOpenModal();
    }
    return changed;
  },
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
  actionInProgress = true;
  try {
    await addOvertimeRequest(payload);
    this.reset();
    setDefaultDate();
    projectAllocations.reset();
  } finally {
    actionInProgress = false;
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
  actionInProgress = true;
  try {
    await cancelOvertimeRequest(requestId);
  } finally {
    actionInProgress = false;
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
fetchLocations().catch(() => {});
fetchGroups().catch(() => {});

// Loads the history and cutoff lock, then keeps both fresh.
historyPoll.start();
historyPoll.refreshNow({ force: true });
