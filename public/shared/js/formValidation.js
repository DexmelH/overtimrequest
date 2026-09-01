/**
 * Red highlight for blank/invalid required fields.
 * Uses Bootstrap's `is-invalid` class, restyled to match the OT theme.
 */

const INVALID_CLASS = "is-invalid";

function toElements(target) {
  if (!target) return [];
  if (target.jquery) return target.toArray();
  if (typeof target === "string") {
    return Array.from(document.querySelectorAll(target));
  }
  if (target instanceof Element) return [target];
  if (Array.isArray(target) || NodeList.prototype.isPrototypeOf(target)) {
    return Array.from(target);
  }
  return [];
}

export function markFieldInvalid(target) {
  toElements(target).forEach((el) => {
    el.classList.add(INVALID_CLASS);
    el.setAttribute("aria-invalid", "true");
  });
}

export function clearFieldInvalid(target) {
  toElements(target).forEach((el) => {
    el.classList.remove(INVALID_CLASS);
    el.removeAttribute("aria-invalid");
  });
}

export function clearInvalidIn(root) {
  const scope =
    typeof root === "string"
      ? document.querySelector(root)
      : root?.jquery
        ? root[0]
        : root instanceof Element
          ? root
          : document;
  if (!scope) return;
  clearFieldInvalid(scope.querySelectorAll(`.${INVALID_CLASS}`));
}

/**
 * Mark a field invalid when its value is blank (after trim).
 * @returns {boolean} true when the field is valid (non-empty)
 */
export function requireFilled(target) {
  const elements = toElements(target);
  let allOk = true;
  elements.forEach((el) => {
    const value = String(el.value ?? "").trim();
    if (!value) {
      markFieldInvalid(el);
      allOk = false;
    } else {
      clearFieldInvalid(el);
    }
  });
  return allOk;
}

/**
 * Focus the first invalid control in a container (or the document).
 */
export function focusFirstInvalid(root) {
  const scope =
    typeof root === "string"
      ? document.querySelector(root)
      : root?.jquery
        ? root[0]
        : root instanceof Element
          ? root
          : document;
  const first = scope?.querySelector?.(`.${INVALID_CLASS}`);
  if (first && typeof first.focus === "function") {
    first.focus({ preventScroll: false });
  }
}

/**
 * Clear the red highlight as soon as the user edits a field.
 * Call once per form/modal root.
 */
export function bindClearInvalidOnEdit(root) {
  const $root = root ? $(root) : $(document);
  $root.on("input change", `.${INVALID_CLASS}, .form-control, .form-select`, function () {
    if (!this.classList.contains(INVALID_CLASS)) return;
    const value = String(this.value ?? "").trim();
    if (value) {
      clearFieldInvalid(this);
    }
  });
}
