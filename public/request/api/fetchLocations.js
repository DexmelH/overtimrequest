import { apiUrl } from "../../shared/js/api.js";
import { apiGet, normalizePayload } from "../../shared/js/http.js";
import { populateSelect } from "../ui/populateSelect.js";
import { $formField, getFieldId } from "../ui/formFields.js";

export async function fetchLocations() {
  const $sel = $formField("location");
  $sel.prop("disabled", true).empty().append('<option value="">Loading...</option>');

  try {
    const json = await apiGet(apiUrl("/locations"));
    populateSelect(normalizePayload(json), getFieldId("location"));
  } catch (error) {
    console.error("Failed to fetch locations:", error);
    $sel.empty().append('<option value="">Failed to load locations</option>');
  } finally {
    $sel.prop("disabled", false);
  }
}
