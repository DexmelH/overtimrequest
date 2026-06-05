/**
 * Resolves API base URL for pages under /overtime/public/* or /overtime/*
 */
export function getApiBase() {
  const meta = document.querySelector('meta[name="api-base"]');
  if (meta?.content) {
    return meta.content.replace(/\/$/, "");
  }
  const path = window.location.pathname;
  const publicIdx = path.indexOf("/public/");
  if (publicIdx >= 0) {
    return path.slice(0, publicIdx) + "/api";
  }
  const segments = path.split("/").filter(Boolean);
  if (segments.length > 0) {
    return "/" + segments[0] + "/api";
  }
  return "/api";
}

export function apiUrl(endpoint) {
  const base = getApiBase();
  const path = endpoint.startsWith("/") ? endpoint : "/" + endpoint;
  return base + path;
}
