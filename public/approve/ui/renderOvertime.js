import { getFilteredOvertime } from "../services/state.js";
import { populateModal } from "./populateModal.js";
import { statusClass, formatDateShort } from "../../shared/js/status.js";

function getInitials(name) {
  if (!name) return "?";
  return name
    .split(/\s+/)
    .filter(Boolean)
    .map((part) => part[0])
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

export function renderTable() {
  const requests = getFilteredOvertime();
  const $tbody = $("#requestsTable tbody").empty();

  if (!requests.length) {
    $("#tableEmpty").removeClass("d-none");
    return;
  }
  $("#tableEmpty").addClass("d-none");

  requests.forEach((req) => {
    const approvers = req.approver_details || [];
    const approvedCount = approvers.filter((m) => m.status == 1).length;
    const rowClass = req.is_approved ? "row-acted" : "row-needs-action";

    const $tr = $("<tr>")
      .addClass(rowClass)
      .attr("tabindex", 0)
      .attr("data-request-id", req.id);

    $tr.append(
      $("<td>").html(`<strong>${req.group_name || "—"}</strong>`),
      $("<td>").html(`
        <div class="employee-cell">
          <span class="avatar">${getInitials(req.employee_name)}</span>
          <div>
            <div class="fw-semibold">${req.employee_name || "—"}</div>
            <div class="employee-meta">${req.project_name || ""}</div>
          </div>
        </div>
      `),
      $("<td>").text(formatDateShort(req.request_date)),
      $("<td>").text(`${req.duration ?? "—"} hrs`),
      $("<td>").text(req.location_name || "—"),
      $("<td>").html(
        `<span class="approval-badge">${approvedCount} / ${approvers.length}</span>`,
      ),
      $("<td>").html(
        `<span class="status-badge ${req.is_approved ? statusClass(1) : "status-pending"}">${
          req.is_approved ? "Acted" : "Needs action"
        }</span>`,
      ),
    );

    $tr.on("click keypress", function (e) {
      if (
        e.type === "click" ||
        (e.type === "keypress" && (e.key === "Enter" || e.key === " "))
      ) {
        e.preventDefault();
        populateModal(req.id);
      }
    });

    $tbody.append($tr);
  });
}
