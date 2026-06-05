export let history = [];
export let filter = "all";
export let searchQuery = "";

export function setHistory(h) {
  history = Array.isArray(h) ? h : [];
}

export function setFilter(f) {
  filter = f;
}

export function setSearchQuery(q) {
  searchQuery = (q || "").trim().toLowerCase();
}
