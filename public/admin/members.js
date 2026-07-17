import { apiUrl } from "../shared/js/api.js";
import { apiGet, apiPost } from "../shared/js/http.js";
import { showToast } from "../shared/js/toast.js";
import { confirmAction } from "../shared/js/confirm.js";

let searchTimer = null;
let selectedEmployee = null;
let membersLoaded = false;
let currentMembers = [];

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function displayName(member) {
  const first = String(member.firstname || "").trim();
  const last = String(member.surname || "").trim();
  if (first && last) return `${first} ${last}`;
  return first || last || `Employee #${member.employee_id}`;
}

function sourceLabel(source) {
  if (source === "default") return "Default group";
  if (source === "assigned") return "Assigned";
  return source;
}

function sourceTone(source) {
  if (source === "default") return "status-approved";
  if (source === "assigned") return "status-pending";
  return "status-cancelled";
}

function renderMembers(list) {
  currentMembers = Array.isArray(list) ? list : [];
  const $list = $("#adminMembersList").empty();
  if (!list?.length) {
    $list.html(`
      <div class="ot-empty">
        <i class="bi bi-shield"></i>
        <p class="mb-0">No admin members found.</p>
      </div>
    `);
    return;
  }

  list.forEach((member) => {
    const sources = Array.isArray(member.sources) ? member.sources : [];
    const badges = sources
      .map(
        (source) =>
          `<span class="status-badge ${sourceTone(source)}">${escapeHtml(sourceLabel(source))}</span>`,
      )
      .join(" ");
    const notes = member.notes
      ? `<div class="admin-member-notes">${escapeHtml(member.notes)}</div>`
      : "";
    const group = member.group_abbr
      ? `<span class="ot-muted small">${escapeHtml(member.group_abbr)}</span>`
      : "";

    const actions = [];
    if (member.can_update) {
      actions.push(
        `<button type="button" class="ot-btn ot-btn-secondary btn-sm admin-member-edit" data-id="${member.employee_id}" title="Update notes">
          <i class="bi bi-pencil"></i>
        </button>`,
      );
    }
    if (member.can_remove) {
      actions.push(
        `<button type="button" class="ot-btn ot-btn-danger btn-sm admin-member-remove" data-id="${member.employee_id}" title="Remove admin">
          <i class="bi bi-person-dash"></i>
        </button>`,
      );
    }

    $list.append(`
      <div class="admin-member-row" data-id="${member.employee_id}">
        <div class="admin-member-main">
          <div class="fw-semibold">${escapeHtml(displayName(member))}</div>
          <div class="ot-muted small">ID ${member.employee_id}${group ? ` · ${group}` : ""}</div>
          ${notes}
          <div class="admin-member-badges mt-1">${badges}</div>
        </div>
        <div class="admin-member-actions">${actions.join("")}</div>
      </div>
    `);
  });
}

async function loadMembers() {
  const $list = $("#adminMembersList");
  $list.html(`
    <div class="ot-empty">
      <div class="spinner-border spinner-border-sm text-primary"></div>
      <p class="mb-0 mt-2">Loading admins...</p>
    </div>
  `);

  try {
    const json = await apiGet(apiUrl("/admin/members"));
    if (!json?.success) {
      showToast(json?.message || "Could not load admins.", { type: "warning" });
      $list.html(`<div class="ot-empty"><p class="mb-0">Could not load admins.</p></div>`);
      return;
    }
    renderMembers(json.data || []);
    membersLoaded = true;
  } catch {
    showToast("Failed to load admins.", { type: "error" });
    $list.html(`<div class="ot-empty"><p class="mb-0">Failed to load admins.</p></div>`);
  }
}

function clearSelection() {
  selectedEmployee = null;
  $("#adminMemberEmployeeId").val("");
  $("#adminMemberSearch").val("");
  $("#adminMemberNotes").val("");
  $("#adminMemberAddBtn").prop("disabled", true);
  $("#adminMemberSearchResults").addClass("d-none").empty();
}

function selectEmployee(employee) {
  selectedEmployee = employee;
  $("#adminMemberEmployeeId").val(employee.id);
  $("#adminMemberSearch").val(
    `${employee.firstname || ""} ${employee.surname || ""}`.trim() || `ID ${employee.id}`,
  );
  $("#adminMemberAddBtn").prop("disabled", false);
  $("#adminMemberSearchResults").addClass("d-none").empty();
}

async function searchEmployees(query) {
  const $results = $("#adminMemberSearchResults");
  if (!query || query.trim().length < 1) {
    $results.addClass("d-none").empty();
    return;
  }

  try {
    const json = await apiGet(apiUrl("/admin/employees") + `?q=${encodeURIComponent(query.trim())}`);
    const rows = json?.data || [];
    if (!rows.length) {
      $results.html(`<div class="admin-member-search-empty">No matches</div>`).removeClass("d-none");
      return;
    }

    $results
      .html(
        rows
          .map(
            (row) => `
          <button type="button" class="admin-member-search-item" data-id="${row.id}">
            <span class="fw-semibold">${escapeHtml(`${row.firstname || ""} ${row.surname || ""}`.trim())}</span>
            <span class="ot-muted small">ID ${row.id}${row.group_abbr ? ` · ${escapeHtml(row.group_abbr)}` : ""}</span>
          </button>
        `,
          )
          .join(""),
      )
      .removeClass("d-none");

    $results.find(".admin-member-search-item").each(function () {
      const id = Number($(this).data("id"));
      const employee = rows.find((r) => Number(r.id) === id);
      $(this).on("click", () => selectEmployee(employee));
    });
  } catch {
    $results.html(`<div class="admin-member-search-empty">Search failed</div>`).removeClass("d-none");
  }
}

