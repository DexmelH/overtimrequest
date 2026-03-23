/// POPULATE
function populateLocationSelect(locations, { preserveValue = true } = {}) {
  const $sel = $("#location");
  console.log($sel.length, locations);
  if ($sel.length === 0) return;
  const current = preserveValue ? $sel.val() : null;
  console.log(current);
  $sel.empty();
  $sel.append('<option value="">Select Location</option>');
  locations.forEach((loc) => {
    const opt = $("<option>").attr("value", loc.id).text(loc.name);
    $sel.append(opt);
  });
  if (preserveValue && current) {
    if ($sel.find(`option[value="${current}"]`).length) $sel.val(current);
  }
}

function populateGroupSelect(groups, { preserveValue = true } = {}) {
  const $sel = $("#group");
  console.log($sel.length, groups);
  if ($sel.length === 0) return;
  const current = preserveValue ? $sel.val() : null;
  console.log(current);
  $sel.empty();
  $sel.append('<option value="">Select Group</option>');
  groups.forEach((group) => {
    const opt = $("<option>").attr("value", group.id).text(group.name);
    $sel.append(opt);
  });
  if (preserveValue && current) {
    if ($sel.find(`option[value="${current}"]`).length) $sel.val(current);
  }
}

function populateProjectSelect(projects, { preserveValue = true } = {}) {
  const $sel = $("#project");
  console.log($sel.length, projects);
  if ($sel.length === 0) return;
  const current = preserveValue ? $sel.val() : null;
  console.log(current);
  $sel.empty();
  $sel.append('<option value="">Select Project</option>');
  projects.forEach((project) => {
    const opt = $("<option>").attr("value", project.id).text(project.name);
    $sel.append(opt);
  });
  if (preserveValue && current) {
    if ($sel.find(`option[value="${current}"]`).length) $sel.val(current);
  }
}

function populateItemSelect(items, { preserveValue = true } = {}) {
  const $sel = $("#item");
  console.log($sel.length, items);
  if ($sel.length === 0) return;
  const current = preserveValue ? $sel.val() : null;
  console.log(current);
  $sel.empty();
  $sel.append('<option value="">Select Item</option>');
  items.forEach((item) => {
    const opt = $("<option>").attr("value", item.id).text(item.name);
    $sel.append(opt);
  });
  if (preserveValue && current) {
    if ($sel.find(`option[value="${current}"]`).length) $sel.val(current);
  }
}

function populateJobSelect(jobs, { preserveValue = true } = {}) {
  const $sel = $("#jobdesc");
  console.log($sel.length, jobs);
  if ($sel.length === 0) return;
  const current = preserveValue ? $sel.val() : null;
  console.log(current);
  $sel.empty();
  $sel.append('<option value="">Select Job</option>');
  jobs.forEach((job) => {
    const opt = $("<option>").attr("value", job.id).text(job.name);
    $sel.append(opt);
  });
  if (preserveValue && current) {
    if ($sel.find(`option[value="${current}"]`).length) $sel.val(current);
  }
}

/// ACTIONS

async function addOvertimeRequest(formData) {
  const optimistic = Object.assign({ created: Date.now() }, formData);
  const newFormData = new FormData();
  newFormData.append("date", formData.date);
  newFormData.append("group", formData.group);
  newFormData.append("location", formData.location);
  newFormData.append("project", formData.project);
  newFormData.append("item", formData.item);
  newFormData.append("jobdesc", formData.jobdesc);
  newFormData.append("remarks", formData.remarks);
  newFormData.append("hours", formData.hours);

  history.push(optimistic);

  try {
    const response = await fetch("php/addOvertime.php", {
      method: "POST",
      credentials: "same-origin",
      body: newFormData,
    });
    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const payload = await response.json();
    if (payload && payload.data) {
      renderHistory("all");
    } else {
      await fetchHistory({ full: true });
    }
    showToast("Overtime request submitted successfully.", { type: "success" });
  } catch (error) {
    renderHistory("all");
    console.log("Failed to add overtime request:", error);
    showToast("Failed to submit request. Please try again.", { type: "error" });
  }
}

