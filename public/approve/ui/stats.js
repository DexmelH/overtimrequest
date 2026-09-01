export function updateStats(countsOrRequests) {
  // Prefer server counts when present; fall back to deriving from a page of rows.
  if (
    countsOrRequests &&
    typeof countsOrRequests === "object" &&
    !Array.isArray(countsOrRequests) &&
    ("total" in countsOrRequests || "pending" in countsOrRequests)
  ) {
    $("#statTotal").text(Number(countsOrRequests.total || 0));
    $("#statPending").text(Number(countsOrRequests.pending || 0));
    $("#statDone").text(Number(countsOrRequests.acted || 0));
    return;
  }

  const list = countsOrRequests || [];
  const pending = list.filter((r) => !r.is_approved).length;
  const done = list.filter((r) => r.is_approved).length;

  $("#statTotal").text(list.length);
  $("#statPending").text(pending);
  $("#statDone").text(done);
}