async function addMember() {
  if (!selectedEmployee?.id) {
    showToast("Select an employee first.", { type: "warning" });
    return;
  }

  const body = new FormData();
  body.append("employee_id", String(selectedEmployee.id));
  body.append("notes", $("#adminMemberNotes").val().trim());

  try {
    const json = await apiPost(apiUrl("/admin/members"), body);
    if (!json?.success) {
      showToast(json?.message || "Could not add admin.", { type: "warning" });
      return;
    }
    showToast(json.message || "Admin added.", { type: "success" });
    clearSelection();
    renderMembers(json.data || []);
  } catch {
    showToast("Failed to add admin.", { type: "error" });
  }
}

function openNotesModal(employeeId) {
  const member = currentMembers.find(
    (item) => Number(item.employee_id) === Number(employeeId),
  );
  if (!member) {
    showToast("Admin member could not be found.", { type: "warning" });
    return;
  }

  $("#adminNotesEmployeeId").val(member.employee_id);
  $("#adminNotesMemberName").text(`${displayName(member)} · ID ${member.employee_id}`);
  $("#adminNotesEdit").val(member.notes || "");

  const modalElement = document.getElementById("adminNotesModal");
  bootstrap.Modal.getOrCreateInstance(modalElement).show();
  modalElement.addEventListener(
    "shown.bs.modal",
    () => $("#adminNotesEdit").trigger("focus"),
    { once: true },
  );
}

async function updateMember(employeeId, notes) {
  const $saveButton = $("#adminNotesSaveBtn");
  $saveButton.prop("disabled", true);

  const body = new FormData();
  body.append("employee_id", String(employeeId));
  body.append("notes", notes);

  try {
    const json = await apiPost(apiUrl("/admin/members/update"), body);
    if (!json?.success) {
      showToast(json?.message || "Could not update admin.", { type: "warning" });
      return;
    }
    showToast(json.message || "Admin updated.", { type: "success" });
    renderMembers(json.data || []);
    bootstrap.Modal.getInstance(document.getElementById("adminNotesModal"))?.hide();
  } catch {
    showToast("Failed to update admin.", { type: "error" });
  } finally {
    $saveButton.prop("disabled", false);
  }
}

async function removeMember(employeeId) {
  const confirmed = await confirmAction({
    title: "Remove admin access?",
    message: "This employee will no longer be able to open the Admin page (unless they are in a default admin group).",
    confirmText: "Remove",
    cancelText: "Keep",
    variant: "danger",
    icon: "bi-person-dash",
  });
  if (!confirmed) return;

  const body = new FormData();
  body.append("employee_id", String(employeeId));

  try {
    const json = await apiPost(apiUrl("/admin/members/remove"), body);
    if (!json?.success) {
      showToast(json?.message || "Could not remove admin.", { type: "warning" });
      return;
    }
    showToast(json.message || "Admin removed.", { type: "success" });
    renderMembers(json.data || []);
  } catch {
    showToast("Failed to remove admin.", { type: "error" });
  }
}

export function initAdminMembers() {
  $("#adminMemberSearch").on("input", function () {
    selectedEmployee = null;
    $("#adminMemberEmployeeId").val("");
    $("#adminMemberAddBtn").prop("disabled", true);
    clearTimeout(searchTimer);
    const q = $(this).val();
    searchTimer = setTimeout(() => searchEmployees(q), 250);
  });

  $("#adminMemberAddBtn").on("click", addMember);
  $("#adminMembersRefreshBtn").on("click", loadMembers);

  $("#adminMembersList").on("click", ".admin-member-edit", function () {
    openNotesModal(Number($(this).data("id")));
  });
  $("#adminMembersList").on("click", ".admin-member-remove", function () {
    removeMember(Number($(this).data("id")));
  });

  $("#adminNotesForm").on("submit", function (event) {
    event.preventDefault();
    const employeeId = Number($("#adminNotesEmployeeId").val());
    if (!employeeId) {
      showToast("Invalid admin member.", { type: "warning" });
      return;
    }
    updateMember(employeeId, $("#adminNotesEdit").val().trim());
  });

  $(document).on("click", (event) => {
    if (!$(event.target).closest("#adminMemberSearch, #adminMemberSearchResults").length) {
      $("#adminMemberSearchResults").addClass("d-none");
    }
  });

  $("#tab-members").on("shown.bs.tab", () => {
    if (!membersLoaded) loadMembers();
  });
}
