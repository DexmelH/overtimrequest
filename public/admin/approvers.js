import { apiUrl } from "../shared/js/api.js";
import { apiGet, apiPost } from "../shared/js/http.js";
import { showToast } from "../shared/js/toast.js";
import { escapeHtml } from "../shared/js/escapeHtml.js";
import { confirmAction } from "../shared/js/confirm.js";
import {
  bindClearInvalidOnEdit,
  clearFieldInvalid,
  markFieldInvalid,
} from "../shared/js/formValidation.js";

const LEVELS = ["L1", "L2", "L3", "L4"];
const META_SEP = "\u00b7";

let groups = [];
let activeSearchLevel = null;
let searchTimer = null;
/** @type {Record<string, object[]>} flat saved rows keyed by group id */
let savedApproversCache = {};
/** @type {Record<string, object[]>} */
let searchResults = {};

function levelNum(label) {
  return parseInt(String(label).replace("L", ""), 10);
}

function levelLabel(n) {
  return `L${n}`;
}

function getCurrentGroupId() {
  return $("#approverGroupSelect").val() || "";
}

function setSavedApprovers(groupId, rows) {
  if (!groupId) return;
  savedApproversCache[groupId] = Array.isArray(rows) ? rows : [];
}

function getSavedApprovers(groupId) {
  return groupId ? savedApproversCache[groupId] || [] : [];
}

function getAssignedIds(groupId) {
  return new Set(
    getSavedApprovers(groupId).map((row) => String(row.approver_id)),
  );
}

function employeesForLevel(groupId, level) {
  const n = levelNum(level);
  return getSavedApprovers(groupId).filter(
    (row) => Number(row.approval_level) === n,
  );
}

function updateAddButtonState(level, employee) {
  const $addBtn = $(`.add-approver-btn[data-level="${level}"]`);
  if (!employee?.id) {
    $addBtn.prop("disabled", true).removeData("pending");
    $addBtn.attr("title", "Select an employee from search results");
    return;
  }

  const groupId = getCurrentGroupId();
  const already = getAssignedIds(groupId).has(String(employee.id));
  $addBtn.data("pending", employee);
  $addBtn.prop("disabled", already || !groupId);
  $addBtn.attr(
    "title",
    already
      ? "This employee is already an approver for this group"
      : "Add this approver at " + level,
  );
}

function renderLevelMembers(level) {
  const n = levelNum(level);
  const groupId = getCurrentGroupId();
  const members = employeesForLevel(groupId, level);
  const $box = $(`#levelMembers${n}`).empty();

  if (!groupId) {
    $box.append('<div class="ot-muted small">Select a group first.</div>');
    return;
  }

  if (!members.length) {
    $box.append('<div class="ot-muted small">No approvers at this level yet.</div>');
    return;
  }

  members.forEach((row) => {
    const name = `${row.surname || ""} ${row.firstname || ""}`.trim() || "-";
    $box.append(`
      <div class="approver-level-chip">
        <span class="approver-level-chip-name">${escapeHtml(name)}</span>
        <span class="ot-muted small">ID ${escapeHtml(String(row.approver_id))}</span>
      </div>
    `);
  });
}

function refreshAllLevelMembers() {
  LEVELS.forEach((level) => renderLevelMembers(level));
}

function renderLevelRows() {
  const $body = $("#approverLevels").empty();

  const bands = [
    {
      id: "standard",
      levels: ["L1", "L2"],
      title: "L1 & L2 — Standard",
      summary: "Self-requests still need approval",
      detail:
        "Approvers at these levels still go through the normal approval chain when they request overtime for themselves, and they cannot file after the daily cutoff (same lock as regular employees).",
    },
    {
      id: "senior",
      levels: ["L3", "L4"],
      title: "L3 & L4 — Senior",
      summary: "Self-requests are auto-approved",
      detail:
        "Approvers at these levels are auto-approved when they request overtime for themselves, and they can still file after the daily cutoff. Level 4 can also finalize a pending request immediately when they act.",
    },
  ];

  bands.forEach((band) => {
    const $band = $(`
      <div class="approver-level-band is-${escapeHtml(band.id)}" data-band="${escapeHtml(band.id)}">
        <div class="approver-level-band-header" tabindex="0"
          aria-label="${escapeHtml(band.title)}. ${escapeHtml(band.detail)}">
          <div class="approver-level-band-title-row">
            <span class="approver-level-band-title">${escapeHtml(band.title)}</span>
            <i class="bi bi-info-circle approver-level-band-info" aria-hidden="true"></i>
          </div>
          <p class="approver-level-band-summary mb-0">${escapeHtml(band.summary)}</p>
          <div class="approver-level-band-tooltip" role="tooltip">${escapeHtml(band.detail)}</div>
        </div>
        <div class="approver-level-band-body"></div>
      </div>
    `);
    const $bandBody = $band.find(".approver-level-band-body");

    band.levels.forEach((level) => {
      const n = levelNum(level);
      $bandBody.append(`
        <div class="approver-level-row" data-level="${level}">
          <div class="level-badge">${level}</div>
          <div class="flex-grow-1 position-relative">
            <div class="input-group input-group-sm">
              <input type="text" class="form-control approver-search"
                id="approverSearch${n}" placeholder="Search employee by name or ID..."
                autocomplete="off" data-level="${level}" />
              <button type="button" class="ot-btn ot-btn-primary btn-sm add-approver-btn"
                data-level="${level}" disabled title="Select an employee from search results">
                <i class="bi bi-plus-lg"></i> Add
              </button>
            </div>
            <div class="employee-suggestions d-none" id="suggestions${n}"></div>
            <div class="approver-level-members mt-2" id="levelMembers${n}"></div>
          </div>
        </div>
      `);
    });

    $body.append($band);
  });

  refreshAllLevelMembers();
}

