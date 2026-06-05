import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";
import { setOvertime } from "../services/state.js";
import { renderTable } from "../ui/renderOvertime.js";
import { updateStats } from "../ui/stats.js";

export async function fetchRequest() {
  $("#tableLoading").removeClass("d-none");
  $("#tableEmpty").addClass("d-none");

  try {
    const json = await apiGet(apiUrl("/overtimetoapprove"));
    const incoming = Array.isArray(json?.data) ? json.data : [];
    setOvertime(incoming);
    updateStats(incoming);
    renderTable();
    return incoming;
  } catch (error) {
    console.error("Failed to fetch overtime requests:", error);
    setOvertime([]);
    updateStats([]);
    renderTable();
    throw error;
  } finally {
    $("#tableLoading").addClass("d-none");
  }
}
