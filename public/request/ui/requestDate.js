import { showToast } from "../../shared/js/toast.js";
import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";

/** @type {Map<string, string>} */
let blockedHolidays = new Map();

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

export function isAllowedRequestDate(isoDate) {
  if (!isoDate) return false;
  const date = parseLocalDate(isoDate);
  return !isBeforeToday(date) && !isWeekend(date) && !isHoliday(isoDate);
}

function nextAllowedDate(fromDate = startOfToday()) {
  const date = new Date(fromDate);
  while (
    isBeforeToday(date) ||
    isWeekend(date) ||
    isHoliday(formatLocalDate(date))
  ) {
    date.setDate(date.getDate() + 1);
  }
  return date;
}

export function applyDateConstraints() {
  $("#date").attr("min", formatLocalDate(startOfToday()));
}

export function setDefaultRequestDate() {
  applyDateConstraints();
  $("#date").val(formatLocalDate(nextAllowedDate()));
}

export function validateDateInput(showMessage = true) {
  const $date = $("#date");
  const value = $date.val();
  if (!value) return false;

  applyDateConstraints();

  if (isAllowedRequestDate(value)) return true;

  if (showMessage) {
    const date = parseLocalDate(value);
    if (isBeforeToday(date)) {
      showToast("Past dates are not allowed.", { type: "warning" });
    } else if (isWeekend(date)) {
      showToast("Saturday and Sunday are not allowed.", { type: "warning" });
    } else if (isHoliday(value)) {
      const name = getHolidayName(value);
      showToast(
        name
          ? `${name} is a holiday and cannot be selected.`
          : "This date is a holiday and cannot be selected.",
        { type: "warning" },
      );
    }
  }

  setDefaultRequestDate();
  return false;
}

export async function loadBlockedHolidays() {
  const from = formatLocalDate(startOfToday());
  try {
    const json = await apiGet(
      apiUrl("/holidays") + "?from=" + encodeURIComponent(from),
    );
    blockedHolidays = new Map();
    (json?.data || []).forEach((row) => {
      if (!row?.date) return;
      const date = String(row.date).slice(0, 10);
      blockedHolidays.set(date, row.name || "Holiday");
    });
  } catch {
    blockedHolidays = new Map();
  }

  const current = $("#date").val();
  if (current && !isAllowedRequestDate(current)) {
    validateDateInput(true);
  } else if (!current) {
    setDefaultRequestDate();
  }
}
