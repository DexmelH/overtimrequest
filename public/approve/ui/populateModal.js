import { overtime } from "../services/state.js";
import { renderManagers } from "../components/approvers.js";
import { modal } from "../components/modal.js";
import { formatDateShort } from "../../shared/js/status.js";

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

  renderManagers(request.approver_details || []);
  $("#approvalRemarks").val("");

  const alreadyActed = !!request.is_approved;
  $("#approvalActions").toggleClass("d-none", alreadyActed);
  $("#approvalRemarksWrap").toggleClass("d-none", alreadyActed);

  modal?.show();
}
