import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";
import { buildListQuery, formatIsoDate } from "../../shared/js/listQuery.js";
import { escapeHtml } from "../../shared/js/escapeHtml.js";
import { statusClass, statusText } from "../../shared/js/status.js";
import { openModal } from "../components/modal.js";
import { showToast } from "../../shared/js/toast.js";

const WEEKDAYS = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];

let viewMode = "list";
let cursor = startOfMonth(new Date());
let selectedDate = null;
let monthRows = [];
/** @type {Map<string, string>} */
let holidays = new Map();
/** @type {Array<{start: string, end: string}>} */
let leaveWeeks = [];
let loading = false;

function pad(n) {
  return String(n).padStart(2, "0");
}

function startOfMonth(date) {
  return new Date(date.getFullYear(), date.getMonth(), 1);
}

function endOfMonth(date) {
  return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}

function addMonths(date, delta) {
  return new Date(date.getFullYear(), date.getMonth() + delta, 1);
}

function parseLocalDate(iso) {
  const [y, m, d] = String(iso).slice(0, 10).split("-").map(Number);
  return new Date(y, m - 1, d);
}

function workWeekBounds(isoDate) {
  const date = parseLocalDate(isoDate);
  const day = date.getDay() || 7;
  const monday = new Date(date);
  monday.setDate(date.getDate() - day + 1);
  const friday = new Date(monday);
  friday.setDate(monday.getDate() + 4);
  return { start: formatIsoDate(monday), end: formatIsoDate(friday) };
}

function isLeaveWeek(isoDate) {
  const { start, end } = workWeekBounds(isoDate);
  return leaveWeeks.some((range) => range.start === start && range.end === end);
}

function monthLabel(date) {
  return date.toLocaleDateString(undefined, {
    month: "long",
    year: "numeric",
  });
}