function clearSuggestions() {
  $(".employee-suggestions").addClass("d-none").empty();
  activeSearchLevel = null;
}

function showSuggestions(level, employees) {
  const n = levelNum(level);
  const $box = $(`#suggestions${n}`).empty().removeClass("d-none");
  activeSearchLevel = level;
  const assigned = getAssignedIds(getCurrentGroupId());
  const filtered = (employees || []).filter(
    (emp) => !assigned.has(String(emp.id)),
  );
  searchResults[level] = filtered;

  if (!filtered.length) {
    $box.append(
      '<div class="suggestion-empty">No available employees (already assigned are hidden)</div>',
    );
    return;
  }

  filtered.forEach((emp) => {
    $box.append(`
      <button type="button" class="suggestion-item" data-level="${level}" data-id="${emp.id}">
        <strong>${escapeHtml(emp.surname)}</strong> ${escapeHtml(emp.firstname || "")}
        <span class="ot-muted">ID ${emp.id} ${META_SEP} ${escapeHtml(emp.group_abbr || "")}</span>
      </button>
    `);
  });
}

function findEmployeeInSearch(level, id) {
  return (searchResults[level] || []).find((emp) => String(emp.id) === String(id));
}

function selectPendingEmployee(level, employee) {
  const n = levelNum(level);
  const $search = $(`#approverSearch${n}`);
  $search.val(`${employee.surname || ""} ${employee.firstname || ""}`.trim());
  updateAddButtonState(level, employee);
}

function clearPendingSearch(level) {
  const n = levelNum(level);
  $(`#approverSearch${n}`).val("");
  updateAddButtonState(level, null);
}

async function addApprover(level, employee) {
  const groupId = getCurrentGroupId();
  if (!groupId) {
    markFieldInvalid("#approverGroupSelect");
    showToast("Select a group first.", { type: "warning" });
    $("#approverGroupSelect").trigger("focus");
    return false;
  }
  clearFieldInvalid("#approverGroupSelect");
  if (!employee?.id) return false;

  const body = new FormData();
  body.append("group_id", groupId);
  body.append("level", String(levelNum(level)));
  body.append("approver_id", String(employee.id));
  const name = `${employee.surname || ""} ${employee.firstname || ""}`.trim();
  if (name) body.append("approver_name", name);

  const $btn = $(`.add-approver-btn[data-level="${level}"]`).prop("disabled", true);
  try {
    const json = await apiPost(apiUrl("/admin/approver-add"), body);
    if (!json?.success) {
      showToast(json?.message || "Could not add approver.", { type: "error" });
      return false;
    }

    setSavedApprovers(groupId, json.saved_approvers || []);
    refreshAllLevelMembers();
    renderSavedList(groupId);
    clearPendingSearch(level);
    clearSuggestions();
    showToast(`${level} approver added.`, { type: "success", duration: 2500 });
    return true;
  } catch {
    showToast("Could not add approver.", { type: "error" });
    return false;
  } finally {
    updateAddButtonState(level, null);
    $btn.prop("disabled", true);
  }
}

async function changeApproverLevel(approverId, newLevel, approverName) {
  const groupId = getCurrentGroupId();
  if (!groupId || !approverId) return false;

  const body = new FormData();
  body.append("group_id", groupId);
  body.append("approver_id", String(approverId));
  body.append("level", String(levelNum(newLevel)));
  if (approverName) body.append("approver_name", approverName);

  try {
    const json = await apiPost(apiUrl("/admin/approver-level"), body);
    if (!json?.success) {
      showToast(json?.message || "Could not change level.", { type: "error" });
      renderSavedList(groupId);
      refreshAllLevelMembers();
      return false;
    }

    setSavedApprovers(groupId, json.saved_approvers || []);
    refreshAllLevelMembers();
    renderSavedList(groupId);
    showToast("Approver level updated.", { type: "success", duration: 2500 });
    return true;
  } catch {
    showToast("Could not change level.", { type: "error" });
    renderSavedList(groupId);
    refreshAllLevelMembers();
    return false;
  }
}

