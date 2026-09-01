import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";
import { escapeHtml } from "../../shared/js/escapeHtml.js";
import { dataSignature } from "../../shared/js/livePoll.js";

let lastSignature = null;
let countdownTimer = null;
let latestCutoff = null;
let latestPayload = null;

function formatHours(value) {
  const n = Number(value || 0);
  return Number.isInteger(n) ? String(n) : n.toFixed(1);
}

function formatRate(rate) {
  if (rate === null || rate === undefined) return "—";
  return `${Math.round(Number(rate) * 100)}%`;
}

function formatCountdown(secondsUntil, isPast) {
  if (isPast) return "Past cutoff";
  const total = Math.max(0, Number(secondsUntil) || 0);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  if (hours > 0) return `${hours}h ${minutes}m left`;
  if (minutes > 0) return `${minutes}m left`;
  return "<1m left";
}

function isDashboardOpen() {
  return !!document.querySelector("#dashboardModal.show");
}

function riskTone(count, isPast) {
  const $card = $(".dash-cutoff-risk");
  $card.removeClass("is-clear is-warn is-danger");
  if (count <= 0) {
    $card.addClass("is-clear");
  } else if (isPast) {
    $card.addClass("is-danger");
  } else {
    $card.addClass("is-warn");
  }
}

function updateHeaderBadge(riskCount, isPast) {
  const $badge = $("#dashBtnBadge");
  const $btn = $("#dashboardOpenBtn");
  $btn.removeClass("has-risk has-urgent");

  if (riskCount <= 0) {
    $badge.addClass("d-none").text("");
    return;
  }

  $badge.removeClass("d-none").text(String(riskCount));
  $btn.addClass(isPast ? "has-urgent" : "has-risk");
}

function renderRiskList(items) {
  const $list = $("#dashRiskList").empty();
  const $empty = $("#dashRiskEmpty");
  const count = items?.length || 0;
  $("#dashRiskCountLabel").text(
    `${count} request${count === 1 ? "" : "s"}`,
  );

  if (!count) {
    $list.addClass("d-none");
    $empty.removeClass("d-none");
    return;
  }

  $empty.addClass("d-none");
  $list.removeClass("d-none");
  items.slice(0, 8).forEach((item) => {
    $list.append(`
      <li>
        <strong>${escapeHtml(item.employee_name || `Emp #${item.employee_id || "—"}`)}</strong>
        <span class="ot-muted">
          ${escapeHtml(item.group_name || "—")} · ${escapeHtml(formatHours(item.duration))} hrs
        </span>
      </li>
    `);
  });
}

function tickCountdown() {
  if (!latestCutoff) return;
  let seconds = Number(latestCutoff.seconds_until || 0);
  if (!latestCutoff.is_past) {
    if (!tickCountdown._anchor) {
      tickCountdown._anchor = Date.now();
      tickCountdown._baseSeconds = seconds;
    }
    const elapsed = Math.floor((Date.now() - tickCountdown._anchor) / 1000);
    seconds = tickCountdown._baseSeconds - elapsed;
    if (seconds <= 0) {
      latestCutoff.is_past = true;
      seconds = 0;
    }
  }

  const count = Number(latestPayload?.cutoff_risk?.count || 0);
  const label = latestCutoff.label || "cutoff";
  updateHeaderBadge(count, !!latestCutoff.is_past);

  // Full modal copy only needs updating while the dashboard is open.
  if (!isDashboardOpen()) return;

  if (latestCutoff.is_past) {
    $("#dashCutoffSub").text(
      count > 0
        ? `Past ${label} · awaiting finalizer`
        : `Past ${label} · nothing at risk`,
    );
  } else {
    $("#dashCutoffSub").text(
      `${formatCountdown(seconds, false)} until ${label}`,
    );
  }
  riskTone(count, !!latestCutoff.is_past);
}

export function renderApproverDashboard(payload) {
  if (!payload) return;
  latestPayload = payload;

  const pendingHours = payload.pending_hours_today ?? 0;
  const pendingCount = payload.pending_count_today ?? 0;
  const risk = payload.cutoff_risk || {};
  const auto = payload.auto_reject_rate_30d || {};
  latestCutoff = payload.cutoff || null;
  tickCountdown._anchor = Date.now();
  tickCountdown._baseSeconds = Number(latestCutoff?.seconds_until || 0);

  const riskCount = Number(risk.count || 0);
  updateHeaderBadge(riskCount, !!latestCutoff?.is_past);

  $("#dashPendingHours").text(formatHours(pendingHours));
  $("#dashPendingHoursSub").text(
    `${pendingCount} open request${pendingCount === 1 ? "" : "s"} for today`,
  );

  $("#dashCutoffRisk").text(riskCount);
  riskTone(riskCount, !!latestCutoff?.is_past);
  renderRiskList(risk.items || []);
  tickCountdown();

  $("#dashAutoRejectRate").text(formatRate(auto.rate));
  if (!auto.finalized) {
    $("#dashAutoRejectSub").text("No finalized requests in the last 30 days");
  } else {
    $("#dashAutoRejectSub").text(
      `${auto.auto_rejected || 0} of ${auto.finalized} finalized auto-rejected`,
    );
  }

  $(".dash-auto-reject").toggleClass(
    "is-high",
    Number(auto.rate || 0) >= 0.25 && Number(auto.finalized || 0) > 0,
  );

  if (payload.date) {
    $("#dashboardModalSub").text(
      `Snapshot for ${payload.date} · pending hours, cutoff risk, and 30-day auto-reject rate`,
    );
  }
}

/**
 * @returns {Promise<boolean>} true when dashboard data changed
 */
export async function fetchApproverDashboard({ silent = false } = {}) {
  try {
    const json = await apiGet(apiUrl("/approve/dashboard"));
    if (!json?.success) {
      return false;
    }
    const signature = dataSignature(json);
    if (silent && signature === lastSignature) {
      tickCountdown();
      return false;
    }
    lastSignature = signature;
    renderApproverDashboard(json);
    return true;
  } catch (error) {
    if (!silent) {
      console.error("Failed to load approver dashboard:", error);
    }
    throw error;
  }
}

export function startDashboardCountdown() {
  if (countdownTimer) return;
  countdownTimer = setInterval(tickCountdown, 30000);
}

export function initApproverDashboardUi() {
  const modalEl = document.getElementById("dashboardModal");
  if (!modalEl) return;

  modalEl.addEventListener("show.bs.modal", () => {
    fetchApproverDashboard().catch(() => {});
  });

  modalEl.addEventListener("shown.bs.modal", () => {
    tickCountdown();
  });
}
