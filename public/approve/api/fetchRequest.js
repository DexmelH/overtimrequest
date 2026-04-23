import { setOvertime } from "../services/state.js";
import { renderTable } from "../ui/renderOvertime.js";

export async function fetchRequest() {
  try {
    const response = await fetch("../api/overtimetoapprove", {
      method: "GET",
      credentials: "same-origin",
    });
    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();

    console.log(json.data);

    const incoming = Array.isArray(json.data) ? json.data : [];

    setOvertime(incoming);

    renderTable(incoming);
  } catch (error) {
    console.log("Failed to fetch overtime requests:", error);
  }
}