async function removeApprover(approverId, approverName) {
  const groupId = getCurrentGroupId();
  if (!groupId || !approverId) return false;

  const confirmed = await confirmAction({
    title: "Remove this approver?",
    message: approverName
      ? `${approverName} will no longer receive overtime requests for this group.`
      : "This person will no longer receive overtime requests for this group.",
    confirmText: "Remove",
    cancelText: "Keep",
    variant: "danger",
    icon: "bi-person-x-fill",
  });
  if (!confirmed) return false;

  const body = new FormData();
  body.append("group_id", groupId);
  body.append("approver_id", String(approverId));
  if (approverName) body.append("approver_name", approverName);

  try {
    const json = await apiPost(apiUrl("/admin/approver-remove"), body);
    if (!json?.success) {
      showToast(json?.message || "Could not remove approver.", { type: "error" });
      return false;
    }

    setSavedApprovers(groupId, json.saved_approvers || []);
    refreshAllLevelMembers();
    renderSavedList(groupId);
    showToast("Approver removed.", { type: "success", duration: 2500 });
    return true;
  } catch {
    showToast("Could not remove approver.", { type: "error" });
    return false;
  }
}

function renderSavedList(groupId) {
  const $wrap = $("#draftApproversPreview").empty();
  if (!groupId) {
    $wrap.append('<p class="ot-muted small mb-0">Select a group to view saved approvers.</p>');
    return;
  }

  const rows = getSavedApprovers(groupId);
  if (!rows.length) {
    $wrap.append(
      '<p class="ot-muted small mb-0">No approvers saved yet. Search under a level and click Add.</p>',
    );
    return;
  }

  const $list = $('<div class="approver-preview-list saved-editable"></div>');
  rows.forEach((row) => {
    const name = `${row.surname || ""} ${row.firstname || ""}`.trim() || "-";
    const currentLevel = Number(row.approval_level) || 1;
    const meta = `ID ${row.approver_id}${row.email ? ` ${META_SEP} ${row.email}` : ""}`;
    const options = LEVELS.map((level) => {
      const n = levelNum(level);
      const selected = n === currentLevel ? " selected" : "";
      return `<option value="${n}"${selected}>${level}</option>`;
    }).join("");

    $list.append(`
      <div class="approver-preview-item saved-edit-row" data-approver-id="${escapeHtml(String(row.approver_id))}">
        <div class="saved-approver-identity min-w-0">
          <div class="approver-preview-name" title="${escapeHtml(name)}">${escapeHtml(name)}</div>
          <div class="ot-muted small text-truncate" title="${escapeHtml(meta)}">${escapeHtml(meta)}</div>
        </div>
        <div class="saved-approver-controls">
          <label class="visually-hidden" for="levelSelect${row.approver_id}">Level</label>
          <select class="form-select form-select-sm saved-level-select"
            id="levelSelect${row.approver_id}"
            data-approver-id="${escapeHtml(String(row.approver_id))}"
            data-approver-name="${escapeHtml(name)}"
            data-current-level="${currentLevel}"
            title="Change approval level">
            ${options}
          </select>
          <button type="button" class="ot-btn ot-btn-secondary btn-sm remove-approver-btn"
            data-approver-id="${escapeHtml(String(row.approver_id))}"
            data-approver-name="${escapeHtml(name)}"
            title="Remove approver">
            <i class="bi bi-person-x"></i>
            <span class="remove-approver-label">Remove</span>
          </button>
        </div>
      </div>
    `);
  });
  $wrap.append($list);
}

function renderOfficialApprovers(payload) {
  const $wrap = $("#officialApproversList").empty();
  const approvers = payload?.approvers || [];
  const groupLabel = payload?.group?.abbreviation
    ? `${payload.group.abbreviation}${payload.group.name ? ` - ${payload.group.name}` : ""}`
    : "";

  if (!approvers.length) {
    $wrap.append(
      `<p class="ot-muted small mb-0">No Forms PIC approvers found${groupLabel ? ` for <strong>${escapeHtml(groupLabel)}</strong>` : ""}.</p>`,
    );
    return;
  }

  if (groupLabel) {
    $wrap.append(
      `<p class="ot-muted small mb-2">Forms PIC for <strong>${escapeHtml(groupLabel)}</strong> (fallback when no saved approvers)</p>`,
    );
  }

  const $list = $('<div class="approver-preview-list official"></div>');
  approvers.forEach((row) => {
    const level = row.role ? `L${row.role}` : "PIC";
    const name = `${row.surname || ""} ${row.firstname || ""}`.trim() || "-";
    $list.append(`
      <div class="approver-preview-item official">
        <span class="level-badge level-badge-sm">${escapeHtml(level)}</span>
        <div class="flex-grow-1 min-w-0">
          <div class="approver-preview-name">${escapeHtml(name)}</div>
          <div class="ot-muted small">ID ${row.id}${row.email ? ` ${META_SEP} ${escapeHtml(row.email)}` : ""}</div>
        </div>
        <span class="status-badge status-approved">Forms PIC</span>
      </div>
    `);
  });
  $wrap.append($list);
}

