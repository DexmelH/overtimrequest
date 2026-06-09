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
