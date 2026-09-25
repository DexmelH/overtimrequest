function pad(value) {
  return String(value).padStart(2, "0");
}

export function currentMonthValue() {
  const now = new Date();
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}`;
}

export function formatMonthLabel(monthValue) {
  const [year, month] = String(monthValue || "").split("-");
  const parsed = new Date(Number(year), Number(month) - 1, 1);
  if (Number.isNaN(parsed.getTime())) return monthValue || "";
  return parsed.toLocaleDateString(undefined, { month: "short", year: "numeric" });
}

function shiftMonth(monthValue, delta) {
  const [year, month] = String(monthValue || currentMonthValue()).split("-");
  const date = new Date(Number(year), Number(month) - 1 + delta, 1);
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}`;
}

let selectedMonth = currentMonthValue();

export function getDashboardMonth() {
  return selectedMonth;
}

function syncControls() {
  const current = currentMonthValue();
  if (selectedMonth > current) {
    selectedMonth = current;
  }
  $("#dashboardMonth").text(formatMonthLabel(selectedMonth));
  $("#dashboardMonthInput").val(selectedMonth).attr("max", current);
  $("#dashboardMonthNext").prop("disabled", selectedMonth >= current);
}

export function setDashboardMonth(monthValue) {
  const next = /^\d{4}-\d{2}$/.test(monthValue || "")
    ? monthValue
    : currentMonthValue();
  const current = currentMonthValue();
  selectedMonth = next > current ? current : next;
  syncControls();
  return selectedMonth;
}

export function initDashboardPeriod(onChange) {
  syncControls();

  $("#dashboardMonthPrev").on("click", function () {
    const month = setDashboardMonth(shiftMonth(selectedMonth, -1));
    onChange?.(month);
  });

  $("#dashboardMonthNext").on("click", function () {
    if (selectedMonth >= currentMonthValue()) return;
    const month = setDashboardMonth(shiftMonth(selectedMonth, 1));
    onChange?.(month);
  });

  $("#dashboardMonthInput").on("change", function () {
    const month = setDashboardMonth($(this).val());
    onChange?.(month);
  });
}
