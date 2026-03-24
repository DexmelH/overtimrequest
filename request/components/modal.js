import { history } from "../services/state.js";
import { statusClass, statusText } from "./status.js";

export function openModal(id) {
  console.log(id);
  const item = history.find((h) => h.id === id);
  if (!item) return;
  $("#m_date").text(item.request_date);
  $("#m_group").text(item.group_name);
  $("#m_location").text(item.location_name);
  $("#m_project").text(item.project_name);
  $("#m_item").text(item.item_name);
  $("#m_hours").text(item.duration + " hrs");
  $("#m_jobdesc").text(item.job_desc);
  $("#m_work").text(item.work);
  $("#m_remarks").text(item.remarks || "-");
  $("#m_statusBadge").html(
    `<div class="status-badge ${statusClass(item.status)}">${statusText(item.status)}</div>`,
  );

  // show modal
  $("#detailModal").attr("aria-hidden", "false").fadeIn(120);
  // focus panel for accessibility
  $(".modal-panel").attr("tabindex", "-1").focus();
  // store current open id
  $("#detailModal").data("openId", id);
  // prevent body scroll while modal open
  $("body").css("overflow", "hidden");
}

export function closeModal() {
  $("#detailModal").attr("aria-hidden", "true").fadeOut(120);
  $("#detailModal").removeData("openId");
  $("body").css("overflow", "");
}
