import { setHistory } from "../services/state.js";
import { renderHistory } from "../ui/renderHistory.js";

export async function fetchHistory() {
  try {
    const response = await fetch("../api/overtimehistory", {
      method: "GET",
      credentials: "same-origin",
    });
    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();

    console.log(json.data);

    const incoming = Array.isArray(json.data) ? json.data : [];

    setHistory(incoming);

    renderHistory();
  } catch (error) {
    console.log("Failed to fetch history:", error);
  }
}
