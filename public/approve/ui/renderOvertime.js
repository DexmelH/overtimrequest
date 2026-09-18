import {
  getFilteredOvertime,
  getPendingFilteredOvertime,
  getSelectedCount,
  isSelected,
  pagination,
  toggleSelected,
} from "../services/state.js";
import { populateModal } from "./populateModal.js";
import {
  approverActionClass,
  requestStatusClass,
  formatDateShort,
} from "../../shared/js/status.js";
import { escapeHtml } from "../../shared/js/escapeHtml.js";
import { renderPager } from "../../shared/js/listQuery.js";

function syncPager() {
  renderPager(
    {
      info: "#listPagerInfo",
      prev: "#listPrevPage",
      next: "#listNextPage",
    },
    pagination,
    { emptyLabel: "No requests in this date range" },
  );
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
    syncPager();
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
      $("<td>").html(`
        <div class="status-badge-stack">
          <span class="status-badge ${requestStatusClass(req.status_code)}">${escapeHtml(
            req.status_label || "Pending",
          )}</span>
          ${
            req.action_code
              ? `<span class="status-badge ${approverActionClass(
                  req.action_code,
                )}">${escapeHtml(req.action_label || "")}</span>`
              : ""
          }
          ${
            req.is_follow_up
              ? '<span class="status-badge status-followup">Follow-up</span>'
              : ""
          }
        </div>
      `),
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
  syncPager();
}
