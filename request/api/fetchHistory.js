import { setHistory } from "../services/state.js";
import { renderHistory } from "../ui/renderHistory.js";

export async function fetchHistory() {
  try {
    const response = await fetch("../php/getHistory.php", {
      method: "GET",
      credentials: "same-origin",
    });
    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();

    console.log(json);

    const incoming = Array.isArray(json)
      ? json
      : Array.isArray(json)
        ? json
        : [];

    setHistory(incoming);

    renderHistory();
  } catch (error) {
    console.log("Failed to fetch history:", error);
  }
}
