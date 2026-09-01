import { defaultDateRange, DEFAULT_LIST_LIMIT } from "../../shared/js/listQuery.js";

const initialRange = defaultDateRange();

export let history = [];
export let filter = "all";
export let searchQuery = "";
export let listQuery = {
  from: initialRange.from,
  to: initialRange.to,
  page: 1,
  limit: DEFAULT_LIST_LIMIT,
  status: "all",
  q: "",
};
export let pagination = {
  page: 1,
  limit: DEFAULT_LIST_LIMIT,
  total: 0,
  pages: 0,
};

export function setHistory(h) {
  history = Array.isArray(h) ? h : [];
}

export function setFilter(f) {
  filter = f || "all";
  listQuery = { ...listQuery, status: filter, page: 1 };
}

export function setSearchQuery(q) {
  searchQuery = (q || "").trim().toLowerCase();
  listQuery = { ...listQuery, q: (q || "").trim(), page: 1 };
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
