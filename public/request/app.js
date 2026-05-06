import { fetchHistory } from "./api/fetchHistory.js";
import { fetchLocations } from "./api/fetchLocations.js";
import { fetchGroups } from "./api/fetchGroups.js";
import { fetchProjects } from "./api/fetchProjects.js";
import { fetchItems } from "./api/fetchItems.js";
import { fetchJobs } from "./api/fetchJobs.js";
import { fetchWorks } from "./api/fetchWorks.js";
import { addOvertimeRequest } from "./api/addOvertime.js";
import { openModal, closeModal } from "./components/modal.js";
import { renderHistory } from "./ui/renderHistory.js";
import { setFilter } from "./services/state.js";

// Close handlers
$("#modalClose, #modalCloseBtn, #modalBackdrop").on("click", function () {
  closeModal();
});

$(document).on("keydown", function (e) {
  if (e.key === "Escape" && $("#detailModal").attr("aria-hidden") === "false") {
    closeModal();
  }
});

$("#group").on("change", function () {
  $("#project").disabled = false;
  fetchProjects().catch(() => {});
});

$("#project").on("change", function () {
  $("#item").disabled = false;
  fetchItems().catch(() => {});
});

$("#item").on("change", function () {
  $("#jobdesc").disabled = false;
  fetchJobs().catch(() => {});
});

$("#jobdesc").on("change", function () {
  fetchWorks().catch(() => {});
});

/// DATA
let history = [];

function onVisibilityChange() {
  if (document.visibilityState === "visible") {
    fetchHistory().catch(() => {});
  }
}

$(window).on("focus", fetchHistory);
// document.addEventListener("visibilitychange", onVisibilityChange);
// $(window).on("pageshow", function (event) {
//   fetchHistory().catch(() => {});
// });

fetchHistory().catch(() => {});
fetchLocations().catch(() => {});
fetchGroups().catch(() => {});

// Form submit
$("#overtimeForm").on("submit", function (e) {
  e.preventDefault();

  const date = $("#date").val();
  const group = $("#group").val();
  const location = $("#location").val().trim();
  const project = $("#project").val().trim();
  const item = $("#item").val().trim();
  const jobdesc = $("#jobdesc").val().trim();
  const work = $("#work").val().trim();
  const hours = parseFloat($("#hours").val());
  const remarks = $("#remarks").val().trim();

  if (
    !date ||
    !group ||
    !location ||
    !project ||
    !item ||
    !jobdesc ||
    !work ||
    !hours ||
    hours <= 0
  ) {
    alert("Please fill all required fields with valid values.");
    return;
  }

  const newReq = {
    date,
    group,
    location,
    project,
    item,
    jobdesc,
    work,
    hours,
    remarks,
  };

  addOvertimeRequest(newReq).catch(() => {});

  $("#overtimeForm")[0].reset();
  $("#hours").val(1);
  $("#date").val(date);
});

// Reset button
$("#resetBtn").on("click", function () {
  $("#overtimeForm")[0].reset();
  $("#hours").val(0);
});

// Filters
$(".filter-btn").on("click", function () {
  $(".filter-btn").removeClass("active");
  $(this).addClass("active");
  const f = $(this).data("filter");
  setFilter(f);
  renderHistory();
});
