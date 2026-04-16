import { fetchRequest } from "./api/fetchRequest.js";
import { overtime } from "./services/state.js";

fetchRequest().catch(() => {});

function showDetails(requestId) {
  const req = overtime.find((r) => r.id === requestId);
  if (!req) return;

  $("#requestDetails").html(`
    <div class="d-flex justify-content-between">
      <div>
        <div class="small-muted">Employee: ${req.employee_name} — ID: ${req.employee_id}</div>
      </div>
      <div class="text-end small-muted">
        <div>Date: ${req.request_date}</div>
        <div>Hours: ${req.duration}</div>
      </div>
    </div>
  `);

  $("#otInfo").html(`
    <p><strong>Reason</strong></p>
    <p class="mb-0">${req.remarks}</p>
  `);

  const approvers = req.approver_details || [];
  const $managers = $("#managersList").empty();
  approvers.forEach((a) => {
    const approved = a.status === 1;
    const label = a.approver_id;
    const title = `Approver ${a.approver_id} — Status: ${a.status === 1 ? "Approved" : a.status === 2 ? "Rejected" : "Pending"}`;
    const badge = $("<span>")
      .addClass("badge")
      .addClass(approved ? "bg-success text-white" : "bg-secondary text-white")
      .text(label)
      .attr("title", title);
    $managers.append(badge);
  });

  const modal = new bootstrap.Modal(document.getElementById("detailsModal"));
  modal.show();
}

$("#requestsTable tbody").on("click", "tr.clickable-row", function (e) {
  // If the click originated from a control (button, link, input), ignore here.
  const $target = $(e.target);
  if (
    $target.is("button") ||
    $target.closest("button").length ||
    $target.is("a") ||
    $target.closest("a").length
  ) {
    return;
  }

  const id = $(this).data("id");
  showDetails(id);
});
