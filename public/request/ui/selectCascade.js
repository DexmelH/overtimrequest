import { resetSelect } from "./populateSelect.js";

const CASCADE = ["project", "item", "jobdesc", "work"];

export function resetDependentFields(fromField = "project") {
  const start = CASCADE.indexOf(fromField);
  const fields = start >= 0 ? CASCADE.slice(start) : CASCADE;
  fields.forEach((id, i) => resetSelect(id, true));
}

export function enableField(fieldId) {
  $(`#${fieldId}`).prop("disabled", false);
}
