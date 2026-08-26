export function statusClass(status) {
  if (status === 1 || status === "1") return "status-approved";
  if (status === 0 || status === "0") return "status-denied";
  if (status === 2 || status === "2") return "status-cancelled";
  return "status-pending";
}

export function statusText(status) {
  if (status === 1 || status === "1") return "Approved";
  if (status === 0 || status === "0") return "Denied";
  if (status === 2 || status === "2") return "Cancelled";
  return "Pending";
}

export function badgeText(status) {
  if (status === 1 || status === "1") return "Approved";
  if (status === 0 || status === "0") return "Rejected";
  if (status === 2 || status === "2") return "Cancelled";
  return "Pending";
}

export function isPending(status) {
  return status == null || status === "";
}

const REQUEST_STATUS_CLASSES = {
  pending: "status-pending",
  approved: "status-approved",
  auto_approved: "status-auto-approved",
  rejected: "status-denied",
  auto_rejected: "status-auto-rejected",
  cancelled: "status-cancelled",
};

const APPROVER_ACTION_CLASSES = {
  action_needed: "status-action-needed",
  you_approved: "status-you-approved",
  you_rejected: "status-you-rejected",
};

/** Badge class for a `status_code` computed by the API. */
export function requestStatusClass(code) {
  return REQUEST_STATUS_CLASSES[code] || "status-pending";
}

/** Badge class for an `action_code` computed by the API. */
export function approverActionClass(code) {
  return APPROVER_ACTION_CLASSES[code] || "status-action-needed";
}

export function formatDateISO(iso) {
  if (!iso) return "No action yet";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return String(iso);
  return d.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export function formatDateShort(iso) {
  if (!iso) return "—";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return String(iso);
  return d.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}
