import { apiUrl } from "./api.js";
import { apiGet } from "./http.js";

const THEME_KEY = "ot-theme";

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

function renderNav(currentPage) {
  const nav = document.getElementById("otNav");
  if (!nav) return;

  nav.innerHTML = NAV_PAGES.map((page) => {
    const active = page.id === currentPage ? " active" : "";
    const attrs = [];
    if (page.adminOnly) attrs.push('data-admin-only="true"');
    if (page.approverOnly) attrs.push('data-approver-only="true"');
    const attrStr = attrs.length ? ` ${attrs.join(" ")}` : "";
    return `<a class="ot-nav-link${active}" href="${page.href}"${attrStr}>
      <i class="bi ${page.icon}" aria-hidden="true"></i>
      <span>${page.label}</span>
    </a>`;
  }).join("");

  if (currentPage === "admin") {
    nav.querySelectorAll('[data-admin-only="true"]').forEach((el) => {
      el.classList.add("is-visible");
    });
  }

  if (currentPage === "approve") {
    nav.querySelectorAll('[data-approver-only="true"]').forEach((el) => {
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

function ensureUserGreeting() {
  if (document.getElementById("otUserGreeting")) return;

  const greeting = document.createElement("p");
  greeting.id = "otUserGreeting";
  greeting.className = "ot-user-greeting d-none";
  greeting.setAttribute("aria-live", "polite");
  document.body.appendChild(greeting);
}

async function loadSession() {
  try {
    const json = await apiGet(apiUrl("/session"));
    const name = String(json?.user?.name || "").trim();
    const greeting = document.getElementById("otUserGreeting");

    if (name && greeting) {
      greeting.textContent = `Hello, ${name}`;
      greeting.classList.remove("d-none");
    }

    if (json?.is_approver) {
      document.querySelectorAll('[data-approver-only="true"]').forEach((el) => {
        el.classList.add("is-visible");
      });
    }

    if (json?.is_admin) {
      document.querySelectorAll('[data-admin-only="true"]').forEach((el) => {
        el.classList.add("is-visible");
      });
    }
  } catch {
    /* session unavailable */
  }
}

async function revealAdminNav() {
  await loadSession();
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
  ensureUserGreeting();
  revealAdminNav();
  staggerCards();

  requestAnimationFrame(() => {
    document.body.classList.add("ot-ready");
  });
}
