export function updateStats(requests) {
  const list = requests || [];
  const pending = list.filter((r) => !r.is_approved).length;
  const done = list.filter((r) => r.is_approved).length;

  $("#statTotal").text(list.length);
  $("#statPending").text(pending);
  $("#statDone").text(done);
}
