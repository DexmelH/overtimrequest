import {
  retryFetch,
  fetchWithTimeout,
  normalizePayload,
} from "../components/utilities.js";
import { populateSelect } from "../ui/populateSelect.js";

export async function fetchJobs({ showLoading = true } = {}) {
  const $sel = $("#jobdesc");
  if ($sel.length && showLoading) {
    $sel.prop("disabled", true);
    const prev = $sel.data("prev") || null;
    $sel.data("prev-text", prev);
    $sel.empty().append('<option value="">Loading...</option>');
  }

  const item = $("#item").val();

  try {
    const response = await retryFetch(
      () =>
        fetchWithTimeout(
          "../api/jobs?item=" + encodeURIComponent(item),
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
    const jobs = normalizePayload(json);
    if (jobs.length) {
      populateSelect(jobs, "jobdesc");
    } else {
      populateSelect([], "jobdesc");
    }
  } catch (error) {
    console.error("Failed to fetch jobs:", error);
    if ($sel.length) {
      $sel.empty().append('<option value="">Failed to load jobs</option>');
    }
    return [];
  } finally {
    if ($sel.length) {
      $sel.prop("disabled", false);
    }
  }
}
