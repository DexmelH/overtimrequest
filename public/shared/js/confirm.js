/**
 * Modern promise-based confirmation dialog (replaces window.confirm).
 */
let dialogEl = null;
let resolvePending = null;
let onRemoveEmployeeHandler = null;

const VARIANTS = {
  danger: {
    icon: "bi-exclamation-triangle-fill",
    iconBg: "rgba(220, 38, 38, 0.12)",
    iconColor: "var(--ot-danger)",
    btnClass: "ot-btn-danger",
  },
  success: {
    icon: "bi-check-circle-fill",
    iconBg: "rgba(22, 163, 74, 0.12)",
    iconColor: "var(--ot-success)",
    btnClass: "ot-btn-success",
  },
  primary: {
    icon: "bi-question-circle-fill",
    iconBg: "var(--ot-accent-soft)",
    iconColor: "var(--ot-accent)",
    btnClass: "ot-btn-primary",
  },
  warning: {
    icon: "bi-exclamation-circle-fill",
    iconBg: "rgba(217, 119, 6, 0.12)",
    iconColor: "var(--ot-warning)",
    btnClass: "ot-btn-warning",
  },
};

function ensureDialog() {
  if (dialogEl) return dialogEl;

  document.body.insertAdjacentHTML(
    "beforeend",
    `
    <div id="otConfirmDialog" class="ot-confirm" aria-hidden="true" role="alertdialog" aria-modal="true" aria-labelledby="otConfirmTitle">
      <div class="ot-confirm-backdrop" data-ot-confirm-dismiss></div>
      <div class="ot-confirm-panel" tabindex="-1">
        <div class="ot-confirm-icon" id="otConfirmIcon" aria-hidden="true">
          <i class="bi"></i>
        </div>
        <h3 class="ot-confirm-title" id="otConfirmTitle"></h3>
        <p class="ot-confirm-message" id="otConfirmMessage"></p>
        <div class="ot-confirm-detail" id="otConfirmDetail" hidden></div>
        <div class="ot-confirm-actions">
          <button type="button" class="ot-btn ot-btn-secondary" id="otConfirmCancel">Cancel</button>
          <button type="button" class="ot-btn" id="otConfirmOk">Confirm</button>
        </div>
      </div>
    </div>
    `,
  );

  dialogEl = document.getElementById("otConfirmDialog");

  dialogEl.querySelector("[data-ot-confirm-dismiss]").addEventListener("click", () => close(false));
  document.getElementById("otConfirmCancel").addEventListener("click", () => close(false));
  document.getElementById("otConfirmOk").addEventListener("click", () => close(true));

  document.getElementById("otConfirmDetail").addEventListener("click", (e) => {
    const btn = e.target.closest("[data-ot-confirm-remove]");
    if (!btn || !dialogEl?.contains(btn)) return;
    e.preventDefault();
    const employeeId = btn.getAttribute("data-employee-id");
    if (employeeId == null || employeeId === "") return;
    onRemoveEmployeeHandler?.(employeeId);
  });

  document.addEventListener("keydown", (e) => {
    if (!dialogEl?.classList.contains("show")) return;
    if (e.key === "Escape") {
      e.preventDefault();
      close(false);
    }
  });

  return dialogEl;
}

function getDetailEl() {
  let detailEl = document.getElementById("otConfirmDetail");
  if (!detailEl) {
    detailEl = document.createElement("div");
    detailEl.id = "otConfirmDetail";
    detailEl.className = "ot-confirm-detail";
    const messageEl = document.getElementById("otConfirmMessage");
    messageEl.insertAdjacentElement("afterend", detailEl);
  }
  return detailEl;
}

function applyDetailHtml(detailHtml) {
  const detailEl = getDetailEl();
  const html = String(detailHtml || "").trim();
  if (html) {
    detailEl.innerHTML = html;
    detailEl.hidden = false;
  } else {
    detailEl.innerHTML = "";
    detailEl.hidden = true;
  }
}

