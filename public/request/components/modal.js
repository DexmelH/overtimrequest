import { history } from "../services/state.js";
import { historyStatusClass, historyStatusText, isPending } from "../../shared/js/status.js";
import { renderManagers } from "../../shared/js/approvers.js";
import { formatDuration } from "../../shared/js/formatDuration.js";

const modalEl = document.getElementById("detailModal");
let bsModal = null;
let currentRequestId = null;

function getModal() {
  if (!bsModal && modalEl) {
    bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  }
  return bsModal;
}

function renderProjects(selector, projects, fallback, durationLabel) {
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
        $("<strong>").text(
          durationLabel || formatDuration(project.hours, 0),
        ),
      )
      .appendTo($target);
  });
}

function formatDimRevision(item) {
  const dim = item.work_2d3d ? String(item.work_2d3d) : "";
  const rev = Number(item.revision) === 1 ? "Revision" : "";
  if (!dim && !rev) return "—";
  if (dim && rev) return `${dim} · ${rev}`;
  return dim || rev;
}

export function openModal(id) {
  const item = history.find((h) => String(h.id) === String(id));
  if (!item) return;

  currentRequestId = item.id;
  const durationLabel =
    item.duration_label ||
    formatDuration(item.duration, item.duration_minutes);

  $("#m_date").text(item.request_date || "—");
  $("#m_group").text(item.group_name || "—");
  $("#m_location").text(item.location_name || "—");
  renderProjects("#m_projects", item.projects, item.project_name, durationLabel);
  $("#m_item").text(item.item_name || "—");
  $("#m_job").text(item.job_name || "—");
  $("#m_tow").text(item.tow_name || "—");
  $("#m_dim").text(formatDimRevision(item));
  $("#m_hours").text(durationLabel);
  $("#m_remarks").text(item.remarks || "—");
  $("#m_statusBadge").html(
    `<span class="status-badge ${historyStatusClass(item)}">${historyStatusText(item)}</span>`,
  );
  renderManagers(item.approver_details || []);

  if (isPending(item.status)) {
    $("#btnCancelRequest").removeClass("d-none");
  } else {
    $("#btnCancelRequest").addClass("d-none");
  }

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
  openModal(currentRequestId);
}

export function getCurrentRequestId() {
  return currentRequestId;
}
