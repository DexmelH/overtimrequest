import { history } from "../services/state.js";
import { statusClass, statusText, isPending } from "../../shared/js/status.js";
import { renderManagers } from "../../approve/components/approvers.js";

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

export function openModal(id) {
  const item = history.find((h) => String(h.id) === String(id));
  if (!item) return;

  currentRequestId = item.id;

  $("#m_date").text(item.request_date || "—");
  $("#m_group").text(item.group_name || "—");
  $("#m_location").text(item.location_name || "—");
  renderProjects("#m_projects", item.projects, item.project_name);
  $("#m_hours").text(`${item.duration ?? "—"} hrs`);
  $("#m_remarks").text(item.remarks || "—");
  $("#m_statusBadge").html(
    `<span class="status-badge ${statusClass(item.status)}">${statusText(item.status)}</span>`,
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

export function getCurrentRequestId() {
  return currentRequestId;
}
