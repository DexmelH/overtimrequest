import { history, filter, searchQuery } from "../services/state.js";
import { statusClass, statusText } from "../../shared/js/status.js";
import { openModal } from "../components/modal.js";
import { fetchHistory } from "../api/fetchHistory.js";

function matchesFilter(item) {
  if (filter === "all") return true;
  if (filter === "approved") return item.status == 1;
  if (filter === "denied") return item.status == 0;
  if (filter === "pending") return item.status == null || item.status === "";
  if (filter === "cancelled") return item.status == 2;
  return true;
}

function matchesSearch(item) {
  if (!searchQuery) return true;
  const hay = [
    item.group_name,
    item.project_name,
    item.location_name,
    item.item_name,
    item.remarks,
    item.request_date,
  ]
    .filter(Boolean)
    .join(" ")
    .toLowerCase();
  return hay.includes(searchQuery);
}

export function renderHistory() {
  const $list = $("#historyList").empty();
  const filtered = history.filter((item) => matchesFilter(item) && matchesSearch(item));

  filtered.forEach((item) => {
    const dateBadge = item.request_date ? item.request_date.slice(5) : "—";
    const $row = $(`
      <div class="history-item" data-id="${item.id}" role="listitem" tabindex="0">
        <div class="history-left">
          <div class="history-date-badge">${dateBadge}</div>
          <div>
            <div class="history-title">${item.group_name || "—"}</div>
            <div class="history-sub">${item.request_date || ""} · ${item.duration ?? 0} hrs · ${item.location_name || ""}</div>
          </div>
        </div>
        <span class="status-badge ${statusClass(item.status)}">${statusText(item.status)}</span>
      </div>
    `);

    $row.on("click keypress", function (e) {
      if (
        e.type === "click" ||
        (e.type === "keypress" && (e.key === "Enter" || e.key === " "))
      ) {
        e.preventDefault();
        fetchHistory()
          .then(() => openModal(item.id))
          .catch(() => openModal(item.id));
      }
    });

    $list.append($row);
  });

  if (filtered.length === 0) {
    $list.append(`
      <div class="ot-empty">
        <i class="bi bi-inbox"></i>
        <p class="mb-0">No requests match this filter.</p>
      </div>
    `);
  }
}
