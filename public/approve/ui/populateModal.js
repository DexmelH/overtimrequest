import { renderManagers } from "../components/utilities.js";
import { overtime } from "../services/state.js";

export function populateModal(requestId) {
  const request = overtime.find((r) => r.id === requestId);

  if (!request) {
    const modalEl = $("#detailsModal");
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    return;
  }

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

  const footer = false;

  $("#modalFooter").prop("hidden", footer);

  // Managers list and summary
  renderManagers(request.approver_details || []);

  // Show modal (Bootstrap)
  const modalEl = $("#detailsModal");
  const modal = new bootstrap.Modal(modalEl);
  modal.show();
}
