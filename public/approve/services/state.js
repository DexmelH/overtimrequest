import { defaultDateRange, DEFAULT_LIST_LIMIT } from "../../shared/js/listQuery.js";

const initialRange = defaultDateRange();

export let overtime = [];
export let filter = "all";
export let selectedIds = new Set();
export let listQuery = {
  from: initialRange.from,
  to: initialRange.to,
  page: 1,
  limit: DEFAULT_LIST_LIMIT,
  view: "all",
};
export let pagination = {
  page: 1,
  limit: DEFAULT_LIST_LIMIT,
  total: 0,
  pages: 0,
};
export let listCounts = {
  total: 0,
  pending: 0,
  acted: 0,
};

export function setOvertime(data) {
  const rows = Array.isArray(data) ? data : [];
  // Needs-action first, then newest within each group (page-local safety net)
  overtime = rows.slice().sort((a, b) => {
    const aPending = a.is_approved ? 1 : 0;
    const bPending = b.is_approved ? 1 : 0;
    if (aPending !== bPending) return aPending - bPending;

    const aDate = String(a.date_created || a.request_date || "");
    const bDate = String(b.date_created || b.request_date || "");
    return bDate.localeCompare(aDate);
  });
  pruneSelection();
}

export function setFilter(f) {
  filter = f || "all";
  listQuery = { ...listQuery, view: filter, page: 1 };
}

export function setListDates(from, to) {
  listQuery = {
    ...listQuery,
    from: from || listQuery.from,
    to: to || listQuery.to,
    page: 1,
  };
}

export function setListPage(page) {
  listQuery = { ...listQuery, page: Math.max(1, Number(page) || 1) };
}

export function setPagination(p) {
  pagination = {
    page: Number(p?.page || 1),
    limit: Number(p?.limit || DEFAULT_LIST_LIMIT),
    total: Number(p?.total || 0),
    pages: Number(p?.pages || 0),
  };
}

export function setListCounts(c) {
  listCounts = {
    total: Number(c?.total || 0),
    pending: Number(c?.pending || 0),
    acted: Number(c?.acted || 0),
  };
}

export function getFilteredOvertime() {
  // Status chips are applied server-side via listQuery.view.
  return overtime;
}

export function getPendingFilteredOvertime() {
  return getFilteredOvertime().filter((req) => !req.is_approved);
}

export function isSelected(id) {
  return selectedIds.has(String(id));
}

export function toggleSelected(id, checked) {
  const key = String(id);
  if (checked) {
    selectedIds.add(key);
  } else {
    selectedIds.delete(key);
  }
}

export function selectPendingInFilter() {
  getPendingFilteredOvertime().forEach((req) => {
    selectedIds.add(String(req.id));
  });
}

export function clearSelection() {
  selectedIds.clear();
}

export function getSelectedIds() {
  return Array.from(selectedIds);
}

export function getSelectedCount() {
  return selectedIds.size;
}

/** Drop IDs that are no longer pending for this approver. */
export function pruneSelection() {
  const pending = new Set(
    overtime
      .filter((req) => !req.is_approved)
      .map((req) => String(req.id)),
  );
  for (const id of Array.from(selectedIds)) {
    if (!pending.has(id)) {
      selectedIds.delete(id);
    }
  }
}
