import {
  retryFetch,
  fetchWithTimeout,
  normalizePayload,
} from "../components/utilities.js";
import { populateSelect } from "../ui/populateSelect.js";

export async function fetchProjects({ showLoading = true } = {}) {
  const $sel = $("#project");
  if ($sel.length && showLoading) {
    $sel.prop("disabled", true);
    const prev = $sel.data("prev") || null;
    $sel.data("prev-text", prev);
    $sel.empty().append('<option value="">Loading...</option>');
  }

  const group = $("#group option:selected").text();

  try {
    const response = await retryFetch(
      () =>
        fetchWithTimeout(
          "../api/projects?group=" + encodeURIComponent(group),
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
    const projects = normalizePayload(json);
    if (projects.length) {
      populateSelect(projects, "project");
    } else {
      populateSelect([], "project");
    }
  } catch (error) {
    console.error("Failed to fetch projects:", error);
    if ($sel.length) {
      $sel.empty().append('<option value="">Failed to load projects</option>');
    }
    return [];
  } finally {
    if ($sel.length) {
      $sel.prop("disabled", false);
    }
  }
}
