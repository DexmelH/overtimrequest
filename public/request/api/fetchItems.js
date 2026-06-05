import { apiUrl } from "../../shared/js/api.js";
import { apiGet, normalizePayload } from "../../shared/js/http.js";
import { populateSelect } from "../ui/populateSelect.js";

export async function fetchItems() {
  const $sel = $("#item");
  const project = $("#project").val();
  $sel.prop("disabled", true).empty().append('<option value="">Loading...</option>');

  try {
    const json = await apiGet(
      apiUrl("/items") + "?project=" + encodeURIComponent(project),
    );
    populateSelect(normalizePayload(json), "item");
  } catch (error) {
    console.error("Failed to fetch items:", error);
    $sel.empty().append('<option value="">Failed to load items</option>');
  } finally {
    $sel.prop("disabled", false);
  }
}
