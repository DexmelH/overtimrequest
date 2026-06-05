import { apiUrl } from "../../shared/js/api.js";
import { apiGet, normalizePayload } from "../../shared/js/http.js";
import { populateSelect } from "../ui/populateSelect.js";

export async function fetchGroups() {
  const $sel = $("#group");
  $sel.prop("disabled", true).empty().append('<option value="">Loading...</option>');

  try {
    const json = await apiGet(apiUrl("/groups"));
    populateSelect(normalizePayload(json), "group");
  } catch (error) {
    console.error("Failed to fetch groups:", error);
    $sel.empty().append('<option value="">Failed to load groups</option>');
  } finally {
    $sel.prop("disabled", false);
  }
}
