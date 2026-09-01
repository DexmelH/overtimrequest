import { history, setHistory } from "../services/state.js";
import { isPending } from "../../shared/js/status.js";
import {
  renderHistoryStatusBadges,
  renderRequestTimeline,
} from "../../shared/js/requestTimeline.js";
import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";
import { showToast } from "../../shared/js/toast.js";

const modalEl = document.getElementById("detailModal");
let bsModal = null;
let currentRequestId = null;

function getModal() {
  if (!bsModal && modalEl) {
    bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  }
  return bsModal;
}

function renderProjects(selector, projects, fallback) {
  const $target = $(selector).empty();
  if (!Array.isArray(projects) || projects.length === 0) {
    $target.text(fallback || "—");
    return;
  }

  projects.forEach((project) => {
    $("<div>")
      .addClass("project-detail-row")
      .append(
        $("<span>").text(project.project_name || "—"),
        $("<strong>").text(`${project.hours ?? 0} hrs`),
      )
      .appendTo($target);
  });
}

function updateApprovalCount(managers) {
  const list = Array.isArray(managers) ? managers : [];
  const total = list.length;
  const approved = list.filter((m) => m.status == 1).length;
  $("#approvalCount").text(`${approved} / ${total} approved`);
}

function populateModal(item) {
  currentRequestId = item.id;

  $("#m_date").text(item.request_date || "—");
  $("#m_group").text(item.group_name || "—");
  $("#m_location").text(item.location_name || "—");
  renderProjects("#m_projects", item.projects, item.project_name);
  $("#m_hours").text(`${item.duration ?? "—"} hrs`);
  $("#m_remarks").text(item.remarks || "—");
  renderHistoryStatusBadges(item);
  renderRequestTimeline(item);
  updateApprovalCount(item.approver_details || []);

  if (isPending(item.status)) {
    $("#btnCancelRequest").removeClass("d-none");
  } else {
    $("#btnCancelRequest").addClass("d-none");
  }
}

/**
 * Load one owned request by id when it is not on the current history page
 * (e.g. origin / follow-up outside the date window).
 */
async function fetchHistoryItem(id) {
  const json = await apiGet(apiUrl("/overtimehistory") + `?id=${encodeURIComponent(id)}`);
  const rows = Array.isArray(json?.data) ? json.data : [];
  return rows[0] || null;
}

function cacheHistoryItem(item) {
  if (!item?.id) return;
  const next = history.slice();
  const index = next.findIndex((h) => String(h.id) === String(item.id));
  if (index >= 0) {
    next[index] = item;
  } else {
    next.unshift(item);
  }
  setHistory(next);
}

export async function openModal(id) {
  let item = history.find((h) => String(h.id) === String(id));
  if (!item) {
    try {
      item = await fetchHistoryItem(id);
      if (item) {
        cacheHistoryItem(item);
      }
    } catch (error) {
      console.error("Failed to load request details:", error);
      showToast("Unable to open that request.", { type: "error" });
      return;
    }
  }
  if (!item) {
    showToast("That request was not found in your history.", { type: "warning" });
    return;
  }

  populateModal(item);
  getModal()?.show();
}

export function closeModal() {
  currentRequestId = null;
  getModal()?.hide();
}

export function isModalOpen() {
  return !!modalEl && modalEl.classList.contains("show");
}

/**
 * Re-read the open request from state so a background refresh is reflected in
 * the modal too. Safe to call at any time: this modal is read-only apart from
 * the cancel button, so there is no user input to overwrite.
 */
export function refreshOpenModal() {
  if (currentRequestId === null || !isModalOpen()) return;
  const item = history.find((h) => String(h.id) === String(currentRequestId));
  if (item) {
    populateModal(item);
  }
}

export function getCurrentRequestId() {
  return currentRequestId;
}

$(document).on("click", "#requestTimeline .ot-timeline-link", function (e) {
  e.preventDefault();
  const targetId = $(this).data("request-id");
  if (!targetId) return;
  openModal(targetId).catch(() => {});
});
