import {
  statusClass,
  badgeText,
  formatDateISO,
} from "../../shared/js/status.js";

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

export function renderManagers(managers) {
  const $container = $("#managersList").empty();

  if (!managers?.length) {
    $container.append(
      '<p class="ot-muted small mb-0">No approvers assigned.</p>',
    );
    updateApprovalCount([]);
    return;
  }

  managers.forEach((m) => {
    const name = escapeHtml(m.surname || "Approver " + m.approver_id);
    const role = escapeHtml(m.role || "—");
    const remarks =
      m.status != null && m.remarks
        ? `<div class="mgr-remarks ot-muted">${escapeHtml(m.remarks)}</div>`
        : "";
    const $row = $(`
      <div class="approver-row">
        <div class="avatar small">${escapeHtml(m.approver_id)}</div>
        <div class="flex-grow-1 min-w-0">
          <div class="mgr-name">${name}</div>
          <div class="mgr-role">${role}</div>
          ${remarks}
        </div>
        <div class="text-end">
          <span class="status-badge ${statusClass(m.status)}">${badgeText(m.status)}</span>
          <div class="ot-muted" style="font-size:0.72rem;margin-top:0.2rem">
            ${m.status != null && m.date_accepted ? formatDateISO(m.date_accepted) : "No action yet"}
          </div>
        </div>
      </div>
    `);
    $container.append($row);
  });

  updateApprovalCount(managers);
}

function updateApprovalCount(managers) {
  const total = managers.length;
  const approved = managers.filter((m) => m.status == 1).length;
  $("#approvalCount").text(`${approved} / ${total} approved`);
}