/// FETCH
async function fetchHistory({ since = 0, full = false, filter = "all" } = {}) {
  try {
    const response = await fetch("php/getHistory.php", {
      method: "GET",
      credentials: "same-origin",
    });
    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();

    const incoming = Array.isArray(json)
      ? json
      : Array.isArray(json.data)
        ? json.data
        : [];

    history = incoming;

    renderHistory(filter);
    return incoming;
  } catch (error) {
    console.log("Failed to fetch history:", error);
    return [];
  }
}

async function fetchLocations({ showLoading = true } = {}) {
  const $sel = $("#location");
  if ($sel.length && showLoading) {
    $sel.prop("disabled", true);
    const prev = $sel.data("prev") || null;
    $sel.data("prev-text", prev);
    $sel.empty().append('<option value="">Loading...</option>');
  }

  try {
    const response = await retryFetch(
      () =>
        fetchWithTimeout(
          "php/getLocations.php",
          {
            method: "GET",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
          },
          8000,
        ),
      3,
      300,
    );

    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();
    const locations = normalizePayload(json);
    if (locations.length) {
      populateLocationSelect(locations);
    } else {
      populateLocationSelect([]);
    }
  } catch (error) {
    console.error("Failed to fetch locations:", error);
    if ($sel.length) {
      $sel.empty().append('<option value="">Failed to load locations</option>');
    }
    return [];
  } finally {
    if ($sel.length) {
      $sel.prop("disabled", false);
    }
  }
}

async function fetchGroups({ showLoading = true } = {}) {
  const $sel = $("#group");
  if ($sel.length && showLoading) {
    $sel.prop("disabled", true);
    const prev = $sel.data("prev") || null;
    $sel.data("prev-text", prev);
    $sel.empty().append('<option value="">Loading...</option>');
  }

  try {
    const response = await retryFetch(
      () =>
        fetchWithTimeout(
          "php/getGroups.php",
          {
            method: "GET",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
          },
          8000,
        ),
      3,
      300,
    );

    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();
    const groups = normalizePayload(json);
    if (groups.length) {
      populateGroupSelect(groups);
    } else {
      populateGroupSelect([]);
    }
  } catch (error) {
    console.error("Failed to fetch groups:", error);
    if ($sel.length) {
      $sel.empty().append('<option value="">Failed to load groups</option>');
    }
    return [];
  } finally {
    if ($sel.length) {
      $sel.prop("disabled", false);
    }
  }
}

async function fetchProjects({ showLoading = true } = {}) {
  const $sel = $("#project");
  if ($sel.length && showLoading) {
    $sel.prop("disabled", true);
    const prev = $sel.data("prev") || null;
    $sel.data("prev-text", prev);
    $sel.empty().append('<option value="">Loading...</option>');
  }

  const group = $("#group option:selected").text();

  try {
    const response = await retryFetch(
      () =>
        fetchWithTimeout(
          "php/getProjects.php?group=" + encodeURIComponent(group),
          {
            method: "GET",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
          },
          8000,
        ),
      3,
      300,
    );

    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();
    const projects = normalizePayload(json);
    if (projects.length) {
      populateProjectSelect(projects);
    } else {
      populateProjectSelect([]);
    }
  } catch (error) {
    console.error("Failed to fetch projects:", error);
    if ($sel.length) {
      $sel.empty().append('<option value="">Failed to load projects</option>');
    }
    return [];
  } finally {
    if ($sel.length) {
      $sel.prop("disabled", false);
    }
  }
}

