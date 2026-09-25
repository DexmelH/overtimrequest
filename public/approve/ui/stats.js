function isResubmitted(request) {
  return request?.status_code === "resubmitted";
}

function isApproved(request) {
  return (
    request?.status_code === "approved" ||
    request?.status_code === "auto_approved" ||
    String(request?.status ?? "") === "1"
  );
}

function isRejected(request) {
  return (
    !isResubmitted(request) &&
    (request?.status_code === "rejected" ||
      request?.status_code === "auto_rejected" ||
      String(request?.status ?? "") === "0")
  );
}

export function updateStats(countsOrRequests) {
  // Prefer server counts when present; fall back to deriving from a page of rows.
  if (
    countsOrRequests &&
    typeof countsOrRequests === "object" &&
    !Array.isArray(countsOrRequests) &&
    ("total" in countsOrRequests ||
      "approved" in countsOrRequests ||
      "rejected" in countsOrRequests)
  ) {
    $("#statTotal").text(Number(countsOrRequests.total || 0));
    $("#statApproved").text(Number(countsOrRequests.approved || 0));
    $("#statRejected").text(Number(countsOrRequests.rejected || 0));
    return;
  }

  const list = (countsOrRequests || []).filter((request) => !isResubmitted(request));
  $("#statTotal").text(list.length);
  $("#statApproved").text(list.filter(isApproved).length);
  $("#statRejected").text(list.filter(isRejected).length);
}
