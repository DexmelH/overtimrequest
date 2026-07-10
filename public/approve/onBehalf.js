import { apiUrl } from "../shared/js/api.js";
import { apiGet, apiPost } from "../shared/js/http.js";
import { showToast } from "../shared/js/toast.js";
import { configureFormFields, getFieldId } from "../request/ui/formFields.js";
import { resetDependentFields, enableField } from "../request/ui/selectCascade.js";
import { fetchLocations } from "../request/api/fetchLocations.js";
import { fetchProjects } from "../request/api/fetchProjects.js";
import { fetchItems } from "../request/api/fetchItems.js";
import { fetchJobs } from "../request/api/fetchJobs.js";
import { fetchWorks } from "../request/api/fetchWorks.js";
import {
  applyDateConstraints,
  configureRequestDate,
  isAllowedRequestDate,
  loadBlockedHolidays,
  setDefaultRequestDate,
  validateDateInput,
} from "../request/ui/requestDate.js";
import { fetchRequest } from "./api/fetchRequest.js";

const ON_BEHALF_FIELDS = {
  group: "obGroup",
  location: "obLocation",
  project: "obProject",
  item: "obItem",
  jobdesc: "obJobdesc",
  work: "obWork",
};

let searchTimer = null;
/** @type {object[]} */
let employeeResults = [];
/** @type {object[]} */
let employeeGroups = [];

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

function bindDateField() {
  configureRequestDate({ dateFieldId: "obDate", relaxed: true });
  applyDateConstraints();
  setDefaultRequestDate();

  $("#obDate").on("change input", () => {
    validateDateInput(true);
  });
}

async function reloadDateRules(employeeId) {
  await loadBlockedHolidays(employeeId || null);
  setDefaultRequestDate();
}

function renderEmployeeGroupSelect(groups) {
  employeeGroups = groups || [];
  const $sel = $(`#${getFieldId("group")}`)
    .empty()
    .append('<option value="">Select assigned group</option>');
  employeeGroups.forEach((g) => {
    $sel.append(
      `<option value="${g.id}" data-abbr="${escapeHtml(g.abbreviation)}">${escapeHtml(g.abbreviation)} — ${escapeHtml(g.name)}</option>`,
    );
  });

  const $empty = $("#obGroupEmpty");
  const employeeId = $("#obEmployeeId").val();
  if (!employeeId) {
    $empty.addClass("d-none");
    $sel.prop("disabled", true);
    return;
  }

  if (employeeGroups.length) {
    $empty.addClass("d-none");
    $sel.prop("disabled", false);
    if (employeeGroups.length === 1) {
      $sel.val(String(employeeGroups[0].id));
      resetDependentFields("project");
      fetchProjects().catch(() => {});
    }
  } else {
    $empty.removeClass("d-none");
    $sel.prop("disabled", true);
  }
}

async function loadEmployeeGroups(employeeId) {
  if (!employeeId) {
    renderEmployeeGroupSelect([]);
    return [];
  }

  try {
    const json = await apiGet(
      apiUrl("/approve/employee-groups") + "?employee_id=" + encodeURIComponent(String(employeeId)),
    );
    if (!json?.success) {
      showToast(json?.message || "Unable to load assigned groups.", { type: "error" });
      renderEmployeeGroupSelect([]);
      return [];
    }
    renderEmployeeGroupSelect(json?.data || []);
    return json?.data || [];
  } catch {
    showToast("Unable to load assigned groups.", { type: "error" });
    renderEmployeeGroupSelect([]);
    return [];
  }
}

async function checkOnBehalfAccess() {
  const json = await apiGet(apiUrl("/approve/approver-groups"));
  if (json?.is_approver) {
    $("#onBehalfOpenBtn").removeClass("d-none");
  }
  return !!json?.is_approver;
}

function clearEmployeeSuggestions() {
  $("#obEmployeeSuggestions").addClass("d-none").empty();
}

function showEmployeeSuggestions(employees) {
  employeeResults = employees;
  const $box = $("#obEmployeeSuggestions").empty().removeClass("d-none");
  if (!employees.length) {
    $box.append('<div class="suggestion-empty">No matching employees found</div>');
    return;
  }

  employees.forEach((emp) => {
    $box.append(`
      <button type="button" class="suggestion-item" data-id="${emp.id}">
        <strong>${escapeHtml(emp.surname)}</strong> ${escapeHtml(emp.firstname || "")}
        <span class="ot-muted">Employee No. ${emp.id}${emp.group_abbr ? ` · ${escapeHtml(emp.group_abbr)}` : ""}</span>
      </button>
    `);
  });
}

async function selectEmployee(employee) {
  if (!employee) return;
  $("#obEmployeeId").val(employee.id);
  $("#obEmployeeSearch").val(`${employee.surname || ""}, ${employee.firstname || ""}`.trim());
  resetDependentFields("project");
  await loadEmployeeGroups(employee.id);
  reloadDateRules(employee.id).catch(() => {});
  clearEmployeeSuggestions();
}

function resetOnBehalfForm() {
  $("#onBehalfForm")[0].reset();
  employeeGroups = [];
  renderEmployeeGroupSelect([]);
  $("#obEmployeeId").val("");
  resetDependentFields("project");
  reloadDateRules(null).catch(() => {});
  clearEmployeeSuggestions();
}

