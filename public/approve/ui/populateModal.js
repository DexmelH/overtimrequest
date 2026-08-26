import { overtime } from "../services/state.js";
import { renderManagers } from "../../shared/js/approvers.js";
import { modal } from "../components/modal.js";
import {
  approverActionClass,
  requestStatusClass,
  formatDateShort,
} from "../../shared/js/status.js";

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

function renderProjects(projects, fallback) {
  const $target = $("#rd-projects").empty();
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

export function populateModal(requestId) {
  const request = overtime.find((r) => String(r.id) === String(requestId));
  if (!request) return;

  $("#rd-requestID").val(request.id);
  $("#rd-avatar").text(getInitials(request.employee_name));
  $("#rd-employee").text(
    `${request.employee_name || "—"} (${request.employee_id || "—"})`,
  );
  $("#rd-meta").text(`Request #${request.id}`);
  $("#rd-date").text(formatDateShort(request.request_date));
  $("#rd-hours").text(`${request.duration ?? "—"} hrs`);

  $("#rd-group").text(request.group_name || "—");
  $("#rd-location").text(request.location_name || "—");
  renderProjects(request.projects, request.project_name);
  $("#rd-remarks").text(request.remarks || "—");

  $("#rd-status")
    .attr("class", `status-badge ${requestStatusClass(request.status_code)}`)
    .text(request.status_label || "Pending");

  // The approver's own action is a separate badge; it is absent once a request
  // closes without them acting.
  $("#rd-action")
    .attr(
      "class",
      `status-badge ${approverActionClass(request.action_code)}${
        request.action_code ? "" : " d-none"
      }`,
    )
    .text(request.action_label || "");

  renderManagers(request.approver_details || []);
  $("#approvalRemarks").val("");

  // Decisions stay editable until the request is finalized, so acting again is
  // a reversal rather than a first decision.
  const canAct = !!request.can_change;
  const hasDecision = request.my_decision !== null && request.my_decision !== undefined;
  $("#approvalActions").toggleClass("d-none", !canAct);
  $("#approvalRemarksWrap").toggleClass("d-none", !canAct);
  $("#btnApproveRequest").html(
    hasDecision
      ? '<i class="bi bi-check-circle"></i> Change to Approve'
      : '<i class="bi bi-check-circle"></i> Approve',
  );
  $("#btnRejectRequest").html(
    hasDecision
      ? '<i class="bi bi-x-circle"></i> Change to Reject'
      : '<i class="bi bi-x-circle"></i> Reject',
  );
  $("#btnApproveRequest").prop("disabled", request.my_decision === 1);
  $("#btnRejectRequest").prop("disabled", request.my_decision === 0);

  $("#followUpActions").toggleClass(
    "d-none",
    request.status_code !== "auto_rejected",
  );

  modal?.show();
}
