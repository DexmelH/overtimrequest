import { showToast } from "../../shared/js/toast.js";
import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";

/** @type {Map<string, string>} */
let blockedHolidays = new Map();
/** @type {Array<{start: string, end: string}>} */
let leaveWeekRanges = [];

let dateFieldId = "date";
let relaxedMode = false;

export function configureRequestDate({ dateFieldId: fieldId = "date", relaxed = false } = {}) {
  dateFieldId = fieldId;
  relaxedMode = relaxed;
}

function $dateField() {
  return $(`#${dateFieldId}`);
}

function parseLocalDate(isoDate) {
  const [y, m, d] = isoDate.split("-").map(Number);
  return new Date(y, m - 1, d);
}

export function formatLocalDate(date) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, "0");
  const d = String(date.getDate()).padStart(2, "0");
  return `${y}-${m}-${d}`;
}

function startOfToday() {
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

function isWeekend(date) {
  const day = date.getDay();
  return day === 0 || day === 6;
}

function isBeforeToday(date) {
  return date < startOfToday();
}

function isHoliday(isoDate) {
  return blockedHolidays.has(isoDate);
}

function getHolidayName(isoDate) {
  return blockedHolidays.get(isoDate) || "";
}

function workWeekBounds(isoDate) {
  const date = parseLocalDate(isoDate);
  const day = date.getDay() || 7;
  const monday = new Date(date);
  monday.setDate(date.getDate() - day + 1);
  const friday = new Date(monday);
  friday.setDate(monday.getDate() + 4);
  return { start: formatLocalDate(monday), end: formatLocalDate(friday) };
}

function hasLeaveInWeek(isoDate) {
  const { start, end } = workWeekBounds(isoDate);
  return leaveWeekRanges.some((range) => range.start === start && range.end === end);
}

function isInCurrentWorkWeek(isoDate) {
  const current = workWeekBounds(formatLocalDate(startOfToday()));
  const target = workWeekBounds(isoDate);
  return current.start === target.start && current.end === target.end;
}

function isRestrictedDay(isoDate) {
  const date = parseLocalDate(isoDate);
  return isWeekend(date) || isHoliday(isoDate);
}

export function isAllowedRequestDate(isoDate) {
  if (!isoDate) return false;
  const date = parseLocalDate(isoDate);
  if (isBeforeToday(date)) return false;
  if (relaxedMode) return true;
  if (!isRestrictedDay(isoDate)) return true;
  if (!isInCurrentWorkWeek(isoDate)) return false;
  return !hasLeaveInWeek(isoDate);
}

function nextAllowedDate(fromDate = startOfToday()) {
  const date = new Date(fromDate);
  while (!isAllowedRequestDate(formatLocalDate(date))) {
    date.setDate(date.getDate() + 1);
  }
  return date;
}

export function applyDateConstraints() {
  $dateField().attr("min", formatLocalDate(startOfToday()));
}

export function setDefaultRequestDate() {
  applyDateConstraints();
  $dateField().val(formatLocalDate(relaxedMode ? startOfToday() : nextAllowedDate()));
}

export function validateDateInput(showMessage = true) {
  const $date = $dateField();
  const value = $date.val();
  if (!value) return false;

  applyDateConstraints();

  if (isAllowedRequestDate(value)) return true;

  if (showMessage) {
    const date = parseLocalDate(value);
    if (isBeforeToday(date)) {
      showToast("Past dates are not allowed.", { type: "warning" });
    } else if (!relaxedMode && isRestrictedDay(value) && !isInCurrentWorkWeek(value)) {
      showToast(
        isHoliday(value)
          ? "Only holidays in the current week can be selected."
          : "Only weekends in the current week can be selected.",
        { type: "warning" },
      );
    } else if (!relaxedMode && isRestrictedDay(value) && hasLeaveInWeek(value)) {
      if (isHoliday(value)) {
        const name = getHolidayName(value);
        showToast(
          name
            ? `You have approved leave this week, so ${name} cannot be selected.`
            : "You have approved leave this week, so this holiday cannot be selected.",
          { type: "warning" },
        );
      } else {
        showToast(
          "You have approved leave this week, so weekend overtime cannot be requested.",
          { type: "warning" },
        );
      }
    }
  }

  setDefaultRequestDate();
  return false;
}

export async function loadBlockedHolidays(employeeId = null) {
  if (relaxedMode) {
    applyDateConstraints();
    const current = $dateField().val();
    if (!current) {
      setDefaultRequestDate();
    }
    return;
  }

  const from = formatLocalDate(startOfToday());
  let url = apiUrl("/holidays") + "?from=" + encodeURIComponent(from);
  if (employeeId) {
    url += "&employee_id=" + encodeURIComponent(String(employeeId));
  }
  try {
    const json = await apiGet(url);
    blockedHolidays = new Map();
    (json?.data || []).forEach((row) => {
      if (!row?.date) return;
      const date = String(row.date).slice(0, 10);
      blockedHolidays.set(date, row.name || "Holiday");
    });
    leaveWeekRanges = (json?.leave_weeks || [])
      .map((row) => ({
        start: String(row.start || "").slice(0, 10),
        end: String(row.end || "").slice(0, 10),
      }))
      .filter((row) => row.start && row.end);
  } catch {
    blockedHolidays = new Map();
    leaveWeekRanges = [];
  }

  const current = $dateField().val();
  if (current && !isAllowedRequestDate(current)) {
    validateDateInput(true);
  } else if (!current) {
    setDefaultRequestDate();
  }
}
