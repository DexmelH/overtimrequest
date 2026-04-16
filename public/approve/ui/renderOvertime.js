import { overtime } from "../services/state.js";

export function renderOvertime() {
  const $tbody = $("#requestsTable tbody").empty();
  overtime.forEach((req) => {
    const approver = req.approver_details || [];
    const approvedCount = (req.approver_details || []).filter(
      (a) => a.status === 1,
    ).length;
    const approverLength = approver.length;

    const $tr = $(`
      <tr data-id="${req.id}" class="clickable-row">
        <td class="nowrap"><strong>${req.group_name}</strong></td>
        <td>
          <div><strong>${req.employee_name}</strong></div>
          <div class="small-muted">ID: ${req.employee_id}</div>
        </td>
        <td>${req.request_date}</td>
        <td>${req.duration}</td>
        <td class="text-truncate" style="max-width:220px">${req.remarks}</td>
        <td>
          <div class="small-muted">${approvedCount} / ${approverLength} approved</div>
          <div class="mt-1 approval-list"></div>
        </td>
      </tr>
    `);

    const $approvalList = $tr.find(".approval-list");
    approver.forEach((m) => {
      const isApproved = approver.status === 1;
      const badge = $("<span>")
        .addClass("badge")
        .addClass(
          isApproved ? "bg-success text-white" : "bg-secondary text-white",
        )
        .text(m.approver_id)
        .attr("title", m.approver_id);
      $approvalList.append(badge);
    });

    $tbody.append($tr);
  });
}
