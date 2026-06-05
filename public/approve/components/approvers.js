import {
  statusClass,
  badgeText,
  formatDateISO,
} from "../../shared/js/status.js";

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
    const $row = $(`
      <div class="approver-row">
        <div class="avatar small">${m.approver_id}</div>
        <div class="flex-grow-1 min-w-0">
          <div class="mgr-name">${m.surname || "Approver " + m.approver_id}</div>
          <div class="mgr-role">${m.role || "—"}</div>
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
