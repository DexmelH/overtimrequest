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

const META_SEP = "\u00b7";

/** @type {Array<{id: number, abbreviation: string, name: string}>} */
let groups = [];
/** @type {object[]} */
let recipients = [];
/** @type {Array<{id: number, name: string, group: string}>} */
let filterProjects = [];
/** @type {object[]} */
let groupUsers = [];
/** @type {object|null} */
let selectedUser = null;

function getAddGroupId() {
  return String($("#notifyAddGroupSelect").val() || "");
}

function getAddUserId() {
  return String($("#notifyAddUserId").val() || selectedUser?.id || "");
}

function getAddProjectId() {
  return String($("#notifyAddProjectSelect").val() || "");
}

function fillGroupSelect($sel, includeAllLabel) {
  $sel.empty();
  if (includeAllLabel) {
    $sel.append(`<option value="">${escapeHtml(includeAllLabel)}</option>`);
  } else {
    $sel.append('<option value="">Select a group</option>');
  }
  groups.forEach((g) => {
    $sel.append(
      `<option value="${escapeHtml(String(g.id))}">${escapeHtml(g.abbreviation)} - ${escapeHtml(g.name)}</option>`,
    );
  });
}

function resetUserPicker(message) {
  selectedUser = null;
  groupUsers = [];
  $("#notifyAddUserId").val("");
  $("#notifyAddUserSearch")
    .val("")
    .prop("disabled", true)
    .attr("placeholder", message || "Select a group first");
  $("#notifyUserSuggestions").addClass("d-none").empty();
}

function resetProjectSelect(message) {
  $("#notifyAddProjectSelect")
    .prop("disabled", true)
    .empty()
    .append(`<option value="">${escapeHtml(message || "Select a user first")}</option>`);
}

function updateAddButtonState() {
  const groupId = getAddGroupId();
  const userId = getAddUserId();
  const projectId = getAddProjectId();
  const disabled = !groupId || !userId || !projectId;
  $("#notifyAddBtn").prop("disabled", disabled);
}

function showUserSuggestions(users) {
  const $box = $("#notifyUserSuggestions").empty().removeClass("d-none");
  if (!users.length) {
    $box.append('<div class="suggestion-empty">No users found</div>');
    return;
  }

  users.forEach((u) => {
    $box.append(`
      <button type="button" class="suggestion-item notify-user-suggestion" data-id="${escapeHtml(String(u.id))}">
        <strong>${escapeHtml(u.surname || "")}</strong> ${escapeHtml(u.firstname || "")}
        <span class="ot-muted">ID ${escapeHtml(String(u.id))}</span>
      </button>
    `);
  });
}

function hideUserSuggestions() {
  $("#notifyUserSuggestions").addClass("d-none").empty();
}

function filterGroupUsers(query) {
  const q = String(query || "").trim().toLowerCase();
  if (!q) return groupUsers.slice();
  return groupUsers.filter((u) => {
    const name = `${u.surname || ""} ${u.firstname || ""}`.trim().toLowerCase();
    const id = String(u.id || "");
    const email = String(u.email || "").toLowerCase();
    return name.includes(q) || id.includes(q) || email.includes(q);
  });
}

function selectNotifyUser(user) {
  if (!user?.id) return;
  selectedUser = user;
  $("#notifyAddUserId").val(String(user.id));
  $("#notifyAddUserSearch").val(
    `${user.surname || ""} ${user.firstname || ""}`.trim(),
  );
  hideUserSuggestions();
  loadProjectsForUser(getAddGroupId(), String(user.id)).catch(() => {});
}

async function loadGroups() {
  const json = await apiGet(apiUrl("/admin/groups"));
  groups = json?.data || [];
  fillGroupSelect($("#notifyAddGroupSelect"), null);
  fillGroupSelect($("#notifyFilterGroupSelect"), "All groups");
}

async function loadUsersForGroup(groupId) {
  resetProjectSelect("Select a user first");
  selectedUser = null;
  $("#notifyAddUserId").val("");
  $("#notifyAddUserSearch").val("");
  hideUserSuggestions();
  updateAddButtonState();

  if (!groupId) {
    resetUserPicker("Select a group first");
    return;
  }

  $("#notifyAddUserSearch")
    .prop("disabled", true)
    .attr("placeholder", "Loading users...");

  try {
    const json = await apiGet(
      apiUrl("/admin/project-notify/employees") +
        "?group_id=" +
        encodeURIComponent(groupId),
    );
    if (!json?.success) {
      showToast(json?.message || "Could not load users.", { type: "error" });
      resetUserPicker("Could not load users");
      return;
    }

    groupUsers = json.data || [];
    if (!groupUsers.length) {
      resetUserPicker("No users in this main group");
      return;
    }

    $("#notifyAddUserSearch")
      .prop("disabled", false)
      .attr("placeholder", "Search user by name or ID...");
  } catch {
    showToast("Could not load users.", { type: "error" });
    resetUserPicker("Could not load users");
  }
}