async function fetchItems({ showLoading = true } = {}) {
  const $sel = $("#item");
  if ($sel.length && showLoading) {
    $sel.prop("disabled", true);
    const prev = $sel.data("prev") || null;
    $sel.data("prev-text", prev);
    $sel.empty().append('<option value="">Loading...</option>');
  }

  const project = $("#project").val();

  try {
    const response = await retryFetch(
      () =>
        fetchWithTimeout(
          "php/getItems.php?project=" + encodeURIComponent(project),
          {
            method: "GET",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
          },
          8000,
        ),
      3,
      300,
    );

    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();
    const items = normalizePayload(json);
    if (items.length) {
      populateItemSelect(items);
    } else {
      populateItemSelect([]);
    }
  } catch (error) {
    console.error("Failed to fetch items:", error);
    if ($sel.length) {
      $sel.empty().append('<option value="">Failed to load items</option>');
    }
    return [];
  } finally {
    if ($sel.length) {
      $sel.prop("disabled", false);
    }
  }
}

async function fetchJobs({ showLoading = true } = {}) {
  const $sel = $("#jobdesc");
  if ($sel.length && showLoading) {
    $sel.prop("disabled", true);
    const prev = $sel.data("prev") || null;
    $sel.data("prev-text", prev);
    $sel.empty().append('<option value="">Loading...</option>');
  }

  const item = $("#item").val();

  try {
    const response = await retryFetch(
      () =>
        fetchWithTimeout(
          "php/getJobs.php?item=" + encodeURIComponent(item),
          {
            method: "GET",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
          },
          8000,
        ),
      3,
      300,
    );

    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const json = await response.json();
    const jobs = normalizePayload(json);
    if (jobs.length) {
      populateJobSelect(jobs);
    } else {
      populateJobSelect([]);
    }
  } catch (error) {
    console.error("Failed to fetch jobs:", error);
    if ($sel.length) {
      $sel.empty().append('<option value="">Failed to load jobs</option>');
    }
    return [];
  } finally {
    if ($sel.length) {
      $sel.prop("disabled", false);
    }
  }
}

/// UTILITIES
async function fetchWithTimeout(url, options = {}, timeout = 5000) {
  const controller = new AbortController();
  const id = setTimeout(() => controller.abort(), timeout);
  options.signal = controller.signal;
  try {
    const response = await fetch(url, options);
    clearTimeout(id);
    return response;
  } catch (error) {
    clearTimeout(id);
    throw error;
  }
}

async function retryFetch(fn, attempts = 3, baseDelay = 250) {
  let lastErr;
  for (let i = 0; i < attempts; i++) {
    try {
      return await fn();
    } catch (err) {
      lastErr = err;
      const delay = baseDelay * Math.pow(2, i);
      await new Promise((res) => setTimeout(res, delay));
    }
  }
  throw lastErr;
}

function normalizePayload(payload) {
  if (!payload) return [];
  if (Array.isArray(payload))
    return payload.map((p) => ({
      id: String(p.fldID ?? p.id ?? p.key ?? p.name),
      name: String(
        p.fldLocation ??
          p.abbreviation ??
          p.fldProject ??
          p.fldItem ??
          p.fldJob,
      ),
    }));
  if (payload.data && Array.isArray(payload.data))
    return payload.data.map((p) => ({
      id: String(p.fldID ?? p.id ?? p.key ?? p.name),
      name: String(
        p.fldLocation ??
          p.abbreviation ??
          p.fldProject ??
          p.fldItem ??
          p.fldJob,
      ),
    }));
  return [];
}

function showToast(message, { type = "default", duration = 4000 } = {}) {
  try {
    const container = document.getElementById("toastContainer");
    if (!container) return alert(message); // fallback

    const toast = document.createElement("div");
    toast.className =
      "toast " +
      (type === "success" ? "success" : type === "error" ? "error" : "");
    toast.setAttribute("role", "status");
    toast.innerHTML = `<div class="toast-msg">${escapeHtml(String(message))}</div>
                       <button class="close-btn" aria-label="Close">×</button>`;

    // close handler
    toast.querySelector(".close-btn").addEventListener("click", () => {
      hideToast(toast);
    });

    container.appendChild(toast);

    // show animation
    requestAnimationFrame(() => toast.classList.add("show"));

    // auto remove
    const t = setTimeout(() => hideToast(toast), duration);

    // remove function
    function hideToast(el) {
      clearTimeout(t);
      el.classList.remove("show");
      el.addEventListener(
        "transitionend",
        () => {
          if (el.parentNode) el.parentNode.removeChild(el);
        },
        { once: true },
      );
    }

    return toast;
  } catch (err) {
    // last resort
    console.warn("showToast error", err);
    alert(message);
  }
}

