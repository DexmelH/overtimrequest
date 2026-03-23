export function statusClass(status) {
  if (status === 1) return "status-approved";
  if (status === 0) return "status-denied";
  return "status-pending";
}

export function statusText(status) {
  if (status === 1) return "Approved";
  if (status === 0) return "Denied";
  return "Pending";
}
