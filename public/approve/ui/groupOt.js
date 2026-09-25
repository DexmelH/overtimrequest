import { escapeHtml } from "../../shared/js/escapeHtml.js";

const ALL_VALUE = "all";

let lastPayload = null;
let selectedValue = ALL_VALUE;

function groupLabel(group) {
  return group?.abbreviation || group?.name || "Group";
}

function selectedHours(payload) {
  const groups = Array.isArray(payload?.groups) ? payload.groups : [];
  if (groups.length === 1) {
    return groups[0].duration_label || "0 min";
  }
  if (selectedValue === ALL_VALUE) {
    return payload?.total_label || "0 min";
  }
  const match = groups.find((group) => String(group.id) === String(selectedValue));
  return match?.duration_label || "0 min";
}

function selectedAbbr(payload) {
  const groups = Array.isArray(payload?.groups) ? payload.groups : [];
  if (groups.length === 1) {
    return groupLabel(groups[0]);
  }
  if (selectedValue === ALL_VALUE) {
    return "All groups";
  }
  const match = groups.find((group) => String(group.id) === String(selectedValue));
  return match ? groupLabel(match) : "All groups";
}

function setOpen(open) {
  const $trigger = $("#statGroupOtTrigger");
  const $menu = $("#statGroupOtMenu");
  const $wrap = $("#statGroupOtWrap");
  $trigger.attr("aria-expanded", open ? "true" : "false");
  $menu.toggleClass("d-none", !open);
  $wrap.toggleClass("is-picking", open);
}

function isOpen() {
  return $("#statGroupOtTrigger").attr("aria-expanded") === "true";
}

function optionRow(value, label, hours, selected) {
  return `
    <button
      type="button"
      class="stat-group-option${selected ? " is-selected" : ""}"
      role="option"
      aria-selected="${selected ? "true" : "false"}"
      data-group="${escapeHtml(value)}"
    >
      <span class="stat-group-option-name">${escapeHtml(label)}</span>
      <span class="stat-group-option-hours">${escapeHtml(hours || "0 min")}</span>
    </button>
  `;
}

function renderMenu(payload) {
  const groups = Array.isArray(payload?.groups) ? payload.groups : [];
  const previous = selectedValue;
  const stillValid =
    previous === ALL_VALUE ||
    groups.some((group) => String(group.id) === String(previous));
  selectedValue = stillValid ? previous : ALL_VALUE;

  const rows = [
    optionRow(ALL_VALUE, "All groups", payload?.total_label, selectedValue === ALL_VALUE),
    ...groups.map((group) =>
      optionRow(
        String(group.id),
        groupLabel(group),
        group.duration_label,
        String(group.id) === String(selectedValue),
      ),
    ),
  ];
  $("#statGroupOtMenu").html(rows.join(""));
  $("#statGroupOtTriggerLabel").text(selectedAbbr(payload));
}

function applySelection() {
  if (!lastPayload) return;
  $("#statGroupOtHours").text(selectedHours(lastPayload));
  $("#statGroupOtTriggerLabel").text(selectedAbbr(lastPayload));
  renderMenu(lastPayload);
}

export function renderGroupOt(payload) {
  lastPayload = payload;
  const groups = Array.isArray(payload?.groups) ? payload.groups : [];
  const $wrap = $("#statGroupOtWrap");
  const $trigger = $("#statGroupOtTrigger");
  const $abbr = $("#statGroupOtAbbr");

  if (!groups.length) {
    setOpen(false);
    $wrap.addClass("d-none");
    return;
  }

  $wrap.removeClass("d-none");
  $("#statGroupOtHours").text(selectedHours(payload));

  if (groups.length === 1) {
    setOpen(false);
    $trigger.addClass("d-none");
    $abbr.removeClass("d-none").text(groupLabel(groups[0]));
    return;
  }

  $abbr.addClass("d-none");
  $trigger.removeClass("d-none");
  renderMenu(payload);
}

export function initGroupOt() {
  $("#statGroupOtTrigger").on("click", function (event) {
    event.preventDefault();
    event.stopPropagation();
    setOpen(!isOpen());
  });

  $("#statGroupOtMenu").on("click", ".stat-group-option", function (event) {
    event.preventDefault();
    selectedValue = String($(this).data("group") || ALL_VALUE);
    applySelection();
    setOpen(false);
  });

  $(document).on("click.statGroupOt", function (event) {
    if (!isOpen()) return;
    if ($(event.target).closest("#statGroupOtPicker").length) return;
    setOpen(false);
  });

  $(document).on("keydown.statGroupOt", function (event) {
    if (event.key === "Escape" && isOpen()) {
      setOpen(false);
      $("#statGroupOtTrigger").trigger("focus");
    }
  });
}
