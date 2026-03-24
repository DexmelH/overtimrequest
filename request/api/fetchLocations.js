import {
  retryFetch,
  fetchWithTimeout,
  normalizePayload,
} from "../components/utilities.js";
import { populateSelect } from "../ui/populateSelect.js";

export async function fetchLocations({ showLoading = true } = {}) {
  const $sel = $("#location");
  if ($sel.length && showLoading) {
    $sel.prop("disabled", true);
    const prev = $sel.data("prev") || null;
    $sel.data("prev-text", prev);
    $sel.empty().append('<option value="">Loading...</option>');
  }

  try {
    const response = await retryFetch(
      () =>
        fetchWithTimeout(
          "../php/getLocations.php",
          {
            method: "GET",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
          },
          8000,
        ),
      3,
      300,
    );

    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();
    const locations = normalizePayload(json);
    if (locations.length) {
      populateSelect(locations, "location");
    } else {
      populateSelect([], "location");
    }
  } catch (error) {
    console.error("Failed to fetch locations:", error);
    if ($sel.length) {
      $sel.empty().append('<option value="">Failed to load locations</option>');
    }
    return [];
  } finally {
    if ($sel.length) {
      $sel.prop("disabled", false);
    }
  }
}
