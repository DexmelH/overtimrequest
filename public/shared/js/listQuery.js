/**
 * Date-range + pagination helpers for history and approve lists.
 */

export const DEFAULT_LIST_DAYS = 7;
export const DEFAULT_LIST_LIMIT = 10;

function pad(n) {
  return String(n).padStart(2, "0");
}

export function formatIsoDate(date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function defaultDateRange(days = DEFAULT_LIST_DAYS) {
  const to = new Date();
  const from = new Date();
  from.setDate(from.getDate() - Math.max(1, days));
  return {
    from: formatIsoDate(from),
    to: formatIsoDate(to),
  };
}

/**
 * @param {Record<string, string|number|null|undefined>} values
 */
export function buildListQuery(values = {}) {
  const params = new URLSearchParams();
  Object.entries(values).forEach(([key, value]) => {
    if (value === null || value === undefined) return;
    const text = String(value).trim();
    if (!text || text === "all") return;
    params.set(key, text);
  });
  const qs = params.toString();
  return qs ? `?${qs}` : "";
}

/**
 * Update prev/next pager labels. Elements may be jQuery objects or selectors.
 */
export function renderPager(
  { info, prev, next },
  pagination = {},
  { emptyLabel = "No entries in this date range" } = {},
) {
  const page = Number(pagination.page || 1);
  const pages = Number(pagination.pages || 0);
  const total = Number(pagination.total || 0);
  const limit = Number(pagination.limit || DEFAULT_LIST_LIMIT);
  const $info = $(info);
  const $prev = $(prev);
  const $next = $(next);

  if (total <= 0) {
    $info.text(emptyLabel);
    $prev.prop("disabled", true);
    $next.prop("disabled", true);
    return;
  }

  const start = (page - 1) * limit + 1;
  const end = Math.min(page * limit, total);
  $info.text(`${start}–${end} of ${total}`);
  $prev.prop("disabled", page <= 1);
  $next.prop("disabled", pages > 0 ? page >= pages : true);
}
