import { showToast } from "./toast.js";
import { apiUrl } from "./api.js";
import { apiGet } from "./http.js";
import { clearFieldInvalid, markFieldInvalid } from "./formValidation.js";

/** @type {Map<string, string>} */
let blockedHolidays = new Map();
/** @type {Array<{start: string, end: string}>} */
let leaveWeekRanges = [];

let dateFieldId = "date";
let relaxedMode = false;

export function configureRequestDate({
  dateFieldId: fieldId = "date",
  relaxed = false,
} = {}) {
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
  return leaveWeekRanges.some(
    (range) => range.start === start && range.end === end,
  );
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
  if (Number.isNaN(date.getTime())) return false;
  // On-behalf (relaxed) allows any valid date, including past dates.
  if (relaxedMode) return true;
  if (isBeforeToday(date)) return false;
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
  const $date = $dateField();
  if (relaxedMode) {
    $date.removeAttr("min");
    return;
  }
  $date.attr("min", formatLocalDate(startOfToday()));
}

export function setDefaultRequestDate() {
  applyDateConstraints();
  $dateField().val(
    formatLocalDate(relaxedMode ? startOfToday() : nextAllowedDate()),
  );
}

export function validateDateInput(showMessage = true) {
  const $date = $dateField();
  const value = $date.val();
  if (!value) {
    markFieldInvalid($date);
    return false;
  }

  applyDateConstraints();

  if (isAllowedRequestDate(value)) {
    clearFieldInvalid($date);
    return true;
  }

  markFieldInvalid($date);

  if (showMessage) {
    const date = parseLocalDate(value);
    if (!relaxedMode && isBeforeToday(date)) {
      showToast("Past dates are not allowed.", { type: "warning" });
    } else if (
      !relaxedMode &&
      isRestrictedDay(value) &&
      !isInCurrentWorkWeek(value)
    ) {
      showToast(
        isHoliday(value)
          ? "Only holidays in the current week can be selected."
          : "Only weekends in the current week can be selected.",
        { type: "warning" },
      );
    } else if (
      !relaxedMode &&
      isRestrictedDay(value) &&
      hasLeaveInWeek(value)
    ) {
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
  // Default may still be invalid briefly; clear once a usable default is set.
  if (isAllowedRequestDate($date.val())) {
    clearFieldInvalid($date);
  }
  return false;
}

export async function loadBlockedHolidays() {
  if (relaxedMode) {
    applyDateConstraints();
    const current = $dateField().val();
    if (!current) {
      setDefaultRequestDate();
    }
    return;
  }

  const from = formatLocalDate(startOfToday());
  const url = apiUrl("/holidays") + "?from=" + encodeURIComponent(from);
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