function isObDateAllowed(isoDate) {
  return isAllowedRequestDate(isoDate);
}

function bindCascadeHandlers() {
  $(`#${getFieldId("group")}`).on("change", function () {
    resetDependentFields("project");
    if ($(this).val()) {
      enableField("project");
      fetchProjects().catch(() => {});
    }
  });

  $(`#${getFieldId("project")}`).on("change", function () {
    resetDependentFields("item");
    if ($(this).val()) {
      enableField("item");
      fetchItems().catch(() => {});
    }
  });

  $(`#${getFieldId("item")}`).on("change", function () {
    resetDependentFields("jobdesc");
    if ($(this).val()) {
      enableField("jobdesc");
      fetchJobs().catch(() => {});
    }
  });

  $(`#${getFieldId("jobdesc")}`).on("change", function () {
    resetDependentFields("work");
    if ($(this).val()) {
      enableField("work");
      fetchWorks().catch(() => {});
    }
  });
}

export function initOnBehalf() {
  configureFormFields(ON_BEHALF_FIELDS);
  bindDateField();
  bindCascadeHandlers();
  renderEmployeeGroupSelect([]);

  checkOnBehalfAccess()
    .then((isApprover) => {
      if (!isApprover) return;
      fetchLocations().catch(() => {});
      reloadDateRules(null).catch(() => {});
    })
    .catch(() => {});

  $("#obEmployeeSearch").on("input", function () {
    $("#obEmployeeId").val("");
    renderEmployeeGroupSelect([]);
    resetDependentFields("project");
    clearTimeout(searchTimer);
    const q = $(this).val().trim();
    if (q.length < 1) {
      clearEmployeeSuggestions();
      return;
    }

    searchTimer = setTimeout(async () => {
      const url = apiUrl("/approve/employees") + "?q=" + encodeURIComponent(q);
      try {
        const json = await apiGet(url);
        showEmployeeSuggestions(json?.data || []);
      } catch {
        clearEmployeeSuggestions();
        showToast("Unable to search employees.", { type: "error" });
      }
    }, 280);
  });

  $(document).on("click", "#obEmployeeSuggestions .suggestion-item", function () {
    const employee = employeeResults.find((emp) => String(emp.id) === String($(this).data("id")));
    selectEmployee(employee).catch(() => {});
  });

  $("#obResetBtn").on("click", () => {
    clearEmployeeSuggestions();
  });

  const onBehalfModalEl = document.getElementById("onBehalfModal");
  if (onBehalfModalEl) {
    onBehalfModalEl.addEventListener("hidden.bs.modal", () => {
      resetOnBehalfForm();
    });
    onBehalfModalEl.addEventListener("shown.bs.modal", () => {
      const employeeId = $("#obEmployeeId").val();
      if (employeeId) {
        loadEmployeeGroups(employeeId).catch(() => {});
      }
      reloadDateRules(employeeId || null).catch(() => {});
    });
  }

  $("#onBehalfForm").on("submit", async function (e) {
    e.preventDefault();

    const payload = {
      employee_id: $("#obEmployeeId").val(),
      date: $("#obDate").val(),
      group: $(`#${getFieldId("group")}`).val(),
      location: $(`#${getFieldId("location")}`).val(),
      project: $(`#${getFieldId("project")}`).val(),
      item: $(`#${getFieldId("item")}`).val(),
      jobdesc: $(`#${getFieldId("jobdesc")}`).val(),
      work: $(`#${getFieldId("work")}`).val(),
      hours: parseFloat($("#obHours").val()),
      remarks: $("#obRemarks").val().trim(),
    };

    if (
      !payload.employee_id ||
      !payload.date ||
      !isObDateAllowed(payload.date) ||
      !payload.group ||
      !payload.location ||
      !payload.project ||
      !payload.item ||
      !payload.jobdesc ||
      !payload.work ||
      !payload.hours ||
      payload.hours <= 0
    ) {
      if (payload.date && !isObDateAllowed(payload.date)) {
        validateDateInput(true);
      } else if (!payload.group) {
        showToast("Please select an assigned group for this employee.", { type: "warning" });
      } else {
        showToast("Please complete all required fields.", { type: "warning" });
      }
      return;
    }

    const $btn = $("#obSubmitBtn").prop("disabled", true);
    const body = new FormData();
    Object.entries(payload).forEach(([key, value]) => body.append(key, String(value)));

    try {
      const json = await apiPost(apiUrl("/approve/addovertime"), body);
      if (!json?.success) {
        showToast(json?.message || "Unable to submit the overtime request.", { type: "error" });
        return;
      }
      showToast(json.message || "The overtime request has been submitted and approved.", {
        type: "success",
        duration: 3500,
      });
      bootstrap.Modal.getInstance(document.getElementById("onBehalfModal"))?.hide();
      fetchRequest().catch(() => {});
    } catch {
      showToast("Unable to submit the overtime request.", { type: "error" });
    } finally {
      $btn.prop("disabled", false);
    }
  });

  $(document).on("click", function (e) {
    if (!$(e.target).closest("#obEmployeeSearch, #obEmployeeSuggestions").length) {
      clearEmployeeSuggestions();
    }
  });
}
