const DEFAULT_FIELD_IDS = {
  group: "group",
  location: "location",
  project: "project",
  item: "item",
  jobdesc: "jobdesc",
  work: "work",
};

let fieldIds = { ...DEFAULT_FIELD_IDS };

export function configureFormFields(overrides = {}) {
  fieldIds = { ...DEFAULT_FIELD_IDS, ...overrides };
}

export function getFieldId(name) {
  return fieldIds[name] || name;
}

export function $formField(name) {
  return $(`#${getFieldId(name)}`);
}

export function selectedGroupLabel() {
  const $option = $formField("group").find("option:selected");
  return $option.data("abbr") || $option.text();
}
