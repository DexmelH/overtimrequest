export let overtime = [];
export let filter = "all";
export let selectedIds = new Set();

export function setOvertime(data) {
  const rows = Array.isArray(data) ? data : [];
  // Needs-action first, then newest within each group
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
  filter = f;
}

export function getFilteredOvertime() {
  return overtime.filter((req) => {
    if (filter === "all") return true;
    if (filter === "action") return !req.is_approved;
    if (filter === "done") return !!req.is_approved;
    return req.status_code === filter;
  });
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
