/**
 * Modern promise-based confirmation dialog (replaces window.confirm).
 */
let dialogEl = null;
let resolvePending = null;

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

  document.addEventListener("keydown", (e) => {
    if (!dialogEl?.classList.contains("show")) return;
    if (e.key === "Escape") {
      e.preventDefault();
      close(false);
    }
  });

  return dialogEl;
}

function close(result) {
  if (!dialogEl) return;
  dialogEl.classList.remove("show");
  dialogEl.setAttribute("aria-hidden", "true");
  document.body.style.overflow = "";

  const resolver = resolvePending;
  resolvePending = null;
  resolver?.(result);
}

/**
 * @param {object} options
 * @param {string} options.title
 * @param {string} [options.message]
 * @param {string} [options.confirmText]
 * @param {string} [options.cancelText]
 * @param {'danger'|'success'|'primary'|'warning'} [options.variant]
 * @param {string} [options.icon] Bootstrap icon class without bi- prefix optional full bi- class
 * @returns {Promise<boolean>}
 */
export function confirmAction({
  title,
  message = "",
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
