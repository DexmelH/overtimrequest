import { apiUrl } from "../../shared/js/api.js";
import { apiPost } from "../../shared/js/http.js";
import { showToast } from "../../shared/js/toast.js";
import { fetchRequest } from "./fetchRequest.js";

export async function approveOvertimeRequest(
  requestID,
  status,
  remarks = "",
  { refresh = true, toast = true } = {},
) {
  const body = new FormData();
  body.append("overtimeID", requestID);
  body.append("status", status);
  body.append("remarks", remarks);

  try {
    const payload = await apiPost(apiUrl("/approveovertime"), body);
    if (payload?.success) {
      if (refresh) {
        await fetchRequest();
      }
      if (toast) {
        showToast(payload.message || "Request updated successfully.", {
          type: "success",
        });
      }
      return payload;
    }
    if (toast) {
      showToast(payload?.message || "Could not update request.", {
        type: "warning",
      });
    }
    return payload;
  } catch (error) {
    console.error("Error approving overtime request:", error);
    if (toast) {
      showToast("Failed to process request. Please try again.", {
        type: "error",
      });
    }
    throw error;
  }
}

/**
 * Process many requests sequentially with a single list refresh at the end.
 * @returns {Promise<{ok: number, failed: number}>}
 */
export async function approveOvertimeRequestsBulk(
  requestIds,
  status,
  remarks = "",
  { onProgress } = {},
) {
  let ok = 0;
  let failed = 0;
  const total = requestIds.length;

  for (let i = 0; i < requestIds.length; i++) {
    const id = requestIds[i];
    if (typeof onProgress === "function") {
      onProgress(i + 1, total);
    }
    try {
      const payload = await approveOvertimeRequest(id, status, remarks, {
        refresh: false,
        toast: false,
      });
      if (payload?.success) {
        ok += 1;
      } else {
        failed += 1;
      }
    } catch {
      failed += 1;
    }
  }

  await fetchRequest();
  return { ok, failed };
}
