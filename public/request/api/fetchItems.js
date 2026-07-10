import { apiUrl } from "../../shared/js/api.js";
import { apiGet, normalizePayload } from "../../shared/js/http.js";
import { populateSelect } from "../ui/populateSelect.js";
import { $formField, getFieldId } from "../ui/formFields.js";

export async function fetchItems() {
  const $sel = $formField("item");
  const project = $formField("project").val();
  $sel.prop("disabled", true).empty().append('<option value="">Loading...</option>');

  try {
    const json = await apiGet(
      apiUrl("/items") + "?project=" + encodeURIComponent(project),
    );
    populateSelect(normalizePayload(json), getFieldId("item"));
  } catch (error) {
    console.error("Failed to fetch items:", error);
    $sel.empty().append('<option value="">Failed to load items</option>');
  } finally {
    $sel.prop("disabled", false);
  }
}
