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
  const ids = Array.isArray(requestIds) ? requestIds : [];
  const total = ids.length;
  if (typeof onProgress === "function") {
    onProgress(total, total);
  }

  const body = new FormData();
  body.append("status", String(status));
  body.append("remarks", remarks);
  ids.forEach((id) => body.append("overtimeIDs[]", String(id)));

  try {
    const payload = await apiPost(apiUrl("/approve/bulk"), body, { timeout: 60000 });
    await fetchRequest();
    return {
      ok: Number(payload?.ok ?? 0),
      failed: Number(payload?.failed ?? (payload?.success ? 0 : ids.length)),
    };
  } catch (error) {
    console.error("Error bulk-approving overtime requests:", error);
    await fetchRequest().catch(() => {});
    throw error;
  }
}
