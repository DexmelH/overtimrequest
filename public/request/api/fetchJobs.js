import { apiUrl } from "../../shared/js/api.js";
import { apiGet, normalizePayload } from "../../shared/js/http.js";
import { populateSelect } from "../ui/populateSelect.js";

export async function fetchJobs() {
  const $sel = $("#jobdesc");
  const item = $("#item").val();
  $sel.prop("disabled", true).empty().append('<option value="">Loading...</option>');

  try {
    const json = await apiGet(apiUrl("/jobs") + "?item=" + encodeURIComponent(item));
    populateSelect(normalizePayload(json), "jobdesc");
  } catch (error) {
    console.error("Failed to fetch jobs:", error);
    $sel.empty().append('<option value="">Failed to load job descriptions</option>');
  } finally {
    $sel.prop("disabled", false);
  }
}
