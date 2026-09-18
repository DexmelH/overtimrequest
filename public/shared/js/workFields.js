import { apiUrl } from "./api.js";
import { apiGet, normalizePayload } from "./http.js";
import { markFieldInvalid, clearFieldInvalid } from "./formValidation.js";
import { formatDuration, toTotalMinutes } from "./formatDuration.js";

const DIM_EXCLUDED_GROUPS = new Set([10, 16]);
const WORK_2D3D_OPTIONS = [
  { value: "2D", title: "2D", subtitle: "AutoCad" },
  { value: "3D", title: "3D", subtitle: "Navis, Video" },
  { value: "2D3D", title: "3D → 2D", subtitle: "" },
  { value: "3D2D", title: "2D → 3D", subtitle: "" },
];

/**
 * Cascading project → item → job → TOW fields with conditional 2D/3D + revision.
 *
 * @param {{
 *   groupSelector: string,
 *   projectId: string,
 *   hoursId: string,
 *   minutesId?: string,
 *   durationSummaryId?: string,
 *   itemId: string,
 *   jobId: string,
 *   towId: string,
 *   towDescId?: string,
 *   dimSectionId: string,
 *   dimCardsId: string,
 *   revisionSectionId: string,
 *   revisionId: string,
 *   employeeIdSelector?: string,
 * }} ids
 */