function applyPanelSize(size) {
  const panel = dialogEl?.querySelector(".ot-confirm-panel");
  if (!panel) return;
  panel.classList.toggle("ot-confirm-panel--lg", size === "lg");
}

/**
 * Close the open confirm dialog (if any).
 * @param {boolean} [result=false]
 */
export function closeConfirm(result = false) {
  close(Boolean(result));
}

function close(result) {
  if (!dialogEl) return;
  dialogEl.classList.remove("show");
  dialogEl.setAttribute("aria-hidden", "true");
  document.body.style.overflow = "";
  onRemoveEmployeeHandler = null;
  applyPanelSize("default");

  const resolver = resolvePending;
  resolvePending = null;
  resolver?.(result);
}

/**
 * Refresh title / message / detail on an already-open confirm dialog.
 * @param {{ title?: string, message?: string, detailHtml?: string, size?: 'default'|'lg' }} options
 */
export function updateConfirmContent({
  title,
  message,
  detailHtml,
  size,
} = {}) {
  if (!dialogEl?.classList.contains("show")) return;

  if (title !== undefined) {
    document.getElementById("otConfirmTitle").textContent = title;
  }
  if (message !== undefined) {
    document.getElementById("otConfirmMessage").textContent = message;
    document.getElementById("otConfirmMessage").style.display = message
      ? "block"
      : "none";
  }
  if (detailHtml !== undefined) {
    applyDetailHtml(detailHtml);
    if (size === undefined) {
      applyPanelSize(String(detailHtml || "").trim() ? "lg" : "default");
    }
  }
  if (size !== undefined) {
    applyPanelSize(size);
  }
}

/**
 * @param {object} options
 * @param {string} options.title
 * @param {string} [options.message]
 * @param {string} [options.detailHtml] Optional HTML under the message (e.g. OT totals list)
 * @param {'default'|'lg'} [options.size]
 * @param {(employeeId: string) => void} [options.onRemoveEmployee]
 * @param {string} [options.confirmText]
 * @param {string} [options.cancelText]
 * @param {'danger'|'success'|'primary'|'warning'} [options.variant]
 * @param {string} [options.icon] Bootstrap icon class without bi- prefix optional full bi- class
 * @returns {Promise<boolean>}
 */
export function confirmAction({
  title,
  message = "",
  detailHtml = "",
  size,
  onRemoveEmployee,
  confirmText = "Confirm",
  cancelText = "Cancel",
  variant = "primary",
  icon,
} = {}) {
  return new Promise((resolve) => {
    const el = ensureDialog();
    const v = VARIANTS[variant] || VARIANTS.primary;
    const iconEl = document.getElementById("otConfirmIcon");
    const iconI = iconEl.querySelector("i");

    iconI.className = "bi " + (icon ? (icon.startsWith("bi-") ? icon : "bi-" + icon) : v.icon);

    iconEl.style.background = v.iconBg;
    iconEl.style.color = v.iconColor;

    document.getElementById("otConfirmTitle").textContent = title;
    document.getElementById("otConfirmMessage").textContent = message;
    document.getElementById("otConfirmMessage").style.display = message ? "block" : "none";

    applyDetailHtml(detailHtml);
    const resolvedSize =
      size || (String(detailHtml || "").trim() ? "lg" : "default");
    applyPanelSize(resolvedSize);

    onRemoveEmployeeHandler =
      typeof onRemoveEmployee === "function" ? onRemoveEmployee : null;

    const $ok = document.getElementById("otConfirmOk");
    $ok.textContent = confirmText;
    $ok.className = "ot-btn " + v.btnClass;

    document.getElementById("otConfirmCancel").textContent = cancelText;

    resolvePending = resolve;
    el.classList.add("show");
    el.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    el.querySelector(".ot-confirm-panel").focus();
  });
}
