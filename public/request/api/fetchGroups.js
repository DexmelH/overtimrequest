import {
  retryFetch,
  fetchWithTimeout,
  normalizePayload,
} from "../components/utilities.js";
import { populateSelect } from "../ui/populateSelect.js";

export async function fetchGroups({ showLoading = true } = {}) {
  const $sel = $("#group");
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
          "../api/groups",
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
    const groups = normalizePayload(json);
    if (groups.length) {
      populateSelect(groups, "group");
    } else {
      populateSelect([], "group");
    }
  } catch (error) {
    console.error("Failed to fetch groups:", error);
    if ($sel.length) {
      $sel.empty().append('<option value="">Failed to load groups</option>');
    }
    return [];
  } finally {
    if ($sel.length) {
      $sel.prop("disabled", false);
    }
  }
}
