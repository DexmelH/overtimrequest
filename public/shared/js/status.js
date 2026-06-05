export function statusClass(status) {
  if (status === 1 || status === "1") return "status-approved";
  if (status === 0 || status === "0") return "status-denied";
  return "status-pending";
}

export function statusText(status) {
  if (status === 1 || status === "1") return "Approved";
  if (status === 0 || status === "0") return "Denied";
  return "Pending";
}

export function badgeText(status) {
  if (status === 1 || status === "1") return "Approved";
  if (status === 0 || status === "0") return "Rejected";
  return "Pending";
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
