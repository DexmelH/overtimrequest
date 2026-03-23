// approval.js
$(function () {
  const LEADERS = [
    { id: "leader1", name: "Leader 1", initials: "L1" },
    { id: "leader2", name: "Leader 2", initials: "L2" },
    { id: "leader3", name: "Leader 3", initials: "L3" },
    { id: "leader4", name: "Leader 4", initials: "L4" },
  ];

  function uid() {
    return (
      "ot-" +
      Date.now().toString(36) +
      "-" +
      Math.random().toString(36).slice(2, 8)
    );
  }
  function statusClass(status) {
    if (status === "Approved") return "status-approved";
    if (status === "Denied") return "status-denied";
    return "status-pending";
  }

  function saveHistory(list) {
    localStorage.setItem("overtime_history", JSON.stringify(list));
  }
  function loadHistory() {
    const raw = localStorage.getItem("overtime_history");
    if (!raw) return [];
    try {
      return JSON.parse(raw);
    } catch (e) {
      return [];
    }
  }

  function normalizeHistory(list) {
    return list.map((item) => {
      if (!item.approvals) {
        item.approvals = {};
        LEADERS.forEach((l) => {
          item.approvals[l.id] = null;
        });
      } else {
        LEADERS.forEach((l) => {
          if (!(l.id in item.approvals)) item.approvals[l.id] = null;
        });
      }
      if (!item.created) item.created = Date.now();
      return item;
    });
  }

  let history = normalizeHistory(loadHistory());

  if (history.length === 0) {
    const now = Date.now();
    history = [
      {
        id: uid(),
        date: new Date(now - 86400000 * 0).toISOString().slice(0, 10),
        group: "Maintenance",
        location: "Plant A",
        project: "PRJ-001",
        item: "Generator check",
        jobdesc: "Routine generator inspection",
        hours: 3,
        remarks: "N/A",
        status: "Pending",
        created: now - 3600 * 1000,
      },
      {
        id: uid(),
        date: new Date(now - 86400000 * 1).toISOString().slice(0, 10),
        group: "Engineering",
        location: "Site B",
        project: "PRJ-042",
        item: "Wiring repair",
        jobdesc: "Fixing short circuit",
        hours: 2.5,
        remarks: "Urgent",
        status: "Pending",
        created: now - 86400000 * 1,
      },
      {
        id: uid(),
        date: new Date(now - 86400000 * 3).toISOString().slice(0, 10),
        group: "Operations",
        location: "Plant C",
        project: "PRJ-099",
        item: "Inventory count",
        jobdesc: "Stocktake",
        hours: 4,
        remarks: "Weekend",
        status: "Pending",
        created: now - 86400000 * 3,
      },
    ];
    history[0].approvals = {
      leader1: "Approved",
      leader2: null,
      leader3: null,
      leader4: null,
    };
    history[1].approvals = {
      leader1: "Approved",
      leader2: "Approved",
      leader3: null,
      leader4: null,
    };
    history[1].status = "Approved";
    history[2].approvals = {
      leader1: "Denied",
      leader2: null,
      leader3: null,
      leader4: null,
    };
    history = normalizeHistory(history);
    saveHistory(history);
  } else {
    history = normalizeHistory(history);
  }

  function populateGroupSelect() {
    const groups = Array.from(
      new Set(history.map((h) => h.group).filter(Boolean)),
    );
    const $g = $("#groupSelect").empty();
    $g.append('<option value="all">All groups</option>');
    groups.forEach((g) =>
      $g.append(`<option value="${escapeHtml(g)}">${escapeHtml(g)}</option>`),
    );
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (m) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[m];
    });
  }

  let currentLeader = $("#leaderSelect").val() || "leader1";
  let currentGroup = "all";
  let currentTab = "toReview";

  function renderCounts() {
    const total = history.length;
    const pending = history.filter((h) => h.status === "Pending").length;
    const approved = history.filter((h) => h.status === "Approved").length;
    const denied = history.filter((h) => h.status === "Denied").length;
    $("#countsSummary").text(
      `Total ${total} · Pending ${pending} · Approved ${approved} · Denied ${denied}`,
    );
  }

  // NEW: To Review = requested yesterday or earlier (created <= end of yesterday)
  function isToReview(item) {
    if (item.status !== "Pending") return false;
    if (item.approvals[currentLeader] !== null) return false;
    const created = item.created || new Date(item.date).getTime();
    const now = new Date();
    // end of yesterday: yesterday at 23:59:59.999
    const yesterdayEnd = new Date(
      now.getFullYear(),
      now.getMonth(),
      now.getDate() - 1,
      23,
      59,
      59,
      999,
    ).getTime();
    return created <= yesterdayEnd;
  }

  function renderList() {
    const $list = $("#requestList").empty();
    const items = history.slice().sort((a, b) => b.created - a.created);
    let filtered = items;

    if (currentGroup !== "all") {
      filtered = filtered.filter((i) => i.group === currentGroup);
    }

    if (currentTab === "toReview") {
      filtered = filtered.filter(isToReview);
      $("#currentFilterLabel").text(
        "To Review (requested yesterday or earlier)",
      );
    } else if (currentTab === "pending") {
      filtered = filtered.filter((i) => i.status === "Pending");
      $("#currentFilterLabel").text("All Pending");
    } else {
      $("#currentFilterLabel").text("All Requests");
    }

    if (filtered.length === 0) {
      $list.append(
        '<div style="color:var(--muted); padding:12px;">No requests found for this filter.</div>',
      );
      renderCounts();
      return;
    }

    filtered.forEach((item) => {
      const approvalsCount = Object.values(item.approvals).filter(
        (v) => v === "Approved",
      ).length;
      const declinesCount = Object.values(item.approvals).filter(
        (v) => v === "Denied",
      ).length;

      const $row = $(`
        <div class="request-item" data-id="${item.id}" tabindex="0" role="button" aria-pressed="false">
          <div class="request-left">
            <div class="request-dot">${escapeHtml(item.date.slice(5))}</div>
            <div>
              <div class="request-meta">${escapeHtml(item.item)} <span style="color:var(--muted); font-weight:600; font-size:12px;"> — ${escapeHtml(item.project)}</span></div>
              <div class="request-sub">${escapeHtml(item.date)} · ${escapeHtml(item.hours)} hrs · ${escapeHtml(item.location)} · ${escapeHtml(item.group)}</div>
              <div class="inline-avatars"></div>
            </div>
          </div>
          <div style="text-align:right;">
            <div class="status-badge ${statusClass(item.status)}">${escapeHtml(item.status)}</div>
            <div style="margin-top:8px; font-size:12px; color:var(--muted);">A:${approvalsCount} · D:${declinesCount}</div>
          </div>
        </div>
      `);

      const $avatars = $row.find(".inline-avatars");
      LEADERS.forEach((l) => {
        const val = item.approvals[l.id];
        const $av = $("<div>").addClass("avatar");
        $av.text(l.initials);
        if (val === "Approved")
          $av.addClass("approved").attr("title", l.name + " approved");
        else if (val === "Denied")
          $av.addClass("declined").attr("title", l.name + " declined");
        else $av.addClass("pending").attr("title", l.name + " pending");
        $avatars.append($av);
      });

      $row.on("click keypress", function (e) {
        if (
          e.type === "click" ||
          (e.type === "keypress" && (e.key === "Enter" || e.key === " "))
        ) {
          openModal(item.id);
        }
      });

      $list.append($row);
    });

    renderCounts();
  }

  function openModal(id) {
    const item = history.find((h) => h.id === id);
    if (!item) return;
    $("#am_date").text(item.date);
    $("#am_group").text(item.group);
    $("#am_location").text(item.location);
    $("#am_project").text(item.project);
    $("#am_item").text(item.item);
    $("#am_hours").text(item.hours + " hrs");
    $("#am_jobdesc").text(item.jobdesc);
    $("#am_remarks").text(item.remarks || "-");
    $("#am_id").text(item.id);
    $("#am_statusBadge").html(
      `<div class="status-badge ${statusClass(item.status)}">${item.status}</div>`,
    );

    const $ap = $("#am_approvals").empty();
    LEADERS.forEach((l) => {
      const val = item.approvals[l.id];
      const pill = $("<div>").addClass("approver-pill").text(l.name);
      if (val === "Approved") pill.addClass("approver-approved").append(" ✓");
      else if (val === "Denied")
        pill.addClass("approver-declined").append(" ✕");
      else pill.addClass("approver-pending").append(" •");
      $ap.append(pill);
    });

    // progress bar update
    const approvals = Object.values(item.approvals).filter(
      (v) => v === "Approved",
    ).length;
    const declines = Object.values(item.approvals).filter(
      (v) => v === "Denied",
    ).length;
    const totalLeaders = LEADERS.length;
    const approvalsPct = Math.round((approvals / totalLeaders) * 100);
    const declinesPct = Math.round((declines / totalLeaders) * 100);

    $("#progressApprovals").css("width", approvalsPct + "%");
    $("#progressDeclines").css("width", declinesPct + "%");
    $("#progressText").text(`${approvals} approvals · ${declines} declines`);

    const myAction = item.approvals[currentLeader];
    if (item.status !== "Pending") {
      $("#approveBtn, #declineBtn")
        .prop("disabled", true)
        .addClass("secondary");
    } else {
      $("#approveBtn, #declineBtn")
        .prop("disabled", false)
        .removeClass("secondary");
      if (myAction === "Approved") {
        $("#approveBtn").prop("disabled", true).addClass("secondary");
        $("#declineBtn").prop("disabled", false).removeClass("secondary");
      } else if (myAction === "Denied") {
        $("#declineBtn").prop("disabled", true).addClass("secondary");
        $("#approveBtn").prop("disabled", false).removeClass("secondary");
      } else {
        $("#approveBtn, #declineBtn")
          .prop("disabled", false)
          .removeClass("secondary");
      }
    }

    $("#approvalModal").attr("aria-hidden", "false").fadeIn(120);
    $(".modal-panel").attr("tabindex", "-1").focus();
    $("#approvalModal").data("openId", id);
    $("body").css("overflow", "hidden");
  }

  function closeModal() {
    $("#approvalModal").attr("aria-hidden", "true").fadeOut(120);
    $("#approvalModal").removeData("openId");
    $("body").css("overflow", "");
  }

  function setLeaderDecision(id, leaderId, decision) {
    const idx = history.findIndex((h) => h.id === id);
    if (idx === -1) return;
    history[idx].approvals[leaderId] = decision;

    const approvals = Object.values(history[idx].approvals).filter(
      (v) => v === "Approved",
    ).length;
    const declines = Object.values(history[idx].approvals).filter(
      (v) => v === "Denied",
    ).length;

    if (approvals >= 2) {
      history[idx].status = "Approved";
    } else if (declines >= 2) {
      history[idx].status = "Denied";
    } else {
      history[idx].status = "Pending";
    }

    saveHistory(history);
    renderList();
    openModal(id);
  }

  $("#leaderSelect").on("change", function () {
    currentLeader = $(this).val();
    renderList();
  });

  $("#groupSelect").on("change", function () {
    currentGroup = $(this).val();
    renderList();
  });

  $(".tab-btn").on("click", function () {
    $(".tab-btn").removeClass("active");
    $(this).addClass("active");
    currentTab = $(this).data("tab");
    renderList();
  });

  $("#modalClose, #modalCloseBtn, #modalBackdrop").on("click", function () {
    closeModal();
  });
  $(document).on("keydown", function (e) {
    if (
      e.key === "Escape" &&
      $("#approvalModal").attr("aria-hidden") === "false"
    )
      closeModal();
  });

  $("#approveBtn").on("click", function () {
    const id = $("#approvalModal").data("openId");
    if (!id) return;
    setLeaderDecision(id, currentLeader, "Approved");
  });

  $("#declineBtn").on("click", function () {
    const id = $("#approvalModal").data("openId");
    if (!id) return;
    setLeaderDecision(id, currentLeader, "Denied");
  });

  populateGroupSelect();
  renderList();

  window.addEventListener("storage", function (e) {
    if (e.key === "overtime_history") {
      history = normalizeHistory(loadHistory());
      populateGroupSelect();
      renderList();
    }
  });
});
