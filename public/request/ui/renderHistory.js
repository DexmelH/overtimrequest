import { history } from "../services/state.js";
import { statusClass, statusText } from "../components/status.js";
import { openModal } from "../components/modal.js";
import { filter } from "../services/state.js";

export function renderHistory() {
  const $list = $("#historyList").empty();
  const items = history;
  const filterText =
    filter === "approved"
      ? 1
      : filter === "denied"
        ? 0
        : filter === "pending"
          ? null
          : "all";

  items.forEach((item) => {
    if (filterText !== "all" && item.status != filterText) return;

    const $row = $(`
        <div class="history-item" data-id="${item.id}" role="listitem" tabindex="0">
          <div class="history-left">
            <div class="dot">${item.request_date ? item.request_date.slice(5) : ""}</div>
            <div>
              <div class="history-meta">${item.group_name} <span style="color:var(--muted); font-weight:600; font-size:12px;"> — ${item.project_name}</span></div>
              <div class="history-sub">${item.request_date} · ${item.duration} hrs · ${item.location_name}</div>
            </div>
          </div>
          <div>
            <div class="status-badge ${statusClass(item.status)}">${statusText(item.status)}</div>
          </div>
        </div>
      `);

    // Open modal when clicked or activated via keyboard
    $row.on("click keypress", function (e) {
      if (
        e.type === "click" ||
        (e.type === "keypress" && (e.key === "Enter" || e.key === " "))
      ) {
        openModal(item.id);
      }
    });

    $list.append($row);
  });

  if ($list.children().length === 0) {
    $list.append(
      '<div style="color:var(--muted); padding:12px;">No requests found for this filter.</div>',
    );
  }
}