function dayLabel(isoDate) {
  return parseLocalDate(isoDate).toLocaleDateString(undefined, {
    weekday: "short",
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function rowsByDate() {
  const map = new Map();
  monthRows.forEach((row) => {
    const key = String(row.request_date || "").slice(0, 10);
    if (!key) return;
    if (!map.has(key)) map.set(key, []);
    map.get(key).push(row);
  });
  return map;
}

function statusDotClass(status) {
  return statusClass(status);
}

async function loadMonthData() {
  const from = formatIsoDate(cursor);
  const to = formatIsoDate(endOfMonth(cursor));
  loading = true;
  $("#calGrid").attr("aria-busy", "true");

  try {
    const [holidayJson, historyJson] = await Promise.all([
      apiGet(apiUrl("/holidays") + "?from=" + encodeURIComponent(from)),
      apiGet(
        apiUrl("/overtimehistory") +
          buildListQuery({ from, to, page: 1, limit: 100 }),
      ),
    ]);

    holidays = new Map();
    (holidayJson?.data || []).forEach((row) => {
      if (!row?.date) return;
      const date = String(row.date).slice(0, 10);
      // Keep holidays that fall in this month (API returns from month-start onward).
      if (date >= from && date <= to) {
        holidays.set(date, row.name || "Holiday");
      }
    });

    leaveWeeks = (holidayJson?.leave_weeks || [])
      .map((row) => ({
        start: String(row.start || "").slice(0, 10),
        end: String(row.end || "").slice(0, 10),
      }))
      .filter((row) => row.start && row.end);

    monthRows = Array.isArray(historyJson?.data) ? historyJson.data : [];
  } catch (error) {
    console.error("Failed to load calendar month:", error);
    showToast("Could not load calendar data.", { type: "error" });
    monthRows = [];
    holidays = new Map();
    leaveWeeks = [];
  } finally {
    loading = false;
    $("#calGrid").attr("aria-busy", "false");
  }
}

function renderDayPanel(isoDate) {
  const $title = $("#calDayTitle");
  const $meta = $("#calDayMeta").empty();
  const $list = $("#calDayList").empty();

  if (!isoDate) {
    $title.text("Select a day");
    $list.append(
      '<p class="ot-cal-day-empty">Choose a date to see overtime requests.</p>',
    );
    return;
  }

  const holidayName = holidays.get(isoDate);
  const leave = isLeaveWeek(isoDate);
  const rows = rowsByDate().get(isoDate) || [];

  $title.text(dayLabel(isoDate));

  if (holidayName) {
    $meta.append(
      `<span class="ot-cal-chip is-holiday">${escapeHtml(holidayName)}</span>`,
    );
  }
  if (leave) {
    $meta.append('<span class="ot-cal-chip is-leave">Leave week</span>');
  }
  $meta.append(
    `<span class="ot-cal-chip is-count">${rows.length} request${
      rows.length === 1 ? "" : "s"
    }</span>`,
  );

  if (!rows.length) {
    $list.append(
      '<p class="ot-cal-day-empty">No overtime requests on this day.</p>',
    );
    return;
  }

  rows.forEach((item) => {
    const $btn = $(`
      <button type="button" class="ot-cal-day-item" data-request-id="${escapeHtml(
        String(item.id),
      )}">
        <span class="status-badge ${statusClass(item.status)}">${escapeHtml(
          statusText(item.status),
        )}</span>
        <span class="ot-cal-day-item-main">
          <strong>${escapeHtml(item.group_name || "—")}</strong>
          <span class="ot-muted">${escapeHtml(String(item.duration ?? 0))} hrs · ${escapeHtml(
            item.location_name || "—",
          )}</span>
        </span>
        <i class="bi bi-chevron-right ot-cal-day-item-open" aria-hidden="true"></i>
      </button>
    `);
    $btn.on("click", () => {
      $("#calDayList .ot-cal-day-item").removeClass("is-active");
      $btn.addClass("is-active");
      openModal(item.id).catch(() => {});
    });
    $list.append($btn);
  });
}

function renderGrid() {
  const $grid = $("#calGrid").empty();
  $("#calMonthLabel").text(monthLabel(cursor));

  WEEKDAYS.forEach((label) => {
    $grid.append(`<div class="ot-cal-weekday">${escapeHtml(label)}</div>`);
  });

  const first = startOfMonth(cursor);
  const last = endOfMonth(cursor);
  // Monday-based offset: Sun=0 → 6, Mon=1 → 0, ...
  const lead = (first.getDay() + 6) % 7;
  const byDate = rowsByDate();
  const todayIso = formatIsoDate(new Date());

  for (let i = 0; i < lead; i++) {
    $grid.append('<div class="ot-cal-cell is-outside" aria-hidden="true"></div>');
  }

  for (let day = 1; day <= last.getDate(); day++) {
    const date = new Date(cursor.getFullYear(), cursor.getMonth(), day);
    const iso = formatIsoDate(date);
    const weekday = date.getDay();
    const isWeekend = weekday === 0 || weekday === 6;
    const holidayName = holidays.get(iso);
    const leave = !isWeekend && isLeaveWeek(iso);
    const rows = byDate.get(iso) || [];
    const selected = selectedDate === iso;
    const isToday = iso === todayIso;

    const classes = [
      "ot-cal-cell",
      isWeekend ? "is-weekend" : "",
      holidayName ? "is-holiday" : "",
      leave ? "is-leave" : "",
      rows.length ? "has-ot" : "",
      selected ? "is-selected" : "",
      isToday ? "is-today" : "",
    ]
      .filter(Boolean)
      .join(" ");

    const titleParts = [];
    if (holidayName) titleParts.push(holidayName);
    if (leave) titleParts.push("Leave week");
    if (rows.length) titleParts.push(`${rows.length} OT request(s)`);

    let flag = "";
    if (holidayName) {
      flag = `<span class="ot-cal-flag is-holiday">H</span>`;
    } else if (leave) {
      flag = `<span class="ot-cal-flag is-leave">L</span>`;
    }

    const dots = rows
      .slice(0, 3)
      .map(
        (row) =>
          `<span class="ot-cal-dot ${statusDotClass(row.status)}" title="${escapeHtml(
            statusText(row.status),
          )}"></span>`,
      )
      .join("");
    const more =
      rows.length > 3
        ? `<span class="ot-cal-more">+${rows.length - 3}</span>`
        : "";

    const $cell = $(`
      <button type="button" class="${classes}" data-date="${escapeHtml(iso)}" aria-pressed="${
        selected ? "true" : "false"
      }" title="${escapeHtml(titleParts.join(" · ") || iso)}">
        <span class="ot-cal-daynum">${day}</span>
        <span class="ot-cal-marks">${flag}${dots}${more}</span>
      </button>
    `);
    $grid.append($cell);
  }

  // Pad to 6 weeks (42 cells) so the month grid fills evenly.
  const filled = lead + last.getDate();
  const trailing = Math.max(0, 42 - filled);
  for (let i = 0; i < trailing; i++) {
    $grid.append('<div class="ot-cal-cell is-outside" aria-hidden="true"></div>');
  }
}

export function getHistoryViewMode() {
  return viewMode;
}

export async function refreshCalendarMonth() {
  if (viewMode !== "calendar") return;
  await loadMonthData();
  renderGrid();
  renderDayPanel(selectedDate);
}

function setViewMode(mode) {
  viewMode = mode === "calendar" ? "calendar" : "list";
  const isCalendar = viewMode === "calendar";

  $("#historyViewList")
    .toggleClass("active", !isCalendar)
    .attr("aria-pressed", !isCalendar ? "true" : "false");
  $("#historyViewCalendar")
    .toggleClass("active", isCalendar)
    .attr("aria-pressed", isCalendar ? "true" : "false");
  $("#historyListToolbar").toggleClass("d-none", isCalendar);
  $("#historyList").toggleClass("d-none", isCalendar);
  $("#historyListPager").toggleClass("d-none", isCalendar);
  $("#historyCalendar").toggleClass("d-none", !isCalendar);
  $("#historyCardHint").text(
    isCalendar
      ? "Select a day to review overtime"
      : "Click a row to view details",
  );
}

export async function showHistoryCalendar() {
  setViewMode("calendar");
  if (!selectedDate) {
    selectedDate = formatIsoDate(new Date());
    cursor = startOfMonth(parseLocalDate(selectedDate));
  }
  await refreshCalendarMonth();
}

export function showHistoryList() {
  setViewMode("list");
}

export function initHistoryCalendar() {
  $("#historyViewList").on("click", function () {
    showHistoryList();
  });

  $("#historyViewCalendar").on("click", function () {
    showHistoryCalendar().catch(() => {});
  });

  $("#calPrevMonth").on("click", async function () {
    cursor = addMonths(cursor, -1);
    selectedDate = null;
    await refreshCalendarMonth();
  });

  $("#calNextMonth").on("click", async function () {
    cursor = addMonths(cursor, 1);
    selectedDate = null;
    await refreshCalendarMonth();
  });

  $("#calTodayBtn").on("click", async function () {
    const today = new Date();
    cursor = startOfMonth(today);
    selectedDate = formatIsoDate(today);
    await refreshCalendarMonth();
  });

  $("#calGrid").on("click", ".ot-cal-cell[data-date]", function () {
    selectedDate = String($(this).data("date") || "");
    renderGrid();
    renderDayPanel(selectedDate);
  });
}
