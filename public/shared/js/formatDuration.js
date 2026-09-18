/**
 * Format hours + minutes as a short label (e.g. "2 hrs", "1h 30m").
 * @param {number|string|null|undefined} hours
 * @param {number|string|null|undefined} minutes
 * @returns {string}
 */
export function formatDuration(hours, minutes = 0) {
  const h = Math.max(0, Math.floor(Number(hours) || 0));
  const m = Math.max(0, Math.min(59, Math.floor(Number(minutes) || 0)));

  if (h <= 0 && m <= 0) return "0 min";
  if (h > 0 && m > 0) return `${h}h ${m}m`;
  if (h > 0) return `${h} ${h === 1 ? "hr" : "hrs"}`;
  return `${m} min`;
}

/** @param {number} hours @param {number} minutes */
export function toTotalMinutes(hours, minutes = 0) {
  const h = Math.max(0, Math.floor(Number(hours) || 0));
  const m = Math.max(0, Math.min(59, Math.floor(Number(minutes) || 0)));
  return h * 60 + m;
}
