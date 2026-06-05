import { apiUrl } from "../../shared/js/api.js";
import { apiGet, normalizePayload } from "../../shared/js/http.js";
import { populateSelect } from "../ui/populateSelect.js";

export async function fetchWorks() {
  const $sel = $("#work");
  $sel.prop("disabled", true).empty().append('<option value="">Loading...</option>');

  try {
    const json = await apiGet(apiUrl("/works"));
    populateSelect(normalizePayload(json), "work");
  } catch (error) {
    console.error("Failed to fetch works:", error);
    $sel.empty().append('<option value="">Failed to load work types</option>');
  } finally {
    $sel.prop("disabled", false);
  }
}
