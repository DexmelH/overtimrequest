import { fetchWithTimeout, retryFetch } from "../components/utilities.js";
import { setHistory } from "../services/state.js";
import { renderHistory } from "../ui/renderHistory.js";

export async function fetchHistory() {
  try {
    const response = await retryFetch(
      () =>
        fetchWithTimeout(
          "../api/overtimehistory",
          {
            method: "GET",
            credentials: "same-origin",
          },
          8000,
        ),
      3,
      300,
    );
    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();

    const incoming = Array.isArray(json) ? json : [];

    setHistory(incoming);

    renderHistory();
  } catch (error) {
    console.log("Failed to fetch history:", error);
  }
}