async function loadGroups() {
  const json = await apiGet(apiUrl("/admin/groups"));
  groups = json?.data || [];
  const $sel = $("#approverGroupSelect").empty().append('<option value="">Select a group</option>');
  groups.forEach((g) => {
    $sel.append(`<option value="${g.id}">${g.abbreviation} - ${g.name}</option>`);
  });
}

async function loadGroupApprovers(groupId) {
  if (!groupId) {
    setSavedApprovers("", []);
    refreshAllLevelMembers();
    renderSavedList("");
    renderOfficialApprovers({});
    return;
  }

  try {
    const json = await apiGet(apiUrl("/admin/approvers") + "?group_id=" + groupId);
    if (!json?.success) {
      showToast(json?.message || "Could not load approvers.", { type: "error" });
      return;
    }
    setSavedApprovers(groupId, json.saved_approvers || []);
    refreshAllLevelMembers();
    renderSavedList(groupId);
    renderOfficialApprovers(json);
  } catch {
    showToast("Could not load approvers.", { type: "error" });
  }
}

export function initApprovers() {
  renderLevelRows();
  renderSavedList("");
  renderOfficialApprovers({});
  loadGroups().catch(() => showToast("Could not load groups.", { type: "error" }));
  bindClearInvalidOnEdit("#adminContent");

  $("#approverGroupSelect").on("change", function () {
    if (String($(this).val() || "").trim()) {
      clearFieldInvalid(this);
    }
    clearSuggestions();
    LEVELS.forEach((level) => clearPendingSearch(level));
    loadGroupApprovers($(this).val()).catch(() => {});
  });

  $(document).on("input", ".approver-search", function () {
    const level = $(this).data("level");
    const q = $(this).val().trim();
    $(`.add-approver-btn[data-level="${level}"]`).prop("disabled", true).removeData("pending");
    clearTimeout(searchTimer);
    if (q.length < 1) {
      clearSuggestions();
      return;
    }
    searchTimer = setTimeout(async () => {
      const groupId = getCurrentGroupId();
      if (!groupId) {
        markFieldInvalid("#approverGroupSelect");
        showToast("Select a group first.", { type: "warning" });
        clearSuggestions();
        return;
      }
      try {
        const qs =
          "?q=" +
          encodeURIComponent(q) +
          "&group_id=" +
          encodeURIComponent(groupId);
        const json = await apiGet(apiUrl("/admin/employees") + qs);
        if (!json?.success) {
          showToast(json?.message || "Employee search failed.", { type: "error" });
          clearSuggestions();
          return;
        }
        showSuggestions(level, json?.data || []);
      } catch {
        clearSuggestions();
        showToast("Could not search employees.", { type: "error" });
      }
    }, 280);
  });

  $(document).on("click", ".suggestion-item", function () {
    const level = $(this).data("level");
    const employee = findEmployeeInSearch(level, $(this).data("id"));
    if (!employee) return;
    selectPendingEmployee(level, employee);
    clearSuggestions();
  });

  $(document).on("click", ".add-approver-btn", function () {
    const level = $(this).data("level");
    const employee = $(this).data("pending");
    if (!employee?.id) {
      showToast("Search and select an employee first.", { type: "warning" });
      return;
    }
    if (getAssignedIds(getCurrentGroupId()).has(String(employee.id))) {
      showToast("This employee is already an approver for this group.", {
        type: "warning",
      });
      return;
    }
    addApprover(level, employee).catch(() => {});
  });

  $(document).on("change", ".saved-level-select", function () {
    const approverId = $(this).data("approver-id");
    const approverName = $(this).data("approver-name") || "";
    const previous = String($(this).data("current-level") || "");
    const next = String($(this).val() || "");
    if (!approverId || next === previous) return;
    changeApproverLevel(approverId, levelLabel(next), approverName).catch(() => {});
  });

  $(document).on("click", ".remove-approver-btn", function () {
    const approverId = $(this).data("approver-id");
    const approverName = $(this).data("approver-name") || "";
    removeApprover(approverId, approverName).catch(() => {});
  });

  $(document).on("click", function (e) {
    if (!$(e.target).closest(".approver-level-row").length) {
      clearSuggestions();
    }
  });
}
