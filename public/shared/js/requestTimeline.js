import {
  badgeText,
  formatDateISO,
  formatDateShort,
  historyStatusClass,
  historyStatusText,
  statusClass,
  statusText,
} from "./status.js";
import { escapeHtml } from "./escapeHtml.js";

function decisionLabel(status) {
  if (status == 1 || status === "1") return "Approved";
  if (status == 0 || status === "0") return "Rejected";
  return "No action yet";
}

function decisionTone(status) {
  if (status == 1 || status === "1") return "is-approved";
  if (status == 0 || status === "0") return "is-rejected";
  return "is-pending";
}

function isTruthyFlag(value) {
  return value === true || value === 1 || value === "1";
}

/**
 * Build the employee-facing request timeline (submit → approvers → outcome).
 */
export function renderRequestTimeline(item) {
  const $list = $("#requestTimeline").empty();
  if (!item) {
    $list.append('<p class="ot-muted small mb-0">No timeline available.</p>');
    return;
  }

  const events = [];

  const submitter = isTruthyFlag(item.is_on_behalf)
    ? `Submitted on behalf by ${item.submitted_by_name || `Approver #${item.submitted_by}`}`
    : "Submitted by you";
  events.push({
    tone: "is-submitted",
    title: submitter,
    meta: isTruthyFlag(item.is_on_behalf)
      ? "Filed for you and auto-approved by the submitting approver"
      : "Your overtime request was filed",
    time: item.date_created || item.request_date,
  });

  if (isTruthyFlag(item.is_follow_up) && item.origin_request_id) {
    const originDate = item.origin_request_date
      ? ` · ${formatDateShort(item.origin_request_date)}`
      : "";
    const originStatus =
      item.origin_request_status !== null && item.origin_request_status !== undefined
        ? ` · ${statusText(item.origin_request_status)}`
        : "";
    events.push({
      tone: "is-followup",
      title: "Follow-up request",
      meta: `Linked to original request #${item.origin_request_id}${originDate}${originStatus}`,
      time: item.date_created || item.request_date,
      linkId: item.origin_request_id,
      linkLabel: `Open request #${item.origin_request_id}`,
    });
  }

  const managers = Array.isArray(item.approver_details)
    ? item.approver_details.slice().sort((a, b) => {
        const levelA = Number(a.approval_level || 0);
        const levelB = Number(b.approval_level || 0);
        if (levelA !== levelB) return levelA - levelB;
        return String(a.date_accepted || "").localeCompare(String(b.date_accepted || ""));
      })
    : [];

  managers.forEach((m) => {
    const acted = m.status !== null && m.status !== undefined && m.status !== "";
    const name = m.surname || `Approver #${m.approver_id}`;
    const role = m.role || (m.approval_level ? `Level ${m.approval_level}` : "Approver");
    events.push({
      tone: decisionTone(m.status),
      title: acted ? `${name} ${decisionLabel(m.status).toLowerCase()}` : `Waiting on ${name}`,
      meta: acted
        ? `${role}${m.remarks ? ` · “${m.remarks}”` : ""}`
        : `${role} · no decision yet`,
      time: acted ? m.date_accepted : null,
      badgeStatus: m.status,
    });
  });

  if (!isPendingStatus(item.status)) {
    events.push({
      tone:
        item.status == 1 || item.status === "1"
          ? "is-approved"
          : item.status == 2 || item.status === "2"
            ? "is-cancelled"
            : "is-rejected",
      title: `Final status: ${historyStatusText(item)}`,
      meta: "Request closed",
      time: null,
      historyBadge: true,
    });
  }

  if (isTruthyFlag(item.has_follow_up) && item.follow_up_id) {
    const followDate = item.follow_up_request_date
      ? ` · ${formatDateShort(item.follow_up_request_date)}`
      : "";
    const followStatus =
      item.follow_up_status !== null && item.follow_up_status !== undefined
        ? ` · ${statusText(item.follow_up_status)}`
        : "";
    events.push({
      tone: "is-followup",
      title: "Follow-up filed",
      meta: `Request #${item.follow_up_id}${followDate}${followStatus}`,
      time: null,
      linkId: item.follow_up_id,
      linkLabel: `Open follow-up #${item.follow_up_id}`,
    });
  }

  events.forEach((event) => {
    let badge = "";
    if (event.historyBadge) {
      badge = `<span class="status-badge ${historyStatusClass(item)}">${escapeHtml(
        historyStatusText(item),
      )}</span>`;
    } else if (event.badgeStatus !== undefined) {
      badge = `<span class="status-badge ${statusClass(event.badgeStatus)}">${escapeHtml(
        badgeText(event.badgeStatus),
      )}</span>`;
    }
    const link = event.linkId
      ? `<button type="button" class="ot-timeline-link" data-request-id="${escapeHtml(
          event.linkId,
        )}">${escapeHtml(event.linkLabel || `Open #${event.linkId}`)}</button>`
      : "";
    const time = event.time
      ? `<div class="ot-timeline-time">${escapeHtml(formatDateISO(event.time))}</div>`
      : "";

    $list.append(`
      <div class="ot-timeline-item ${escapeHtml(event.tone)}">
        <div class="ot-timeline-dot" aria-hidden="true"></div>
        <div class="ot-timeline-body">
          <div class="ot-timeline-title-row">
            <div class="ot-timeline-title">${escapeHtml(event.title)}</div>
            ${badge}
          </div>
          <div class="ot-timeline-meta">${escapeHtml(event.meta)}</div>
          ${link}
          ${time}
        </div>
      </div>
    `);
  });
}

function isPendingStatus(status) {
  return status == null || status === "";
}

export function renderHistoryStatusBadges(item) {
  const $badge = $("#m_statusBadge").empty();
  if (!item) return;

  $badge.append(
    `<span class="status-badge ${historyStatusClass(item)}">${escapeHtml(
      historyStatusText(item),
    )}</span>`,
  );
  if (isTruthyFlag(item.is_on_behalf)) {
    $badge.append('<span class="status-badge status-onbehalf">On behalf</span>');
  }
  if (isTruthyFlag(item.is_follow_up)) {
    $badge.append('<span class="status-badge status-followup">Follow-up</span>');
  }
}
