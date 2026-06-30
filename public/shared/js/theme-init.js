(function () {
  try {
    var saved = localStorage.getItem("ot-theme");
    var dark =
      saved === "dark" ||
      (saved !== "light" && window.matchMedia("(prefers-color-scheme: dark)").matches);
    if (dark) {
      document.documentElement.setAttribute("data-theme", "dark");
    }
    var meta = document.querySelector('meta[name="theme-color"]');
    if (!meta) {
      meta = document.createElement("meta");
      meta.name = "theme-color";
      document.head.appendChild(meta);
    }
    meta.content = dark ? "#0b1220" : "#eef2ff";
  } catch (e) {}
})();
