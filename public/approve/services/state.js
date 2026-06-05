export let overtime = [];
export let filter = "all";

export function setOvertime(data) {
  overtime = Array.isArray(data) ? data : [];
}

export function setFilter(f) {
  filter = f;
}

export function getFilteredOvertime() {
  return overtime.filter((req) => {
    if (filter === "all") return true;
    if (filter === "action") return !req.is_approved;
    if (filter === "done") return !!req.is_approved;
    return true;
  });
}
