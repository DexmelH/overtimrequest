import { apiUrl } from "./api.js";
import { apiGet } from "./http.js";

const THEME_KEY = "ot-theme";
const SIDEBAR_OPEN_CLASS = "ot-sidebar-open";

const NAV_PAGES = [
  { id: "request", label: "Request", icon: "bi-clock-history", href: "../request/" },
  { id: "approve", label: "Approve", icon: "bi-check2-square", href: "../approve/", approverOnly: true },
  { id: "admin", label: "Admin", icon: "bi-shield-lock", href: "../admin/", adminOnly: true },
];

export function getTheme() {
  return document.documentElement.getAttribute("data-theme") === "dark" ? "dark" : "light";
}

export function setTheme(theme) {
  const isDark = theme === "dark";
  document.documentElement.setAttribute("data-theme", isDark ? "dark" : "light");
  try {
    localStorage.setItem(THEME_KEY, isDark ? "dark" : "light");
  } catch (e) {
    /* ignore */
  }
  updateThemeMeta(isDark);
  syncThemeToggle();
}

function updateThemeMeta(isDark) {
  let meta = document.querySelector('meta[name="theme-color"]');
  if (!meta) {
    meta = document.createElement("meta");
    meta.name = "theme-color";
    document.head.appendChild(meta);
  }
  meta.content = isDark ? "#0b1220" : "#eef2ff";
}

export function toggleTheme() {
  setTheme(getTheme() === "dark" ? "light" : "dark");
}

function syncThemeToggle() {
  const btn = document.getElementById("otThemeToggle");
  if (!btn) return;
  const dark = getTheme() === "dark";
  btn.setAttribute("aria-pressed", dark ? "true" : "false");
  btn.title = dark ? "Switch to light mode" : "Switch to dark mode";
  btn.innerHTML = dark
    ? '<i class="bi bi-sun-fill" aria-hidden="true"></i><span class="d-none d-sm-inline">Light</span>'
    : '<i class="bi bi-moon-stars-fill" aria-hidden="true"></i><span class="d-none d-sm-inline">Dark</span>';
}

function renderNav(currentPage, { isApprover = false, isAdmin = false } = {}) {
  const nav = document.getElementById("otNav");
  if (!nav) return;

  // Omit role-gated links until session confirms access (no CSS-only hide).
  const pages = NAV_PAGES.filter((page) => {
    if (page.approverOnly && !isApprover) return false;
    if (page.adminOnly && !isAdmin) return false;
    return true;
  });

  nav.innerHTML = pages
    .map((page) => {
      const active = page.id === currentPage ? " active" : "";
      return `<a class="ot-sidebar-link${active}" href="${page.href}">
      <i class="bi ${page.icon}" aria-hidden="true"></i>
      <span>${page.label}</span>
    </a>`;
    })
    .join("");
}

function renderThemeToggle() {
  const tools = document.getElementById("otHeaderTools");
  if (!tools || document.getElementById("otThemeToggle")) return;

  const btn = document.createElement("button");
  btn.type = "button";
  btn.className = "ot-theme-toggle";
  btn.id = "otThemeToggle";
  btn.setAttribute("aria-label", "Toggle color theme");
  btn.addEventListener("click", toggleTheme);
  tools.appendChild(btn);
  syncThemeToggle();
}

function ensureUserGreeting() {
  if (document.getElementById("otUserGreeting")) return;

  const tools = document.getElementById("otHeaderTools");
  if (!tools) return;

  const greeting = document.createElement("p");
  greeting.id = "otUserGreeting";
  greeting.className = "ot-user-greeting d-none";
  greeting.setAttribute("aria-live", "polite");
  tools.prepend(greeting);
}

function setSidebarOpen(open) {
  const shell = document.querySelector(".ot-shell");
  const toggle = document.getElementById("otSidebarToggle");
  const backdrop = document.getElementById("otSidebarBackdrop");
  if (!shell) return;

  shell.classList.toggle(SIDEBAR_OPEN_CLASS, open);
  document.body.classList.toggle(SIDEBAR_OPEN_CLASS, open);

  if (toggle) {
    toggle.setAttribute("aria-expanded", open ? "true" : "false");
    toggle.setAttribute("aria-label", open ? "Close navigation" : "Open navigation");
    const icon = toggle.querySelector("i");
    if (icon) {
      icon.className = open ? "bi bi-x-lg" : "bi bi-list";
    }
  }

  if (backdrop) {
    backdrop.hidden = !open;
  }
}

function initSidebarDrawer() {
  const toggle = document.getElementById("otSidebarToggle");
  const backdrop = document.getElementById("otSidebarBackdrop");
  const nav = document.getElementById("otNav");

  if (toggle) {
    toggle.addEventListener("click", () => {
      const shell = document.querySelector(".ot-shell");
      const isOpen = shell?.classList.contains(SIDEBAR_OPEN_CLASS);
      setSidebarOpen(!isOpen);
    });
  }

  if (backdrop) {
    backdrop.addEventListener("click", () => setSidebarOpen(false));
  }

  if (nav) {
    nav.addEventListener("click", (event) => {
      const link = event.target.closest("a.ot-sidebar-link");
      if (link && window.matchMedia("(max-width: 991.98px)").matches) {
        setSidebarOpen(false);
      }
    });
  }

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    const shell = document.querySelector(".ot-shell");
    if (shell?.classList.contains(SIDEBAR_OPEN_CLASS)) {
      setSidebarOpen(false);
    }
  });

  window.matchMedia("(min-width: 992px)").addEventListener("change", (event) => {
    if (event.matches) setSidebarOpen(false);
  });
}

async function loadSession(currentPage) {
  try {
    const json = await apiGet(apiUrl("/session"));
    const name = String(json?.user?.name || "").trim();
    const greeting = document.getElementById("otUserGreeting");

    if (name && greeting) {
      greeting.textContent = `Hello, ${name}`;
      greeting.classList.remove("d-none");
    }

    renderNav(currentPage, {
      isApprover: Boolean(json?.is_approver),
      isAdmin: Boolean(json?.is_admin),
    });
  } catch {
    /* session unavailable — keep role-gated links hidden */
  }
}

function staggerCards() {
  const cards = document.querySelectorAll(".ot-main .ot-card, .ot-main .ot-stagger-item");
  cards.forEach((card, index) => {
    card.style.setProperty("--ot-stagger", `${Math.min(index * 0.06, 0.36)}s`);
    card.classList.add("ot-animate-in");
  });
}

export function initShell() {
  const currentPage = document.body.dataset.page || "";
  updateThemeMeta(getTheme() === "dark");
  // Hide gated links by default. On approve/admin pages the page gate already
  // ran, so show that section link immediately to avoid a nav flash.
  renderNav(currentPage, {
    isApprover: currentPage === "approve",
    isAdmin: currentPage === "admin",
  });
  renderThemeToggle();
  ensureUserGreeting();
  initSidebarDrawer();
  loadSession(currentPage);
  staggerCards();

  requestAnimationFrame(() => {
    document.body.classList.add("ot-ready");
  });
}
