import { apiUrl } from "../../shared/js/api.js";
import { apiPost } from "../../shared/js/http.js";

/**
 * Batch lookup of Daily Report OT minutes for employee + year-month pairs.
 *
 * @param {Array<{employee_id: number|string, year_month: string}>} items
 * @returns {Promise<Array<{employee_id: number, year_month: string, minutes: number, label: string}>>}
 */
export async function fetchEmployeeMonthOt(items) {
  const list = Array.isArray(items) ? items : [];
  if (!list.length) return [];

  const body = new FormData();
  body.append("items", JSON.stringify(list));

  const payload = await apiPost(apiUrl("/approve/employee-month-ot"), body);
  if (!payload?.success) {
    throw new Error(payload?.message || "Could not load month OT totals.");
  }
  return Array.isArray(payload.items) ? payload.items : [];
}
