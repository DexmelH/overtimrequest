import { apiUrl } from "../../shared/js/api.js";
import { apiGet, normalizePayload } from "../../shared/js/http.js";
import { populateSelect } from "../ui/populateSelect.js";

export async function fetchProjects() {
  const $sel = $("#project");
  const group = $("#group option:selected").text();
  $sel.prop("disabled", true).empty().append('<option value="">Loading...</option>');

  try {
    const json = await apiGet(
      apiUrl("/projects") + "?group=" + encodeURIComponent(group),
    );
    populateSelect(normalizePayload(json), "project");
  } catch (error) {
    console.error("Failed to fetch projects:", error);
    $sel.empty().append('<option value="">Failed to load projects</option>');
  } finally {
    $sel.prop("disabled", false);
  }
}
