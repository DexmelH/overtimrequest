import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";
import { dataSignature } from "../../shared/js/livePoll.js";
import { setHistory } from "../services/state.js";
import { renderHistory } from "../ui/renderHistory.js";

let lastSignature = null;

/**
 * @returns {Promise<boolean>} true when the payload differed from the previous
 * one and the list was re-rendered. Background refreshes rely on this to avoid
 * rebuilding the list (and resetting its scroll position) for no reason.
 */
export async function fetchHistory() {
  try {
    const json = await apiGet(apiUrl("/overtimehistory"));
    const rows = Array.isArray(json) ? json : [];
    const signature = dataSignature(rows);
    if (signature === lastSignature) {
      return false;
    }

    lastSignature = signature;
    setHistory(rows);
    renderHistory();
    return true;
  } catch (error) {
    console.error("Failed to fetch history:", error);
    throw error;
  }
}
