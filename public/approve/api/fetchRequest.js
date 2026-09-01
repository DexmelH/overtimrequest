import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";
import { dataSignature } from "../../shared/js/livePoll.js";
import { buildListQuery } from "../../shared/js/listQuery.js";
import {
  listQuery,
  setListCounts,
  setOvertime,
  setPagination,
} from "../services/state.js";
import { renderTable } from "../ui/renderOvertime.js";
import { updateStats } from "../ui/stats.js";

let lastSignature = null;

export function resetRequestSignature() {
  lastSignature = null;
}

/**
 * @param {{silent?: boolean}} options
 * @returns {Promise<boolean>} true when the table was re-rendered.
 */
export async function fetchRequest({ silent = false } = {}) {
  if (!silent) {
    $("#tableLoading").removeClass("d-none");
    $("#tableEmpty").addClass("d-none");
  }

  try {
    const qs = buildListQuery({
      from: listQuery.from,
      to: listQuery.to,
      page: listQuery.page,
      limit: listQuery.limit,
      view: listQuery.view,
    });
    const json = await apiGet(apiUrl("/overtimetoapprove") + qs);
    const incoming = Array.isArray(json?.data) ? json.data : [];
    const pagination = json?.pagination || {
      page: listQuery.page,
      limit: listQuery.limit,
      total: incoming.length,
      pages: 1,
    };
    const counts = json?.counts || {
      total: incoming.length,
      pending: incoming.filter((r) => !r.is_approved).length,
      acted: incoming.filter((r) => r.is_approved).length,
    };
    const signature = dataSignature({
      data: incoming,
      pagination,
      counts,
      from: json?.from || listQuery.from,
      to: json?.to || listQuery.to,
      view: listQuery.view,
    });
    if (silent && signature === lastSignature) {
      return false;
    }

    lastSignature = signature;
    setOvertime(incoming);
    setPagination(pagination);
    setListCounts(counts);
    updateStats(counts);
    renderTable();
    return true;
  } catch (error) {
    console.error("Failed to fetch overtime requests:", error);
    if (!silent) {
      lastSignature = null;
      setOvertime([]);
      setPagination({ page: 1, limit: listQuery.limit, total: 0, pages: 0 });
      setListCounts({ total: 0, pending: 0, acted: 0 });
      updateStats({ total: 0, pending: 0, acted: 0 });
      renderTable();
    }
    throw error;
  } finally {
    if (!silent) {
      $("#tableLoading").addClass("d-none");
    }
  }
}
