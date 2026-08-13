const DEFAULT_FIELD_IDS = {
  group: "group",
  location: "location",
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
