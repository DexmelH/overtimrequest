export function showToast(message, { type = "default", duration = 4200 } = {}) {
  try {
    const container = document.getElementById("toastContainer");
    if (!container) return alert(message);

    const toast = document.createElement("div");
    const typeClass =
      type === "success"
        ? "success"
        : type === "error"
          ? "error"
          : type === "warning"
            ? "warning"
            : "";
    toast.className = "ot-toast " + typeClass;
    toast.setAttribute("role", "status");
    toast.innerHTML = `<div class="ot-toast-msg">${escapeHtml(String(message))}</div>
      <button class="ot-toast-close" type="button" aria-label="Close">&times;</button>`;

    toast.querySelector(".ot-toast-close").addEventListener("click", () => hideToast(toast));
    container.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add("show"));

    const t = setTimeout(() => hideToast(toast), duration);

    function hideToast(el) {
      clearTimeout(t);
      el.classList.remove("show");
      el.addEventListener(
        "transitionend",
        () => el.parentNode?.removeChild(el),
        { once: true },
      );
    }
    return toast;
  } catch (err) {
    console.warn("showToast error", err);
    alert(message);
  }
}

function escapeHtml(str) {
  return String(str ?? "").replace(/[&<>"']/g, (m) =>
    ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[m],
  );
}
