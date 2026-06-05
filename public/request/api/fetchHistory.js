import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";
import { setHistory } from "../services/state.js";
import { renderHistory } from "../ui/renderHistory.js";

export async function fetchHistory() {
  try {
    const json = await apiGet(apiUrl("/overtimehistory"));
    setHistory(Array.isArray(json) ? json : []);
    renderHistory();
  } catch (error) {
    console.error("Failed to fetch history:", error);
    throw error;
  }
}
