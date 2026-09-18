import { history, pagination } from "../services/state.js";
import { historyStatusClass, historyStatusText } from "../../shared/js/status.js";
import { escapeHtml } from "../../shared/js/escapeHtml.js";
import { formatDuration } from "../../shared/js/formatDuration.js";
import { openModal } from "../components/modal.js";
import { renderPager } from "../../shared/js/listQuery.js";

export function renderHistory() {
  const $list = $("#historyList").empty();

  history.forEach((item) => {
    const dateBadge = item.request_date ? item.request_date.slice(5) : "—";
    const durationLabel =
      item.duration_label ||
      formatDuration(item.duration, item.duration_minutes);
    const $row = $(`
      <div class="history-item" data-id="${escapeHtml(item.id)}" role="listitem" tabindex="0">
        <div class="history-left">
          <div class="history-date-badge">${escapeHtml(dateBadge)}</div>
          <div>
            <div class="history-title">${escapeHtml(item.group_name || "—")}</div>
            <div class="history-sub">${escapeHtml(item.request_date || "")} · ${escapeHtml(durationLabel)} · ${escapeHtml(item.location_name || "")}</div>
          </div>
        </div>
        <span class="status-badge ${historyStatusClass(item)}">${escapeHtml(historyStatusText(item))}</span>
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
    { emptyLabel: "No entries in this date range" },
  );
}
