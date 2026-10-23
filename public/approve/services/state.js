import { defaultDateRange, DEFAULT_LIST_LIMIT } from "../../shared/js/listQuery.js";

const initialRange = defaultDateRange();

export let overtime = [];
export let filter = "all";
export let selectedIds = new Set();
/** @type {Map<string, object>} Snapshots so bulk confirm can summarize across pages. */
export let selectedMeta = new Map();
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
  approved: 0,
  rejected: 0,
};

function snapshotRequest(req) {
  if (!req) return null;
  return {
    id: req.id,
    employee_id: req.employee_id,
    employee_name: req.employee_name,
    request_date: req.request_date,
    duration: req.duration,
    duration_minutes: req.duration_minutes,
  };
}

function rememberSelected(req) {
  const snap = snapshotRequest(req);
  if (!snap) return;
  selectedMeta.set(String(snap.id), snap);
}

export function setOvertime(data) {
  const rows = Array.isArray(data) ? data : [];
  // Needs-action first, then newest within each group (server already prefers this).
  overtime = rows.slice().sort((a, b) => {
    const aPending = a.is_approved ? 1 : 0;
    const bPending = b.is_approved ? 1 : 0;
    if (aPending !== bPending) return aPending - bPending;

    const aDate = String(a.date_created || a.request_date || "");
    const bDate = String(b.date_created || b.request_date || "");
    return bDate.localeCompare(aDate);
  });

  // Refresh snapshots for selected rows still on this page.
  for (const req of overtime) {
    const key = String(req.id);
    if (selectedIds.has(key)) {
      rememberSelected(req);
    }
  }
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

export function setListCounts(counts) {
  listCounts = {
    total: Number(counts?.total || 0),
    approved: Number(counts?.approved || 0),
    rejected: Number(counts?.rejected || 0),
  };
}

/** Server already filtered; return the current page as-is. */
export function getFilteredOvertime() {
  return overtime;
}

export function getPendingFilteredOvertime() {
  return getFilteredOvertime().filter((req) => !req.is_approved);
}

export function isSelected(id) {
  return selectedIds.has(String(id));
}

/**
 * @param {number|string} id
 * @param {boolean} checked
 * @param {object} [request] Full row when available (keeps meta for bulk OT summary)
 */
export function toggleSelected(id, checked, request = null) {
  const key = String(id);
  if (checked) {
    selectedIds.add(key);
    const req =
      request || overtime.find((r) => String(r.id) === key) || null;
    rememberSelected(req);
  } else {
    selectedIds.delete(key);
    selectedMeta.delete(key);
  }
}

export function selectPendingInFilter() {
  getPendingFilteredOvertime().forEach((req) => {
    selectedIds.add(String(req.id));
    rememberSelected(req);
  });
}

export function clearSelection() {
  selectedIds.clear();
  selectedMeta.clear();
}

export function getSelectedIds() {
  return Array.from(selectedIds);
}

export function getSelectedCount() {
  return selectedIds.size;
}

/**
 * Selected request snapshots (works across pages).
 * @returns {object[]}
 */
export function getSelectedRequests() {
  return getSelectedIds()
    .map((id) => {
      const key = String(id);
      return (
        selectedMeta.get(key) ||
        overtime.find((r) => String(r.id) === key) ||
        null
      );
    })
    .filter(Boolean);
}

/**
 * Deselect every selected request belonging to the given employee.
 * @param {number|string} employeeId
 * @returns {number} how many request IDs were removed
 */
export function removeEmployeeFromSelection(employeeId) {
  const target = String(employeeId ?? "");
  if (!target) return 0;

  let removed = 0;
  for (const id of Array.from(selectedIds)) {
    const key = String(id);
    const req =
      selectedMeta.get(key) ||
      overtime.find((r) => String(r.id) === key) ||
      null;
    if (!req || String(req.employee_id ?? "") !== target) continue;
    selectedIds.delete(key);
    selectedMeta.delete(key);
    removed += 1;
  }
  return removed;
}

/**
 * Drop selections that are on the current page and no longer pending.
 * Off-page selections are kept (meta snapshots remain).
 */
export function pruneSelection() {
  const onPage = new Map(
    overtime.map((req) => [String(req.id), req]),
  );
  for (const id of Array.from(selectedIds)) {
    const key = String(id);
    const onPageReq = onPage.get(key);
    if (!onPageReq) continue;
    if (onPageReq.is_approved) {
      selectedIds.delete(key);
      selectedMeta.delete(key);
    }
  }
}
