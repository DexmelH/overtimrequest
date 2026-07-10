import { resetSelect } from "./populateSelect.js";
import { getFieldId } from "./formFields.js";

const CASCADE = ["project", "item", "jobdesc", "work"];

export function resetDependentFields(fromField = "project") {
  const start = CASCADE.indexOf(fromField);
  const fields = start >= 0 ? CASCADE.slice(start) : CASCADE;
  fields.forEach((name) => resetSelect(getFieldId(name), true));
}

export function enableField(fieldName) {
  $(`#${getFieldId(fieldName)}`).prop("disabled", false);
}
