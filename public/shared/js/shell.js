import { apiUrl } from "./api.js";
import { apiGet } from "./http.js";

const THEME_KEY = "ot-theme";

const NAV_PAGES = [
  { id: "request", label: "Request", icon: "bi-clock-history", href: "../request/" },
  { id: "approve", label: "Approve", icon: "bi-check2-square", href: "../approve/" },
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

function renderNav(currentPage) {
  const nav = document.getElementById("otNav");
  if (!nav) return;

  nav.innerHTML = NAV_PAGES.map((page) => {
    const active = page.id === currentPage ? " active" : "";
    const adminClass = page.adminOnly ? ' data-admin-only="true"' : "";
    return `<a class="ot-nav-link${active}" href="${page.href}"${adminClass}>
      <i class="bi ${page.icon}" aria-hidden="true"></i>
      <span>${page.label}</span>
    </a>`;
  }).join("");

  if (currentPage === "admin") {
    nav.querySelectorAll('[data-admin-only="true"]').forEach((el) => {
      el.classList.add("is-visible");
    });
  }
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

async function revealAdminNav() {
  try {
    const json = await apiGet(apiUrl("/admin/session"));
    if (json?.is_admin) {
      document.querySelectorAll('[data-admin-only="true"]').forEach((el) => {
        el.classList.add("is-visible");
      });
    }
  } catch (e) {
    /* not admin or unreachable */
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
  renderNav(currentPage);
  renderThemeToggle();
  revealAdminNav();
  staggerCards();

  requestAnimationFrame(() => {
    document.body.classList.add("ot-ready");
  });
}