async function loadProjectsForUser(groupId, employeeId) {
  updateAddButtonState();

  if (!groupId || !employeeId) {
    resetProjectSelect("Select a user first");
    return;
  }

  $("#notifyAddProjectSelect")
    .prop("disabled", true)
    .empty()
    .append('<option value="">Loading…</option>');

  try {
    const qs =
      "?group_id=" +
      encodeURIComponent(groupId) +
      "&employee_id=" +
      encodeURIComponent(employeeId);
    const json = await apiGet(apiUrl("/admin/project-notify/projects") + qs);
    if (!json?.success) {
      showToast(json?.message || "Could not load projects.", { type: "error" });
      resetProjectSelect("Could not load projects");
      return;
    }

    const projects = json.data || [];
    const $sel = $("#notifyAddProjectSelect").empty();
    if (!projects.length) {
      $sel
        .prop("disabled", true)
        .append('<option value="">No projects available</option>');
      return;
    }

    $sel.append('<option value="">Select a project</option>');
    projects.forEach((p) => {
      $sel.append(
        `<option value="${escapeHtml(String(p.id))}">${escapeHtml(p.name)}</option>`,
      );
    });
    $sel.prop("disabled", false);
  } catch {
    showToast("Could not load projects.", { type: "error" });
    resetProjectSelect("Could not load projects");
  }
}

function fillFilterProjectSelect() {
  const $sel = $("#notifyFilterProjectSelect").empty();
  $sel.append('<option value="">All projects</option>');
  filterProjects.forEach((p) => {
    const label = p.group ? `${p.name} (${p.group})` : p.name;
    $sel.append(
      `<option value="${escapeHtml(String(p.id))}">${escapeHtml(label)}</option>`,
    );
  });
}

function renderRecipientList() {
  const $wrap = $("#projectNotifyList").empty();
  if (!recipients.length) {
    $wrap.append(
      '<p class="ot-muted small mb-0">No notify recipients match the current filters.</p>',
    );
    return;
  }

  const $list = $('<div class="approver-preview-list"></div>');
  recipients.forEach((row) => {
    const name = `${row.surname || ""} ${row.firstname || ""}`.trim() || "—";
    const projectName = row.project_name || `Project #${row.project_id}`;
    const groupLabel = row.project_group || "—";
    const idEmail = `ID ${row.employee_id}${row.email ? ` ${META_SEP} ${row.email}` : ""}`;

    $list.append(`
      <div class="approver-preview-item notify-recipient-row">
        <div class="saved-approver-identity min-w-0">
          <div class="approver-preview-name">${escapeHtml(name)}</div>
          <div class="ot-muted small">${escapeHtml(idEmail)}</div>
          <div class="ot-muted small">Group: ${escapeHtml(groupLabel)}</div>
          <div class="ot-muted small">Project: ${escapeHtml(projectName)}</div>
        </div>
        <div class="saved-approver-controls">
          <button type="button" class="ot-btn ot-btn-secondary btn-sm remove-notify-btn"
            data-employee-id="${escapeHtml(String(row.employee_id))}"
            data-employee-name="${escapeHtml(name)}"
            data-project-id="${escapeHtml(String(row.project_id))}"
            data-project-name="${escapeHtml(projectName)}"
            data-group-abbr="${escapeHtml(groupLabel)}"
            title="Remove notify recipient">
            <i class="bi bi-person-x"></i>
            <span class="remove-approver-label">Remove</span>
          </button>
        </div>
      </div>
    `);
  });
  $wrap.append($list);
}

async function loadRecipients() {
  const groupId = String($("#notifyFilterGroupSelect").val() || "");
  const projectId = String($("#notifyFilterProjectSelect").val() || "");
  const params = new URLSearchParams();
  if (groupId) params.set("group_id", groupId);
  if (projectId) params.set("project_id", projectId);

  const qs = params.toString() ? `?${params.toString()}` : "";
  try {
    const json = await apiGet(apiUrl("/admin/project-notify") + qs);
    if (!json?.success) {
      showToast(json?.message || "Could not load notify recipients.", {
        type: "error",
      });
      return;
    }
    recipients = json.recipients || [];
    filterProjects = json.filter_projects || [];
    const currentProject = projectId;
    fillFilterProjectSelect();
    if (
      currentProject &&
      filterProjects.some((p) => String(p.id) === currentProject)
    ) {
      $("#notifyFilterProjectSelect").val(currentProject);
    }
    renderRecipientList();
  } catch {
    showToast("Could not load notify recipients.", { type: "error" });
  }
}

