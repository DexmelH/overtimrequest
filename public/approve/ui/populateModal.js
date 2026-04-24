import { renderManagers } from "../components/utilities.js";
import { overtime } from "../services/state.js";
import { modal } from "../components/modal.js";

export function populateModal(requestId) {
  const request = overtime.find((r) => r.id === requestId);

  $("#rd-requestID").text(`${request.id}`);

  // Top summary
  $("#rd-employee").text(`${request.employee_name} - ${request.employee_id}`);
  $("#rd-date").text(new Date(request.request_date).toLocaleDateString());
  $("#rd-hours").text(request.duration);

  // Info cards
  $("#rd-detailsGroup").text(request.group_name || "—");
  $("#rd-location").text(request.location_name || "—");
  $("#rd-project").text(request.project_name || "—");

  // Overtime info and reason
  $("#otInfo").text(request.remarks || "");
  $("#otReason").text(request.remarks || "");

  // Managers list and summary
  renderManagers(request.approver_details || []);

  const footer = request.is_approved;
  if (footer) {
    $("#detailsModal .modal-footer").addClass("d-none");
  } else {
    $("#detailsModal .modal-footer").removeClass("d-none");
  }

  modal.show();
}
