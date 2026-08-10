import {
  getFilteredOvertime,
  getPendingFilteredOvertime,
  getSelectedCount,
  isSelected,
  toggleSelected,
} from "../services/state.js";
import { populateModal } from "./populateModal.js";
import { statusClass, formatDateShort } from "../../shared/js/status.js";

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

export function syncBulkBar() {
  const count = getSelectedCount();
  const $bar = $("#bulkActionBar");
  const pendingCount = getPendingFilteredOvertime().length;
  const allPendingSelected =
    pendingCount > 0 &&
    getPendingFilteredOvertime().every((req) => isSelected(req.id));

  $("#bulkSelectedCount").text(`${count} selected`);
  $("#selectAllPending").prop("checked", allPendingSelected);
  $("#selectAllPending").prop("indeterminate", count > 0 && !allPendingSelected);
  $("#btnBulkApprove, #btnBulkReject, #btnBulkClear").prop("disabled", count === 0);

  if (count > 0) {
    $bar.removeClass("d-none");
  } else {
    $bar.addClass("d-none");
  }
}

export function renderTable() {
  const requests = getFilteredOvertime();
  const $tbody = $("#requestsTable tbody").empty();

  if (!requests.length) {
    $("#tableEmpty").removeClass("d-none");
    syncBulkBar();
    return;
  }
  $("#tableEmpty").addClass("d-none");

  requests.forEach((req) => {
    const approvers = req.approver_details || [];
    const approvedCount = approvers.filter((m) => m.status == 1).length;
    const needsAction = !req.is_approved;
    const selected = needsAction && isSelected(req.id);
    const rowClass = [
      needsAction ? "row-needs-action" : "row-acted",
      selected ? "row-selected" : "",
    ]
      .filter(Boolean)
      .join(" ");

    const $tr = $("<tr>")
      .addClass(rowClass)
      .attr("tabindex", 0)
      .attr("data-request-id", req.id);

    const $checkCell = $("<td>").addClass("bulk-check-cell");
    if (needsAction) {
      const $check = $("<input>")
        .attr({
          type: "checkbox",
          class: "form-check-input bulk-row-check",
          "aria-label": `Select request ${req.id}`,
        })
        .prop("checked", selected);
      $check.on("click", (e) => e.stopPropagation());
      $check.on("change", function (e) {
        e.stopPropagation();
        toggleSelected(req.id, this.checked);
        $tr.toggleClass("row-selected", this.checked);
        syncBulkBar();
      });
      $checkCell.append($check);
    } else {
      $checkCell.append($("<span>").addClass("bulk-check-spacer").attr("aria-hidden", "true"));
    }

    $tr.append(
      $checkCell,
      $("<td>").html(`<strong>${escapeHtml(req.group_name || "—")}</strong>`),
      $("<td>").html(`
        <div class="employee-cell">
          <span class="avatar">${escapeHtml(req.employee_id)}</span>
          <div>
            <div class="fw-semibold">${escapeHtml(req.employee_name || "—")}</div>
            <div class="employee-meta">${escapeHtml(req.project_name || "")}</div>
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
        `<span class="status-badge ${needsAction ? "status-pending" : statusClass(1)}">${
          needsAction ? "Needs action" : "Acted"
        }</span>`,
      ),
    );

    $tr.on("click keypress", function (e) {
      if ($(e.target).closest(".bulk-row-check, .bulk-check-cell").length) {
        return;
      }
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

  syncBulkBar();
}
