import { apiUrl } from "../shared/js/api.js";
import { apiGet, apiPost } from "../shared/js/http.js";
import { showToast } from "../shared/js/toast.js";

const LEVELS = ["L1", "L2", "L3", "L4"];

let groups = [];
let activeSearchLevel = null;
let searchTimer = null;
/** @type {Record<string, Record<number, object>>} */
let savedLevelsCache = {};
/** @type {Record<string, object[]>} */
let searchResults = {};

function levelNum(label) {
  return parseInt(label.replace("L", ""), 10);
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function getCurrentGroupId() {
  return $("#approverGroupSelect").val() || "";
}

function employeeFromSavedRow(row) {
  if (!row?.approver_id) return null;
  return {
    id: row.approver_id,
    surname: row.surname || "",
    firstname: row.firstname || "",
    email: row.email || "",
  };
}

function setSavedLevels(groupId, savedLevels) {
  if (!groupId) return;
  savedLevelsCache[groupId] = savedLevels || {};
}

function getSavedLevels(groupId) {
  return groupId ? savedLevelsCache[groupId] || {} : {};
}

function getSavedApproverId(groupId, level) {
  const saved = getSavedLevels(groupId)[levelNum(level)];
  return saved?.approver_id ? Number(saved.approver_id) : null;
}

function hasApproverChange(level, employee) {
  if (!employee?.id) return false;
  const groupId = getCurrentGroupId();
  if (!groupId) return false;
  return getSavedApproverId(groupId, level) !== Number(employee.id);
}

function updateAddButtonState(level, employee) {
  const $addBtn = $(`.add-approver-btn[data-level="${level}"]`);
  if (!employee?.id) {
    $addBtn.prop("disabled", true).removeData("pending");
    $addBtn.attr("title", "Select an employee from search results");
    return;
  }

  $addBtn.data("pending", employee);
  const changed = hasApproverChange(level, employee);
  $addBtn.prop("disabled", !changed);
  $addBtn.attr(
    "title",
    changed ? "Save this approver for the selected level" : "No changes — this approver is already saved",
  );
}

function renderLevelRows() {
  const $body = $("#approverLevels").empty();
  LEVELS.forEach((level) => {
    const n = levelNum(level);
    $body.append(`
      <div class="approver-level-row" data-level="${level}">
        <div class="level-badge">${level}</div>
        <div class="flex-grow-1 position-relative">
          <input type="hidden" class="approver-id" id="approverId${n}" value="" />
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
          <div class="selected-approver ot-muted small mt-1" id="selected${n}">No approver saved</div>
        </div>
        <button type="button" class="ot-btn ot-btn-secondary btn-sm clear-level" data-level="${level}">
          Clear
        </button>
      </div>
    `);
  });
}

function setLevelRow(level, employee) {
  const n = levelNum(level);
  const $id = $(`#approverId${n}`);
  const $search = $(`#approverSearch${n}`);
  const $selected = $(`#selected${n}`);

  if (!employee) {
    $id.val("");
    $search.val("");
    $selected.text("No approver saved");
    updateAddButtonState(level, null);
    return;
  }

  $id.val(employee.id);
  $search.val(`${employee.surname || ""} ${employee.firstname || ""}`.trim());
  $selected.html(
    `<strong>${escapeHtml(employee.surname || "")}</strong> ${escapeHtml(employee.firstname || "")} <span class="ot-muted">(ID ${employee.id})</span>`,
  );
  updateAddButtonState(level, employee);
}

function loadSavedIntoForm(groupId) {
  const saved = getSavedLevels(groupId);
  LEVELS.forEach((level) => {
    setLevelRow(level, employeeFromSavedRow(saved[levelNum(level)]));
  });
  renderSavedPreview(groupId);
}

function clearSuggestions() {
  $(".employee-suggestions").addClass("d-none").empty();
  activeSearchLevel = null;
}

function showSuggestions(level, employees) {
  const n = levelNum(level);
  const $box = $(`#suggestions${n}`).empty().removeClass("d-none");
  activeSearchLevel = level;
  searchResults[level] = employees;

  if (!employees.length) {
    $box.append('<div class="suggestion-empty">No employees found</div>');
    return;
  }

  employees.forEach((emp) => {
    $box.append(`
      <button type="button" class="suggestion-item" data-level="${level}" data-id="${emp.id}">
        <strong>${escapeHtml(emp.surname)}</strong> ${escapeHtml(emp.firstname || "")}
        <span class="ot-muted">ID ${emp.id} · ${escapeHtml(emp.group_abbr || "")}</span>
      </button>
    `);
  });
}

function findEmployeeInSearch(level, id) {
  return (searchResults[level] || []).find((emp) => String(emp.id) === String(id));
}

async function saveApproverLevel(level, employee) {
  const groupId = getCurrentGroupId();
  if (!groupId) {
    showToast("Select a group first.", { type: "warning" });
    return false;
  }
  if (!employee?.id) return false;

  const body = new FormData();
  body.append("group_id", groupId);
  body.append("level", String(levelNum(level)));
  body.append("approver_id", String(employee.id));
  const name = `${employee.surname || ""} ${employee.firstname || ""}`.trim();
  if (name) body.append("approver_name", name);

  const $btn = $(`.add-approver-btn[data-level="${level}"]`).prop("disabled", true);
  try {
    const json = await apiPost(apiUrl("/admin/approver-level"), body);
    if (!json?.success) {
      showToast(json?.message || "Could not save approver.", { type: "error" });
      return false;
    }

    setSavedLevels(groupId, json.saved_levels || {});
    setLevelRow(level, employee);
    renderSavedPreview(groupId);
    clearSuggestions();
    showToast(`${level} approver saved.`, { type: "success", duration: 2500 });
    return true;
  } catch {
    showToast("Could not save approver.", { type: "error" });
    return false;
  } finally {
    updateAddButtonState(level, employee);
  }
}

async function clearApproverLevel(level) {
  const groupId = getCurrentGroupId();
  if (!groupId) return false;

  const body = new FormData();
  body.append("group_id", groupId);
  body.append("level", String(levelNum(level)));
  body.append("approver_id", "0");

  const $btn = $(`.clear-level[data-level="${level}"]`).prop("disabled", true);
  try {
    const json = await apiPost(apiUrl("/admin/approver-level"), body);
    if (!json?.success) {
      showToast(json?.message || "Could not clear approver.", { type: "error" });
      return false;
    }

    setSavedLevels(groupId, json.saved_levels || {});
    setLevelRow(level, null);
    renderSavedPreview(groupId);
    showToast(`${level} approver cleared.`, { type: "success", duration: 2500 });
    return true;
  } catch {
    showToast("Could not clear approver.", { type: "error" });
    return false;
  } finally {
    $btn.prop("disabled", false);
  }
}

function renderSavedPreview(groupId) {
  const $wrap = $("#draftApproversPreview").empty();
  if (!groupId) {
    $wrap.append('<p class="ot-muted small mb-0">Select a group to view saved approvers.</p>');
    return;
  }

  const saved = getSavedLevels(groupId);
  const entries = LEVELS.map((level) => ({
    level,
    employee: employeeFromSavedRow(saved[levelNum(level)]),
  })).filter((e) => e.employee);

  if (!entries.length) {
    $wrap.append('<p class="ot-muted small mb-0">No approvers saved yet. Search and click Add for each level.</p>');
    return;
  }

  const $list = $('<div class="approver-preview-list"></div>');
  entries.forEach(({ level, employee }) => {
    $list.append(`
      <div class="approver-preview-item">
        <span class="level-badge level-badge-sm">${level}</span>
        <div class="flex-grow-1 min-w-0">
          <div class="approver-preview-name">${escapeHtml(employee.surname)} ${escapeHtml(employee.firstname || "")}</div>
          <div class="ot-muted small">ID ${employee.id}${employee.email ? ` · ${escapeHtml(employee.email)}` : ""}</div>
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
    ? `${payload.group.abbreviation}${payload.group.name ? ` — ${payload.group.name}` : ""}`
    : "";

  if (!approvers.length) {
    $wrap.append(
      `<p class="ot-muted small mb-0">No Forms PIC approvers found${groupLabel ? ` for <strong>${escapeHtml(groupLabel)}</strong>` : ""}.</p>`,
    );
    return;
  }

  if (groupLabel) {
    $wrap.append(`<p class="ot-muted small mb-2">Forms PIC for <strong>${escapeHtml(groupLabel)}</strong> (fallback when no saved approvers)</p>`);
  }

  const $list = $('<div class="approver-preview-list official"></div>');
  approvers.forEach((row) => {
    const level = row.role ? `L${row.role}` : "PIC";
    const name = `${row.surname || ""} ${row.firstname || ""}`.trim() || "—";
    $list.append(`
      <div class="approver-preview-item official">
        <span class="level-badge level-badge-sm">${escapeHtml(level)}</span>
        <div class="flex-grow-1 min-w-0">
          <div class="approver-preview-name">${escapeHtml(name)}</div>
          <div class="ot-muted small">ID ${row.id}${row.email ? ` · ${escapeHtml(row.email)}` : ""}</div>
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
    $sel.append(`<option value="${g.id}">${g.abbreviation} — ${g.name}</option>`);
  });
}

async function loadGroupApprovers(groupId) {
  if (!groupId) {
    setSavedLevels("", {});
    loadSavedIntoForm("");
    renderOfficialApprovers({});
    return;
  }

  try {
    const json = await apiGet(apiUrl("/admin/approvers") + "?group_id=" + groupId);
    if (!json?.success) {
      showToast(json?.message || "Could not load approvers.", { type: "error" });
      return;
    }
    setSavedLevels(groupId, json.saved_levels || {});
    loadSavedIntoForm(groupId);
    renderOfficialApprovers(json);
  } catch {
    showToast("Could not load approvers.", { type: "error" });
  }
}

export function initApprovers() {
  renderLevelRows();
  renderSavedPreview("");
  renderOfficialApprovers({});
  loadGroups().catch(() => showToast("Could not load groups.", { type: "error" }));

  $("#approverGroupSelect").on("change", function () {
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
      try {
        const json = await apiGet(apiUrl("/admin/employees") + "?q=" + encodeURIComponent(q));
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
    setLevelRow(level, employee);
    clearSuggestions();
  });

  $(document).on("click", ".add-approver-btn", function () {
    const level = $(this).data("level");
    const employee = $(this).data("pending");
    if (!employee || !hasApproverChange(level, employee)) {
      showToast("Search and select a different employee first.", { type: "warning" });
      return;
    }
    saveApproverLevel(level, employee).catch(() => {});
  });

  $(document).on("click", ".clear-level", function () {
    const level = $(this).data("level");
    const groupId = getCurrentGroupId();
    const saved = getSavedLevels(groupId)[levelNum(level)];
    if (!saved) {
      setLevelRow(level, null);
      return;
    }
    clearApproverLevel(level).catch(() => {});
  });

  $(document).on("click", function (e) {
    if (!$(e.target).closest(".approver-level-row").length) {
      clearSuggestions();
    }
  });
}