export function createWorkFields(ids) {
  const $group = $(ids.groupSelector);
  const $project = $(`#${ids.projectId}`);
  const $hours = $(`#${ids.hoursId}`);
  const $minutes = ids.minutesId ? $(`#${ids.minutesId}`) : $();
  const $durationSummary = ids.durationSummaryId
    ? $(`#${ids.durationSummaryId}`)
    : $();
  const $item = $(`#${ids.itemId}`);
  const $job = $(`#${ids.jobId}`);
  const $tow = $(`#${ids.towId}`);
  const $towDesc = ids.towDescId ? $(`#${ids.towDescId}`) : $();
  const $dimSection = $(`#${ids.dimSectionId}`);
  const $dimCards = $(`#${ids.dimCardsId}`);
  const $revSection = $(`#${ids.revisionSectionId}`);
  const $revision = $(`#${ids.revisionId}`);
  const employeeIdSelector = ids.employeeIdSelector || null;

  /** @type {Map<string, number>} */
  let projectDirect = new Map();
  let loadToken = 0;
  let towToken = 0;

  renderDimCards();

  function renderDimCards() {
    const name = `work2d3d_${ids.projectId}`;
    $dimCards.empty();
    WORK_2D3D_OPTIONS.forEach((opt) => {
      const $label = $("<label>")
        .addClass("work-dim-card")
        .append(
          $("<input>")
            .attr({ type: "radio", name, value: opt.value })
            .prop("checked", false),
          $("<span>")
            .addClass("work-dim-card-body")
            .append(
              $("<i>").addClass("bi bi-check2 work-dim-check"),
              $("<strong>").text(opt.title),
              opt.subtitle
                ? $("<small>").text(opt.subtitle)
                : $("<small>").html("&nbsp;"),
            ),
        );
      $dimCards.append($label);
    });
  }

  function selectedDirect() {
    const id = String($project.val() || "");
    return projectDirect.get(id) === 1;
  }

  function dimVisible() {
    const groupId = Number($group.val() || 0);
    return (
      selectedDirect() &&
      groupId > 0 &&
      !DIM_EXCLUDED_GROUPS.has(groupId) &&
      Boolean($project.val())
    );
  }

  function syncDimVisibility() {
    const show = dimVisible();
    $dimSection.toggleClass("d-none", !show);
    $revSection.toggleClass("d-none", !show);
    if (!show) {
      $dimCards.find("input[type=radio]").prop("checked", false);
      $revision.prop("checked", false);
    }
    syncDimControls();
  }

  /** 2D/3D + revision only after a job is chosen (and the section is shown). */
  function syncDimControls() {
    const ready = dimVisible() && Boolean($job.val());
    $dimCards.find("input[type=radio]").prop("disabled", !ready);
    $dimSection.toggleClass("is-dim-disabled", !ready);
    $revision.prop("disabled", !ready);
    if (!ready) {
      $dimCards.find("input[type=radio]").prop("checked", false);
      $revision.prop("checked", false);
      $dimSection.removeClass("is-invalid-block");
    }
  }

  /** Hours / minutes stay available for entry at any time. */
  function syncHoursEnabled() {
    $hours.prop("disabled", false);
    if ($minutes.length) {
      $minutes.prop("disabled", false);
    }
  }

  function syncDurationSummary() {
    if (!$durationSummary.length) return;

    const $label = $durationSummary.find(".work-duration-summary-label");
    const $sub = $durationSummary.find(".work-duration-summary-sub");
    const hoursRaw = $hours.val();
    const minutesRaw = $minutes.length ? $minutes.val() : "0";
    const hours =
      hoursRaw === "" || hoursRaw === null ? NaN : Number(hoursRaw);
    const minutes =
      minutesRaw === "" || minutesRaw === null ? 0 : Number(minutesRaw);

    const hoursOk = Number.isInteger(hours) && hours >= 0;
    const minutesOk =
      Number.isInteger(minutes) && minutes >= 0 && minutes <= 59;
    const total = hoursOk && minutesOk ? toTotalMinutes(hours, minutes) : 0;

    if (!hoursOk || !minutesOk || total <= 0) {
      $durationSummary.addClass("is-empty");
      $label.text("Enter hours and minutes");
      $sub.html("&nbsp;");
      return;
    }

    $durationSummary.removeClass("is-empty");
    $label.text(formatDuration(hours, minutes));
    $sub.text(`${total} ${total === 1 ? "minute" : "minutes"} total`);
  }

  /**
   * Re-apply disabled states from current selections (e.g. after cutoff unlock).
   */
  function syncDisabledState() {
    const hasGroup = Boolean($group.val());
    const hasProject = Boolean($project.val());
    const hasItem = Boolean($item.val());
    const hasJob = Boolean($job.val());
    const projectOptionCount = $project.find("option").length;
    const itemOptionCount = $item.find("option").length;
    const jobOptionCount = $job.find("option").length;
    const towOptionCount = $tow.find("option").length;

    $project.prop("disabled", !hasGroup || projectOptionCount <= 1);
    $item.prop("disabled", !hasProject || itemOptionCount <= 1);
    $job.prop("disabled", !hasItem || jobOptionCount <= 1);
    $tow.prop("disabled", !hasJob || towOptionCount <= 1);
    syncHoursEnabled();
    syncDimVisibility();
  }

  function fillSelect($el, rows, placeholder) {
    const current = String($el.val() || "");
    $el.empty().append($("<option>").val("").text(placeholder));
    rows.forEach((row) => {
      const $opt = $("<option>").val(String(row.id)).text(row.name);
      if (row.description) {
        $opt.attr("data-description", row.description);
      }
      $el.append($opt);
    });
    if (current && rows.some((r) => String(r.id) === current)) {
      $el.val(current);
    }
  }

  function clearTowDesc() {
    if (!$towDesc.length) return;
    $towDesc.text("");
  }

  function syncTowDesc() {
    if (!$towDesc.length) return;
    const desc = String(
      $tow.find("option:selected").attr("data-description") || "",
    ).trim();
    if (!desc || !$tow.val()) {
      clearTowDesc();
      return;
    }
    $towDesc.text(desc);
  }

  function disableCascadeFrom(level) {
    if (level <= 1) {
      fillSelect($item, [], "Select project first");
      $item.prop("disabled", true);
    }
    if (level <= 2) {
      fillSelect($job, [], "Select item first");
      $job.prop("disabled", true);
    }
    if (level <= 1) {
      fillSelect($tow, [], "Select job request description first");
      $tow.prop("disabled", true);
      clearTowDesc();
    } else if (level <= 3) {
      $tow.val("").prop("disabled", true);
      clearTowDesc();
    }
    syncHoursEnabled();
    syncDimControls();
  }

  function employeeQuery() {
    if (!employeeIdSelector) return "";
    const empId = Number($(employeeIdSelector).val() || 0);
    return empId > 0 ? `&employee_id=${empId}` : "";
  }

  async function loadProjects() {
    const token = ++loadToken;
    const groupId = String($group.val() || "").trim();
    const groupAbbr = String(
      $group.find("option:selected").data("abbr") ||
        $group.find("option:selected").text() ||
        "",
    ).trim();
    projectDirect = new Map();
    fillSelect(
      $project,
      [],
      groupId ? "Loading projects…" : "Select group first",
    );
    $project.prop("disabled", true);
    disableCascadeFrom(1);
    syncDimVisibility();

    if (!groupId || !groupAbbr || groupAbbr.toLowerCase().startsWith("select")) {
      fillSelect($project, [], "Select group first");
      return;
    }

    try {
      const payload = await apiGet(
        apiUrl(
          `/projects?group=${encodeURIComponent(groupAbbr)}${employeeQuery()}`,
        ),
      );
      if (token !== loadToken) return;

      const list = Array.isArray(payload)
        ? payload
        : payload?.data && Array.isArray(payload.data)
          ? payload.data
          : [];

      const rows = list
        .map((p) => {
          const id = String(p.fldID ?? p.id ?? "");
          const name = String(p.fldProject ?? p.name ?? "");
          const direct = Number(p.fldDirect ?? p.direct ?? 0);
          if (!id || !name) return null;
          projectDirect.set(id, direct);
          return { id, name };
        })
        .filter(Boolean);

      fillSelect(
        $project,
        rows,
        rows.length ? "Select project" : "No projects available",
      );
      $project.prop("disabled", rows.length === 0);
    } catch {
      if (token !== loadToken) return;
      fillSelect($project, [], "Failed to load projects");
      $project.prop("disabled", true);
    }
    syncHoursEnabled();
    syncDimVisibility();
  }

  async function loadItems() {
    const token = ++loadToken;
    const projectId = Number($project.val() || 0);
    const groupId = Number($group.val() || 0);
    disableCascadeFrom(1);
    syncDimVisibility();

    if (projectId <= 0 || groupId <= 0) return;

    $item.prop("disabled", true);
    fillSelect($item, [], "Loading…");

    try {
      const payload = await apiGet(
        apiUrl(`/work/items?project_id=${projectId}&group_id=${groupId}`),
      );
      if (token !== loadToken) return;
      const rows = normalizePayload(payload);
      fillSelect($item, rows, rows.length ? "Select item of work" : "No items available");
      $item.prop("disabled", rows.length === 0);
    } catch {
      if (token !== loadToken) return;
      fillSelect($item, [], "Failed to load items");
      $item.prop("disabled", true);
    }

    syncHoursEnabled();
    loadTow().catch(() => {});
  }

  async function loadJobs() {
    const token = ++loadToken;
    const projectId = Number($project.val() || 0);
    const itemId = Number($item.val() || 0);
    const groupId = Number($group.val() || 0);
    disableCascadeFrom(2);

    if (projectId <= 0 || itemId <= 0 || groupId <= 0) return;

    $job.prop("disabled", true);
    fillSelect($job, [], "Loading…");

    try {
      const payload = await apiGet(
        apiUrl(
          `/work/jobs?project_id=${projectId}&item_id=${itemId}&group_id=${groupId}`,
        ),
      );
      if (token !== loadToken) return;
      const rows = normalizePayload(payload);
      fillSelect(
        $job,
        rows,
        rows.length ? "Select job request description" : "No jobs available",
      );
      $job.prop("disabled", rows.length === 0);
    } catch {
      if (token !== loadToken) return;
      fillSelect($job, [], "Failed to load jobs");
      $job.prop("disabled", true);
    }
    syncDimControls();
  }

  async function loadTow() {
    const token = ++towToken;
    const projectId = Number($project.val() || 0);
    fillSelect($tow, [], "Select job request description first");
    $tow.prop("disabled", true);
    clearTowDesc();

    if (projectId <= 0) return;

    try {
      const payload = await apiGet(apiUrl(`/work/tow?project_id=${projectId}`));
      if (token !== towToken) return;
      const list = Array.isArray(payload)
        ? payload
        : payload?.data && Array.isArray(payload.data)
          ? payload.data
          : [];
      const rows = list
        .map((row) => ({
          id: String(row.id ?? row.fldID ?? ""),
          name: String(row.name ?? row.fldTOW ?? ""),
          description: String(row.description ?? row.fldTOWDesc ?? ""),
        }))
        .filter((row) => row.id && row.name);
      // Keep disabled until a job is selected (cascade UX).
      const jobSelected = Boolean($job.val());
      fillSelect(
        $tow,
        rows,
        rows.length ? "Select type of work" : "No types available",
      );
      $tow.prop("disabled", !jobSelected || rows.length === 0);
      if (!jobSelected) {
        $tow.val("");
      }
      syncTowDesc();
    } catch {
      if (token !== towToken) return;
      fillSelect($tow, [], "Failed to load types of work");
      clearTowDesc();
    }
  }

  function enableTowIfReady() {
    const hasOptions = $tow.find("option").length > 1;
    const jobSelected = Boolean($job.val());
    $tow.prop("disabled", !jobSelected || !hasOptions);
    if (!jobSelected) {
      $tow.val("");
      clearTowDesc();
    } else {
      syncTowDesc();
    }
    syncDimControls();
  }

  $group.on("change", () => {
    loadProjects().catch(() => {});
  });

  $project.on("change", () => {
    syncDimVisibility();
    loadItems().catch(() => {});
  });

  $item.on("change", () => {
    loadJobs().catch(() => {});
  });

  $job.on("change", () => {
    enableTowIfReady();
  });

  $tow.on("change", () => {
    syncTowDesc();
  });

  $hours.on("input change", () => {
    syncDurationSummary();
  });
  if ($minutes.length) {
    $minutes.attr({ min: 0, max: 59, step: 1 });
    $minutes.on("input change blur", function () {
      const raw = $(this).val();
      if (raw === "" || raw === null) {
        syncDurationSummary();
        return;
      }
      let n = Number(raw);
      if (!Number.isFinite(n)) {
        $(this).val("0");
      } else {
        n = Math.trunc(n);
        if (n < 0) n = 0;
        if (n > 59) n = 59;
        if (String(n) !== String(raw)) {
          $(this).val(String(n));
        }
      }
      syncDurationSummary();
    });
  }

  function getValues() {
    const showDim = dimVisible();
    const hoursRaw = $hours.val();
    const minutesRaw = $minutes.length ? $minutes.val() : "0";
    return {
      project_id: Number($project.val() || 0),
      hours: hoursRaw === "" || hoursRaw === null ? NaN : Number(hoursRaw),
      minutes: minutesRaw === "" || minutesRaw === null ? 0 : Number(minutesRaw),
      item_id: Number($item.val() || 0),
      job_id: Number($job.val() || 0),
      tow_id: Number($tow.val() || 0),
      work_2d3d: showDim
        ? String($dimCards.find("input[type=radio]:checked").val() || "")
        : "",
      revision: showDim && $revision.is(":checked") ? 1 : 0,
    };
  }

  function markInvalidFields() {
    let valid = true;
    const values = getValues();

    if (!values.project_id) {
      markFieldInvalid($project[0], "Select a project.");
      valid = false;
    } else {
      clearFieldInvalid($project[0]);
    }

    const hoursOk = Number.isInteger(values.hours) && values.hours >= 0;
    const minutesOk =
      Number.isInteger(values.minutes) &&
      values.minutes >= 0 &&
      values.minutes <= 59;
    const totalOk = hoursOk && minutesOk && values.hours * 60 + values.minutes > 0;

    if (!totalOk) {
      markFieldInvalid(
        $hours[0],
        "Enter hours and minutes (at least 1 minute total).",
      );
      if ($minutes.length) {
        markFieldInvalid($minutes[0], "Minutes must be 0–59.");
      }
      valid = false;
    } else {
      clearFieldInvalid($hours[0]);
      if ($minutes.length) clearFieldInvalid($minutes[0]);
    }

    if (!values.item_id) {
      markFieldInvalid($item[0], "Select an item of work.");
      valid = false;
    } else {
      clearFieldInvalid($item[0]);
    }

    if (!values.job_id) {
      markFieldInvalid($job[0], "Select a job request description.");
      valid = false;
    } else {
      clearFieldInvalid($job[0]);
    }

    if (!values.tow_id) {
      markFieldInvalid($tow[0], "Select a type of work.");
      valid = false;
    } else {
      clearFieldInvalid($tow[0]);
    }

    if (dimVisible() && !values.work_2d3d) {
      $dimSection.addClass("is-invalid-block");
      valid = false;
    } else {
      $dimSection.removeClass("is-invalid-block");
    }

    return valid;
  }

  function reset() {
    loadToken += 1;
    towToken += 1;
    projectDirect = new Map();
    fillSelect($project, [], "Select group first");
    $project.prop("disabled", true);
    $hours.val("");
    if ($minutes.length) $minutes.val("0");
    disableCascadeFrom(1);
    $dimCards.find("input[type=radio]").prop("checked", false).prop("disabled", true);
    $revision.prop("checked", false).prop("disabled", true);
    $dimSection.addClass("d-none").removeClass("is-invalid-block is-dim-disabled");
    $revSection.addClass("d-none");
    clearTowDesc();
    syncHoursEnabled();
    syncDurationSummary();
  }

  // Clear dim invalid state when a card is chosen.
  $dimCards.on("change", "input[type=radio]", () => {
    $dimSection.removeClass("is-invalid-block");
  });

  syncHoursEnabled();
  syncDimControls();
  syncDurationSummary();

  return {
    loadProjects,
    getValues,
    markInvalidFields,
    reset,
    syncDimVisibility,
    syncDisabledState,
  };
}
