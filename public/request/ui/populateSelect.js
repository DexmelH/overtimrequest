const LABELS = {
  group: "Group",
  location: "Location",
  project: "Project",
  item: "Item",
  jobdesc: "Job Description",
  work: "Work Type",
};

export function populateSelect(items, fieldId, { preserveValue = false } = {}) {
  const $sel = $(`#${fieldId}`);
  if (!$sel.length) return;

  const current = preserveValue ? $sel.val() : "";
  const label = LABELS[fieldId] || fieldId;

  $sel.empty().append(`<option value="">Select ${label}</option>`);

  (items || []).forEach((item) => {
    $sel.append($("<option>").attr("value", item.id).text(item.name));
  });

  if (preserveValue && current && $sel.find(`option[value="${current}"]`).length) {
    $sel.val(current);
  }
}

export function resetSelect(fieldId, disabled = true) {
  const $sel = $(`#${fieldId}`);
  if (!$sel.length) return;
  const label = LABELS[fieldId] || fieldId;
  $sel
    .empty()
    .append(`<option value="">Select ${label}</option>`)
    .prop("disabled", disabled);
}