function escapeHtml(str) {
  return String(str === undefined || str === null ? "" : str).replace(
    /[&<>"']/g,
    function (m) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[m];
    },
  );
}

// Modal controls
function openModal(id) {
  const item = history.find((h) => h.id === id);
  if (!item) return;
  $("#m_date").text(item.request_date);
  $("#m_group").text(item.group_name);
  $("#m_location").text(item.location_name);
  $("#m_project").text(item.project_name);
  $("#m_item").text(item.item_name);
  $("#m_hours").text(item.duration + " hrs");
  $("#m_jobdesc").text(item.job_description);
  $("#m_remarks").text(item.remarks || "-");
  $("#m_statusBadge").html(
    `<div class="status-badge ${statusClass(item.status)}">${statusText(item.status)}</div>`,
  );

  // show modal
  $("#detailModal").attr("aria-hidden", "false").fadeIn(120);
  // focus panel for accessibility
  $(".modal-panel").attr("tabindex", "-1").focus();
  // store current open id
  $("#detailModal").data("openId", id);
  // prevent body scroll while modal open
  $("body").css("overflow", "hidden");
}

function closeModal() {
  $("#detailModal").attr("aria-hidden", "true").fadeOut(120);
  $("#detailModal").removeData("openId");
  $("body").css("overflow", "");
}

// Close handlers
$("#modalClose, #modalCloseBtn, #modalBackdrop").on("click", function () {
  closeModal();
});

$(document).on("keydown", function (e) {
  if (e.key === "Escape" && $("#detailModal").attr("aria-hidden") === "false") {
    closeModal();
  }
});

$("#group").on("change", function () {
  $("#project").disabled = false;
  fetchProjects().catch(() => {});
});

$("#project").on("change", function () {
  $("#item").disabled = false;
  fetchItems().catch(() => {});
});

$("#item").on("change", function () {
  $("#jobdesc").disabled = false;
  fetchJobs().catch(() => {});
});

/// DATA
let history = [];

$(function () {
  fetchHistory({ full: true }).catch(() => {});
  fetchLocations().catch(() => {});
  fetchGroups().catch(() => {});

  window.fetchHistory = fetchHistory;
  window.fetchLocations = fetchLocations;
  window.fetchGroups = fetchGroups;

  // Utilities
  function uid() {
    return (
      "ot-" +
      Date.now().toString(36) +
      "-" +
      Math.random().toString(36).slice(2, 8)
    );
  }

  // Form submit
  $("#overtimeForm").on("submit", function (e) {
    e.preventDefault();

    const date = $("#date").val();
    const group = $("#group").val();
    const location = $("#location").val().trim();
    const project = $("#project").val().trim();
    const item = $("#item").val().trim();
    const jobdesc = $("#jobdesc").val().trim();
    const hours = parseFloat($("#hours").val());
    const remarks = $("#remarks").val().trim();

    if (
      !date ||
      !group ||
      !location ||
      !project ||
      !item ||
      !jobdesc ||
      !hours ||
      hours <= 0
    ) {
      alert("Please fill all required fields with valid values.");
      return;
    }

    const newReq = {
      date,
      group,
      location,
      project,
      item,
      jobdesc,
      hours,
      remarks,
    };

    addOvertimeRequest(newReq).catch(() => {});
    renderHistory($(".filter-btn.active").data("filter"));
    openModal(newReq.id);

    $("#overtimeForm")[0].reset();
    $("#hours").val(1);
    $("#date").val(date);
  });

  // Reset button
  $("#resetBtn").on("click", function () {
    $("#overtimeForm")[0].reset();
    $("#hours").val(1);
  });

  // Filters
  $(".filter-btn").on("click", function () {
    $(".filter-btn").removeClass("active");
    $(this).addClass("active");
    const f = $(this).data("filter");
    renderHistory(f);
  });
});
