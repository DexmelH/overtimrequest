import { capitalizeFirst } from "../components/utilities.js";

export function populateSelect(locations, text, { preserveValue = true } = {}) {
  const $sel = $(`#${text}`);
  if ($sel.length === 0) return;
  const current = preserveValue ? $sel.val() : null;
  console.log(current);
  $sel.empty();
  $sel.append(
    `<option selected disabled value="">Select ${capitalizeFirst(text)}</option>`,
  );
  locations.forEach((loc) => {
    const opt = $("<option>").attr("value", loc.id).text(loc.name);
    $sel.append(opt);
  });
  if (preserveValue && current) {
    if ($sel.find(`option[value="${current}"]`).length) $sel.val(current);
  }
}
