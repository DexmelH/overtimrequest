import { apiUrl } from "../../shared/js/api.js";
import { apiGet, normalizePayload } from "../../shared/js/http.js";

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}

export function createProjectAllocations({
  containerId,
  addButtonId,
  totalId,
  groupSelector,
}) {
  const containerSelector = `#${containerId}`;
  const addButtonSelector = `#${addButtonId}`;
  const totalSelector = `#${totalId}`;
  let projects = [];

  function selectedProjectIds() {
    return new Set(
      $(`${containerSelector} .project-allocation-project`)
        .map((_, element) => String($(element).val() || ""))
        .get()
        .filter(Boolean),
    );
  }

  function projectOptions(selectedValue = "") {
    const selectedIds = selectedProjectIds();
    return [
      '<option value="">Select project</option>',
      ...projects.map((project) => {
        const id = String(project.id);
        const disabled = selectedIds.has(id) && id !== String(selectedValue);
        return `<option value="${escapeHtml(id)}"${id === String(selectedValue) ? " selected" : ""}${disabled ? " disabled" : ""}>${escapeHtml(project.name)}</option>`;
      }),
    ].join("");
  }

  function refreshOptions() {
    $(`${containerSelector} .project-allocation-project`).each(function () {
      const value = $(this).val();
      $(this).html(projectOptions(value)).val(value);
    });
    $(addButtonSelector).prop(
      "disabled",
      projects.length === 0 || selectedProjectIds().size >= projects.length,
    );
  }

  function updateTotal() {
    const total = $(`${containerSelector} .project-allocation-hours`)
      .map((_, element) => {
        const hours = Number($(element).val());
        return Number.isInteger(hours) && hours > 0 ? hours : 0;
      })
      .get()
      .reduce((sum, hours) => sum + hours, 0);
    $(totalSelector).text(`${total} hrs`);
    return total;
  }

  function addRow(values = {}) {
    const rowCount = $(`${containerSelector} .project-allocation-row`).length;
    const removeDisabled = rowCount === 0 ? " disabled" : "";
    $(containerSelector).append(`
      <div class="project-allocation-row">
        <select class="form-select project-allocation-project" aria-label="Project" required${projects.length ? "" : " disabled"}>
          ${projectOptions(values.project_id || "")}
        </select>
        <div class="input-group project-allocation-hours-wrap">
          <input
            type="number"
            class="form-control project-allocation-hours"
            min="1"
            step="1"
            value="${escapeHtml(values.hours || "")}"
            placeholder="Hours"
            aria-label="Project hours"
            required
          />
          <span class="input-group-text">hrs</span>
        </div>
        <button type="button" class="ot-btn ot-btn-danger project-allocation-remove" title="Remove project"${removeDisabled}>
          <i class="bi bi-trash" aria-hidden="true"></i>
          <span class="visually-hidden">Remove project</span>
        </button>
      </div>
    `);
    refreshOptions();
    updateRemoveButtons();
    updateTotal();
  }

  function updateRemoveButtons() {
    const onlyOne = $(`${containerSelector} .project-allocation-row`).length <= 1;
    $(`${containerSelector} .project-allocation-remove`).prop("disabled", onlyOne);
  }

  function reset({ keepProjects = false } = {}) {
    if (!keepProjects) projects = [];
    $(containerSelector).empty();
    addRow();
    $(addButtonSelector).prop("disabled", projects.length === 0);
  }

  async function loadProjects() {
    const $group = $(groupSelector);
    const group = $group.find("option:selected").data("abbr") || $group.find("option:selected").text();
    projects = [];
    reset({ keepProjects: true });
    $(addButtonSelector).prop("disabled", true);

    if (!$group.val()) return;

    $(containerSelector).html('<div class="ot-muted small">Loading projects...</div>');
    try {
      const json = await apiGet(apiUrl("/projects") + "?group=" + encodeURIComponent(group));
      projects = normalizePayload(json);
      $(containerSelector).empty();
      addRow();
      $(addButtonSelector).prop("disabled", projects.length === 0);
    } catch {
      $(containerSelector).html('<div class="text-danger small">Failed to load projects.</div>');
    }
  }

  function getAllocations() {
    return $(`${containerSelector} .project-allocation-row`)
      .map((_, row) => ({
        project_id: Number($(row).find(".project-allocation-project").val()),
        hours: Number($(row).find(".project-allocation-hours").val()),
      }))
      .get();
  }

  function isValid() {
    const allocations = getAllocations();
    if (!allocations.length) return false;
    const ids = new Set();
    return allocations.every(({ project_id: projectId, hours }) => {
      if (!projectId || !Number.isInteger(hours) || hours <= 0 || ids.has(projectId)) return false;
      ids.add(projectId);
      return true;
    });
  }

  $(addButtonSelector).on("click", () => addRow());
  $(containerSelector)
    .on("change", ".project-allocation-project", refreshOptions)
    .on("input", ".project-allocation-hours", updateTotal)
    .on("click", ".project-allocation-remove", function () {
      $(this).closest(".project-allocation-row").remove();
      refreshOptions();
      updateRemoveButtons();
      updateTotal();
    });

  reset();

  return {
    loadProjects,
    reset,
    getAllocations,
    isValid,
    updateTotal,
  };
}
