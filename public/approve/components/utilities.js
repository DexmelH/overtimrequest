export async function fetchWithTimeout(url, options = {}, timeout = 5000) {
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

export async function retryFetch(fn, attempts = 3, baseDelay = 250) {
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

export function normalizePayload(payload) {
  if (!payload) return [];
  if (Array.isArray(payload))
    return payload.map((p) => ({
      id: String(p.fldID ?? p.id ?? p.key ?? p.name),
      name: String(
        p.fldLocation ??
          p.abbreviation ??
          p.fldProject ??
          p.fldItem ??
          p.fldJob ??
          p.fldTOW,
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
          p.fldJob ??
          p.fldTOW,
      ),
    }));
  return [];
}

export function capitalizeFirst(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

export function statusClass(status) {
  if (status === 1) return "status-approved";
  if (status === 0) return "status-denied";
  return "status-pending";
}

export function badgeText(status) {
  if (status === 1) return "Approved";
  if (status === 0) return "Rejected";
  return "Pending";
}

export function formatDateISO(iso) {
  if (!iso) return "No action yet";
  const d = new Date(iso);
  return d.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function renderManagers(managers) {
  const $container = $("#managersList");
  $container.empty();

  const $list = $("<div>").addClass("approvals-list");

  (managers || []).forEach((m) => {
    const $row = $("<div>").addClass("approver").attr("tabindex", 0);

    const $info = $("<div>").addClass("mgr-info");
    const $avatar = $("<div>")
      .addClass("avatar small")
      .text(m.approver_id || "");
    const $text = $("<div>");
    const $nameEl = $("<div>")
      .addClass("mgr-name")
      .text(m.surname || "");
    const $roleEl = $("<div>")
      .addClass("mgr-role")
      .text(m.role || "");
    $text.append($nameEl, $roleEl);
    $info.append($avatar, $text);

    const $statusWrap = $("<div>").addClass("mgr-status");
    const $badge = $("<div>").addClass("status-badge " + statusClass(m.status));
    $badge.text(badgeText(m.status));
    const $date = $("<div>")
      .addClass("status-date")
      .text(
        m.status !== null
          ? m.date_accepted
            ? formatDateISO(m.date_accepted)
            : "No action yet"
          : "No action yet",
      );
    $statusWrap.append($badge, $date);

    $row.append($info, $statusWrap);
    $list.append($row);
  });

  $container.append($list);
  updateApprovalCount(managers);
}

export function updateApprovalCount(managers) {
  const total = (managers || []).length;
  const approved = (managers || []).filter((m) => m.status === 1).length;
  const $el = $("#approvalCount");
  if ($el.length) $el.text(`${approved} / ${total} approved`);
}

export function updateApprovalSummary(managers) {
  const $summary = $("#approvalSummary");
  $summary.empty();

  const approved = (managers || []).filter((m) => m.status === 1);
  const rejected = (managers || []).filter((m) => m.status === 0);

  if (approved.length) {
    const $h = $("<div>").addClass("info-label").text("Approved by");
    $summary.append($h);
    approved.forEach((a) => {
      const $item = $("<div>")
        .addClass("summary-item")
        .text(`${a.approver_id} · ${formatDateISO(a.date_accepted)}`);
      $summary.append($item);
    });
  }

  if (rejected.length) {
    const $h2 = $("<div>").addClass("info-label mt-2").text("Rejected by");
    $summary.append($h2);
    rejected.forEach((r) => {
      const $item = $("<div>")
        .addClass("summary-item")
        .text(`${r.approver_id} · ${formatDateISO(r.date_accepted)}`);
      $summary.append($item);
    });
  }

  if (!approved.length && !rejected.length) {
    const $none = $("<div>")
      .addClass("summary-item")
      .text("No approvals or rejections yet.");
    $summary.append($none);
  }
}