async function addNotifyRecipient() {
  const groupId = getAddGroupId();
  const employeeId = getAddUserId();
  const projectId = getAddProjectId();

  if (!groupId) {
    markFieldInvalid("#notifyAddGroupSelect");
    showToast("Select a group first.", { type: "warning" });
    return;
  }
  clearFieldInvalid("#notifyAddGroupSelect");

  if (!employeeId || !projectId) {
    showToast("Select a user and project.", { type: "warning" });
    return;
  }

  const userLabel =
    selectedUser
      ? `${selectedUser.surname || ""} ${selectedUser.firstname || ""}`.trim()
      : $("#notifyAddUserSearch").val().trim();
  const body = new FormData();
  body.append("group_id", groupId);
  body.append("employee_id", employeeId);
  body.append("project_id", projectId);
  if (userLabel) body.append("employee_name", userLabel);

  const $btn = $("#notifyAddBtn").prop("disabled", true);
  try {
    const json = await apiPost(apiUrl("/admin/project-notify"), body);
    if (!json?.success) {
      showToast(json?.message || "Could not add notify recipient.", {
        type: "error",
      });
      return;
    }

    showToast("Notify recipient added.", { type: "success", duration: 2500 });
    $("#notifyAddProjectSelect").val("");
    updateAddButtonState();
    await loadRecipients();
    await loadProjectsForUser(groupId, employeeId);
  } catch {
    showToast("Could not add notify recipient.", { type: "error" });
  } finally {
    updateAddButtonState();
    $btn.prop("disabled", $("#notifyAddBtn").prop("disabled"));
  }
}

async function removeNotifyRecipient(
  employeeId,
  projectId,
  employeeName,
  projectName,
  groupAbbr,
) {
  if (!employeeId || !projectId) return;

  const confirmed = await confirmAction({
    title: "Remove notify recipient?",
    message: employeeName
      ? `${employeeName} will no longer be emailed for ${projectName || "this project"}.`
      : "This person will no longer be emailed for this project.",
    confirmText: "Remove",
    cancelText: "Keep",
    variant: "danger",
    icon: "bi-person-x-fill",
  });
  if (!confirmed) return;

  const body = new FormData();
  body.append("employee_id", String(employeeId));
  body.append("project_id", String(projectId));
  if (employeeName) body.append("employee_name", employeeName);
  if (projectName) body.append("project_name", projectName);
  if (groupAbbr) body.append("group_abbr", groupAbbr);

  try {
    const json = await apiPost(apiUrl("/admin/project-notify/remove"), body);
    if (!json?.success) {
      showToast(json?.message || "Could not remove notify recipient.", {
        type: "error",
      });
      return;
    }
    showToast("Notify recipient removed.", { type: "success", duration: 2500 });
    await loadRecipients();
    const groupId = getAddGroupId();
    const userId = getAddUserId();
    if (groupId && userId) {
      await loadProjectsForUser(groupId, userId);
    }
  } catch {
    showToast("Could not remove notify recipient.", { type: "error" });
  }
}

export function initProjectNotify() {
  bindClearInvalidOnEdit("#panel-notify");
  resetUserPicker();
  resetProjectSelect();
  updateAddButtonState();
  renderRecipientList();

  loadGroups()
    .then(() => loadRecipients())
    .catch(() => showToast("Could not load groups.", { type: "error" }));

  $("#notifyAddGroupSelect").on("change", function () {
    if (String($(this).val() || "").trim()) {
      clearFieldInvalid(this);
    }
    loadUsersForGroup($(this).val()).catch(() => {});
  });

  $("#notifyAddUserSearch").on("focus input", function () {
    if ($(this).prop("disabled")) return;
    const q = $(this).val().trim();
    if (selectedUser) {
      const selectedName = `${selectedUser.surname || ""} ${selectedUser.firstname || ""}`.trim();
      if (q !== selectedName) {
        selectedUser = null;
        $("#notifyAddUserId").val("");
        resetProjectSelect("Select a user first");
        updateAddButtonState();
      }
    }
    showUserSuggestions(filterGroupUsers(q));
  });

  $(document).on("click", ".notify-user-suggestion", function () {
    const id = String($(this).data("id") || "");
    const user = groupUsers.find((u) => String(u.id) === id);
    if (!user) return;
    selectNotifyUser(user);
  });

  $(document).on("click", function (e) {
    if (!$(e.target).closest("#notifyAddUserSearch, #notifyUserSuggestions").length) {
      hideUserSuggestions();
    }
  });

  $("#notifyAddProjectSelect").on("change", updateAddButtonState);

  $("#notifyAddBtn").on("click", () => {
    addNotifyRecipient().catch(() => {});
  });

  $("#notifyFilterGroupSelect").on("change", async function () {
    $("#notifyFilterProjectSelect").val("");
    await loadRecipients();
  });

  $("#notifyFilterProjectSelect").on("change", () => {
    loadRecipients().catch(() => {});
  });

  $(document).on("click", ".remove-notify-btn", function () {
    removeNotifyRecipient(
      $(this).data("employee-id"),
      $(this).data("project-id"),
      $(this).data("employee-name") || "",
      $(this).data("project-name") || "",
      $(this).data("group-abbr") || "",
    ).catch(() => {});
  });
}
