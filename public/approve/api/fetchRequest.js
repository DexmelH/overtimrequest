import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";
import { dataSignature } from "../../shared/js/livePoll.js";
import { setOvertime } from "../services/state.js";
import { renderTable } from "../ui/renderOvertime.js";
import { updateStats } from "../ui/stats.js";

let lastSignature = null;

/**
 * @param {{silent?: boolean}} options `silent` marks a background refresh: no
 * loading spinner, no re-render when nothing changed, and a failed request
 * leaves the current table alone instead of emptying it.
 * @returns {Promise<boolean>} true when the table was re-rendered.
 */
export async function fetchRequest({ silent = false } = {}) {
  if (!silent) {
    $("#tableLoading").removeClass("d-none");
    $("#tableEmpty").addClass("d-none");
  }

  try {
    const json = await apiGet(apiUrl("/overtimetoapprove"));
    const incoming = Array.isArray(json?.data) ? json.data : [];
    const signature = dataSignature(incoming);
    if (silent && signature === lastSignature) {
      return false;
    }

    lastSignature = signature;
    setOvertime(incoming);
    updateStats(incoming);
    renderTable();
    return true;
  } catch (error) {
    console.error("Failed to fetch overtime requests:", error);
    if (!silent) {
      lastSignature = null;
      setOvertime([]);
      updateStats([]);
      renderTable();
    }
    throw error;
  } finally {
    if (!silent) {
      $("#tableLoading").addClass("d-none");
    }
  }
}
