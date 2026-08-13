const LABELS = {
  group: "Group",
  location: "Location",
  obGroup: "Group",
  obLocation: "Location",
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
