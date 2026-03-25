export function showToast(message, { type = "default", duration = 4000 } = {}) {
  try {
    const container = document.getElementById("toastContainer");
    if (!container) return alert(message); // fallback

    const toast = document.createElement("div");
    toast.className =
      "toast " +
      (type === "success" ? "success" : type === "error" ? "error" : "");
    toast.setAttribute("role", "status");
    toast.innerHTML = `<div class="toast-msg">${escapeHtml(String(message))}</div>
                       <button class="close-btn" aria-label="Close">×</button>`;

    // close handler
    toast.querySelector(".close-btn").addEventListener("click", () => {
      hideToast(toast);
    });

    container.appendChild(toast);

    // show animation
    requestAnimationFrame(() => toast.classList.add("show"));

    // auto remove
    const t = setTimeout(() => hideToast(toast), duration);

    // remove function
    function hideToast(el) {
      clearTimeout(t);
      el.classList.remove("show");
      el.addEventListener(
        "transitionend",
        () => {
          if (el.parentNode) el.parentNode.removeChild(el);
        },
        { once: true },
      );
    }

    return toast;
  } catch (err) {
    // last resort
    console.warn("showToast error", err);
    alert(message);
  }
}

export function escapeHtml(str) {
  return String(str === undefined || str === null ? "" : str).replace(
    /[&<>"']/g,
    function (m) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[m];
    },
  );
}
