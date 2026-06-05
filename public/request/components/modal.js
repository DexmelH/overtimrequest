import { history } from "../services/state.js";
import { statusClass, statusText } from "../../shared/js/status.js";

const modalEl = document.getElementById("detailModal");
let bsModal = null;

function getModal() {
  if (!bsModal && modalEl) {
    bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
  }
  return bsModal;
}

export function openModal(id) {
  const item = history.find((h) => String(h.id) === String(id));
  if (!item) return;

  $("#m_date").text(item.request_date || "—");
  $("#m_group").text(item.group_name || "—");
  $("#m_location").text(item.location_name || "—");
  $("#m_project").text(item.project_name || "—");
  $("#m_item").text(item.item_name || "—");
  $("#m_jobdesc").text(item.job_desc || "—");
  $("#m_work").text(item.work || "—");
  $("#m_hours").text(`${item.duration ?? "—"} hrs`);
  $("#m_remarks").text(item.remarks || "—");
  $("#m_statusBadge").html(
    `<span class="status-badge ${statusClass(item.status)}">${statusText(item.status)}</span>`,
  );

  getModal()?.show();
}

export function closeModal() {
  getModal()?.hide();
}
