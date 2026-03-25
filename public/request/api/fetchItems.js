import {
  retryFetch,
  fetchWithTimeout,
  normalizePayload,
} from "../components/utilities.js";
import { populateSelect } from "../ui/populateSelect.js";

export async function fetchItems({ showLoading = true } = {}) {
  const $sel = $("#item");
  if ($sel.length && showLoading) {
    $sel.prop("disabled", true);
    const prev = $sel.data("prev") || null;
    $sel.data("prev-text", prev);
    $sel.empty().append('<option value="">Loading...</option>');
  }

  const project = $("#project").val();

  try {
    const response = await retryFetch(
      () =>
        fetchWithTimeout(
          "../api/items?project=" + encodeURIComponent(project),
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
    const items = normalizePayload(json);
    if (items.length) {
      populateSelect(items, "item");
    } else {
      populateSelect([], "item");
    }
  } catch (error) {
    console.error("Failed to fetch items:", error);
    if ($sel.length) {
      $sel.empty().append('<option value="">Failed to load items</option>');
    }
    return [];
  } finally {
    if ($sel.length) {
      $sel.prop("disabled", false);
    }
  }
}
