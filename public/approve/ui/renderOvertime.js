import { overtime } from "../services/state.js";
import { populateModal } from "./populateModal.js";

// export function renderOvertime() {
//   const $tbody = $("#requestsTable tbody").empty();
//   overtime.forEach((req) => {
//     const approver = req.approver_details || [];
//     const approvedCount = (req.approver_details || []).filter(
//       (a) => a.status === 1,
//     ).length;
//     const approverLength = approver.length;

//     const $tr = $(`
//       <tr data-id="${req.id}" class="clickable-row">
//         <td class="nowrap"><strong>${req.group_name}</strong></td>
//         <td>
//           <div><strong>${req.employee_name}</strong></div>
//           <div class="small-muted">ID: ${req.employee_id}</div>
//         </td>
//         <td>${req.request_date}</td>
//         <td>${req.duration}</td>
//         <td>
//           <div class="small-muted">${approvedCount} / ${approverLength} approved</div>
//           <div class="mt-1 approval-list"></div>
//         </td>
//       </tr>
//     `);

//     const $approvalList = $tr.find(".approval-list");
//     approver.forEach((m) => {
//       console.log(m.status);
//       const badge = $("<span>")
//         .addClass("badge")
//         .addClass(
//           m.status === 1
//             ? "bg-success text-white"
//             : m.status === 0
//               ? "bg-danger text-white"
//               : "bg-secondary text-white",
//         )
//         .text(m.approver_id)
//         .attr(
//           "title",
//           `Approver ${m.approver_id} — Status: ${m.status === 1 ? "Approved" : m.status === 0 ? "Rejected" : "Pending"}`,
//         );
//       $approvalList.append(badge);
//     });

//     $tbody.append($tr);
//   });
// }

export function renderTable(requests) {
  const $tbody = $("#requestsTable tbody");
  $tbody.empty();

  (requests || []).forEach((req) => {
    const $tr = $("<tr>").attr("tabindex", 0).attr("data-request-id", req.id);

    // Group cell
    const $tdGroup = $("<td>").text(req.group_name);

    // Employee cell (avatar + name + meta)
    const $tdEmployee = $("<td>").addClass("employee");
    const $av = $("<div>").addClass("avatar").text(req.employee_id);
    const $empWrap = $("<div>");
    const $name = $("<div>").text(req.employee_name);
    const $meta = $("<div>")
      .addClass("cell-meta")
      .text(req.project_name || "");
    $empWrap.append($name, $meta);
    $tdEmployee.append($av, $empWrap);

    // Date cell
    const dateText = req.request_date
      ? new Date(req.request_date).toLocaleDateString()
      : "";
    const $tdDate = $("<td>").text(dateText);

    // Hours cell
    const $tdHours = $("<td>").text(req.duration);

    // Approvals cell
    const $tdApprovals = $("<td>");
    const $badge = $("<span>").addClass("approval-badge");
    const approverDetails = req.approver_details || [];
    const approvedCount = approverDetails.filter((m) => m.status === 1).length;
    const totalCount = approverDetails.length;
    $badge.text(`${approvedCount} / ${totalCount}`);
    $tdApprovals.append($badge);

    // assemble row
    $tr.append($tdGroup, $tdEmployee, $tdDate, $tdHours, $tdApprovals);

    // click handler to open modal (calls your populateModal function)
    $tr.on("click", () => populateModal(req.id));

    $tbody.append($tr);
  });
}
