import { apiUrl } from "../shared/js/api.js";
import { apiGet, apiPost } from "../shared/js/http.js";
import { showToast } from "../shared/js/toast.js";
import { confirmAction } from "../shared/js/confirm.js";

const LEVELS = ["L1", "L2", "L3", "L4"];
let groups = [];
let activeSearchLevel = null;
let searchTimer = null;

function levelNum(label) {
  return parseInt(label.replace("L", ""), 10);
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
          <input type="text" class="form-control form-control-sm approver-search"
            id="approverSearch${n}" placeholder="Search employee by name or ID..."
            autocomplete="off" data-level="${level}" />
          <div class="employee-suggestions d-none" id="suggestions${n}"></div>
          <div class="selected-approver ot-muted small mt-1" id="selected${n}">Not assigned</div>
        </div>
        <button type="button" class="ot-btn ot-btn-secondary btn-sm clear-level" data-level="${level}">
          Clear
        </button>
      </div>
    `);
  });
}

function setLevelApprover(level, employee) {
  const n = levelNum(level);
  const $id = $(`#approverId${n}`);
  const $search = $(`#approverSearch${n}`);
  const $selected = $(`#selected${n}`);

  if (!employee) {
    $id.val("");
    $search.val("");
    $selected.text("Not assigned");
    return;
  }

  $id.val(employee.approver_id || employee.id);
  $search.val("");
  $selected.html(
    `<strong>${employee.surname || ""}</strong> ${employee.firstname || ""} <span class="ot-muted">(ID ${employee.approver_id || employee.id})</span>`,
  );
}

function clearSuggestions() {
  $(".employee-suggestions").addClass("d-none").empty();
  activeSearchLevel = null;
}

function showSuggestions(level, employees) {
  const n = levelNum(level);
  const $box = $(`#suggestions${n}`).empty().removeClass("d-none");
  activeSearchLevel = level;

  if (!employees.length) {
    $box.append('<div class="suggestion-empty">No employees found</div>');
    return;
  }

  employees.forEach((emp) => {
    $box.append(`
      <button type="button" class="suggestion-item" data-level="${level}"
        data-id="${emp.id}" data-surname="${emp.surname}" data-firstname="${emp.firstname || ""}">
        <strong>${emp.surname}</strong> ${emp.firstname || ""}
        <span class="ot-muted">ID ${emp.id} · ${emp.group_abbr || ""}</span>
      </button>
    `);
  });
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
    LEVELS.forEach((l) => setLevelApprover(l, null));
    return;
  }
  const json = await apiGet(apiUrl("/admin/approvers") + "?group_id=" + groupId);
  LEVELS.forEach((level) => {
    const data = json?.levels?.[level];
    if (data?.approver_id) {
      setLevelApprover(level, data);
    } else {
      setLevelApprover(level, null);
    }
  });
}

async function saveApprovers() {
  const groupId = $("#approverGroupSelect").val();
  if (!groupId) {
    showToast("Select a group first.", { type: "warning" });
    return;
  }

  const confirmed = await confirmAction({
    title: "Save group approvers?",
    message: "L1–L4 approvers will be used for new overtime requests in this group.",
    confirmText: "Save",
    cancelText: "Cancel",
    variant: "primary",
    icon: "bi-people-fill",
  });
  if (!confirmed) return;

  const body = new FormData();
  body.append("group_id", groupId);
  LEVELS.forEach((level) => {
    const n = levelNum(level);
    const val = $(`#approverId${n}`).val();
    if (val) body.append("l" + n, val);
  });

  try {
    const json = await apiPost(apiUrl("/admin/approvers"), body);
    if (json?.success) {
      showToast(json.message || "Approvers saved.", { type: "success" });
      await loadGroupApprovers(groupId);
    } else {
      showToast(json?.message || "Could not save approvers.", { type: "warning" });
    }
  } catch (e) {
    showToast("Failed to save approvers.", { type: "error" });
  }
}

export function initApprovers() {
  renderLevelRows();
  loadGroups().catch(() => showToast("Could not load groups.", { type: "error" }));

  $("#approverGroupSelect").on("change", function () {
    loadGroupApprovers($(this).val()).catch(() => {});
  });

  $(document).on("input", ".approver-search", function () {
    const level = $(this).data("level");
    const q = $(this).val().trim();
    clearTimeout(searchTimer);
    if (q.length < 1) {
      clearSuggestions();
      return;
    }
    searchTimer = setTimeout(async () => {
      try {
        const json = await apiGet(apiUrl("/admin/employees") + "?q=" + encodeURIComponent(q));
        showSuggestions(level, json?.data || []);
      } catch {
        clearSuggestions();
      }
    }, 280);
  });

  $(document).on("click", ".suggestion-item", function () {
    const level = $(this).data("level");
    setLevelApprover(level, {
      id: $(this).data("id"),
      surname: $(this).data("surname"),
      firstname: $(this).data("firstname"),
    });
    clearSuggestions();
  });

  $(document).on("click", ".clear-level", function () {
    setLevelApprover($(this).data("level"), null);
  });

  $(document).on("click", function (e) {
    if (!$(e.target).closest(".approver-level-row").length) {
      clearSuggestions();
    }
  });

  $("#saveApproversBtn").on("click", saveApprovers);
}
