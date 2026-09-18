import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";
import { dataSignature } from "../../shared/js/livePoll.js";
import { buildListQuery } from "../../shared/js/listQuery.js";
import {
  listQuery,
  setHistory,
  setPagination,
} from "../services/state.js";
import { renderHistory } from "../ui/renderHistory.js";

let lastSignature = null;

export function resetHistorySignature() {
  lastSignature = null;
}

/**
 * @returns {Promise<boolean>} true when the payload differed and the list was re-rendered.
 */
export async function fetchHistory() {
  try {
    const qs = buildListQuery({
      from: listQuery.from,
      to: listQuery.to,
      page: listQuery.page,
      limit: listQuery.limit,
      status: listQuery.status,
      q: listQuery.q,
    });
    const json = await apiGet(apiUrl("/overtimehistory") + qs);
    const rows = Array.isArray(json?.data)
      ? json.data
      : Array.isArray(json)
        ? json
        : [];
    const pagination = json?.pagination || {
      page: listQuery.page,
      limit: listQuery.limit,
      total: rows.length,
      pages: 1,
    };
    const signature = dataSignature({
      data: rows,
      pagination,
      from: json?.from || listQuery.from,
      to: json?.to || listQuery.to,
      status: listQuery.status,
      q: listQuery.q,
    });
    if (signature === lastSignature) {
      return false;
    }

    lastSignature = signature;
    setHistory(rows);
    setPagination(pagination);
    renderHistory();
    return true;
  } catch (error) {
    console.error("Failed to fetch history:", error);
    throw error;
  }
}
