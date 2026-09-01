import { history, pagination } from "../services/state.js";
import { statusClass, statusText } from "../../shared/js/status.js";
import { escapeHtml } from "../../shared/js/escapeHtml.js";
import { openModal } from "../components/modal.js";
import { renderPager } from "../../shared/js/listQuery.js";

export function renderHistory() {
  const $list = $("#historyList").empty();

  history.forEach((item) => {
    const dateBadge = item.request_date ? item.request_date.slice(5) : "—";
    let statusLabel = statusText(item.status);
    let statusCls = statusClass(item.status);
    if ((item.status == 1 || item.status === "1") && item.is_on_behalf) {
      statusLabel = "Auto-approved";
      statusCls = "status-auto-approved";
    } else if (
      (item.status == 0 || item.status === "0") &&
      !(item.approver_details || []).some(
        (m) => m.status !== null && m.status !== undefined && m.status !== "",
      )
    ) {
      statusLabel = "Auto-rejected";
      statusCls = "status-auto-rejected";
    }

    const badges = [
      `<span class="status-badge ${statusCls}">${escapeHtml(statusLabel)}</span>`,
    ];
    if (item.is_on_behalf) {
      badges.push('<span class="status-badge status-onbehalf">On behalf</span>');
    }
    if (item.is_follow_up) {
      badges.push('<span class="status-badge status-followup">Follow-up</span>');
    }

    const $row = $(`
      <div class="history-item" data-id="${escapeHtml(item.id)}" role="listitem" tabindex="0">
        <div class="history-left">
          <div class="history-date-badge">${escapeHtml(dateBadge)}</div>
          <div>
            <div class="history-title">${escapeHtml(item.group_name || "—")}</div>
            <div class="history-sub">${escapeHtml(item.request_date || "")} · ${escapeHtml(item.duration ?? 0)} hrs · ${escapeHtml(item.location_name || "")}</div>
          </div>
        </div>
        <div class="history-badges">${badges.join("")}</div>
      </div>
    `);

    $row.on("click keypress", function (e) {
      if (
        e.type === "click" ||
        (e.type === "keypress" && (e.key === "Enter" || e.key === " "))
      ) {
        e.preventDefault();
        openModal(item.id);
      }
    });

    $list.append($row);
  });

  if (history.length === 0) {
    $list.append(`
      <div class="ot-empty">
        <i class="bi bi-inbox"></i>
        <p class="mb-0">No requests in this date range.</p>
      </div>
    `);
  }

  renderPager(
    {
      info: "#historyPagerInfo",
      prev: "#historyPrevPage",
      next: "#historyNextPage",
    },
    pagination,
    { emptyLabel: "No requests in this date range" },
  );
}
