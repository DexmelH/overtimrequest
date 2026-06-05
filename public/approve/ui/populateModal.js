import { overtime } from "../services/state.js";
import { renderManagers } from "../components/approvers.js";
import { modal } from "../components/modal.js";
import { formatDateShort } from "../../shared/js/status.js";

export function populateModal(requestId) {
  const request = overtime.find((r) => String(r.id) === String(requestId));
  if (!request) return;

  $("#rd-requestID").val(request.id);
  $("#rd-avatar").text(request.employee_id);
  $("#rd-employee").text(
    `${request.employee_name || "—"} (${request.employee_id || "—"})`,
  );
  $("#rd-meta").text(`Request #${request.id}`);
  $("#rd-date").text(formatDateShort(request.request_date));
  $("#rd-hours").text(`${request.duration ?? "—"} hrs`);

  $("#rd-group").text(request.group_name || "—");
  $("#rd-location").text(request.location_name || "—");
  $("#rd-project").text(request.project_name || "—");
  $("#rd-item").text(request.item_name || "—");
  $("#rd-job").text(request.job_desc || "—");
  $("#rd-work").text(request.work || "—");
  $("#rd-remarks").text(request.remarks || "—");

  renderManagers(request.approver_details || []);
  $("#approvalRemarks").val("");

  const alreadyActed = !!request.is_approved;
  $("#approvalActions").toggleClass("d-none", alreadyActed);
  $("#approvalRemarksWrap").toggleClass("d-none", alreadyActed);

  modal?.show();
}
