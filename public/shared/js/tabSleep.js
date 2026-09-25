/**
 * Live-indicator rest while the page is in the background.
 * The tab favicon and title stay the same.
 */
function faviconActiveUrl() {
  return document.querySelector('meta[name="ot-favicon-active"]')?.content || "";
}

function faviconLink() {
  let link = document.getElementById("otFavicon");
  if (link) return link;
  link = document.createElement("link");
  link.id = "otFavicon";
  link.rel = "icon";
  link.type = "image/svg+xml";
  document.head.appendChild(link);
  return link;
}

function setAsleep(asleep) {
  document.body.classList.toggle("ot-tab-asleep", asleep);

  const live = document.getElementById("listUpdated");
  if (live) {
    if (asleep) {
      if (!live.dataset.awakeText) {
        live.dataset.awakeText = live.textContent || "Live";
      }
      live.textContent = "Sleeping";
    } else if (live.dataset.awakeText) {
      live.textContent = live.dataset.awakeText;
    }
  }
}

function onVisibility() {
  setAsleep(document.hidden);
}

export function initTabSleep() {
  const active = faviconActiveUrl();
  if (active) {
    faviconLink().href = active;
  }
  document.addEventListener("visibilitychange", onVisibility);
  setAsleep(document.hidden);
}
