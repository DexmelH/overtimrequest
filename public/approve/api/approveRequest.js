import { apiUrl } from "../../shared/js/api.js";
import { apiPost } from "../../shared/js/http.js";
import { showToast } from "../../shared/js/toast.js";
import { fetchRequest } from "./fetchRequest.js";

export async function approveOvertimeRequest(requestID, status, remarks = "") {
  const body = new FormData();
  body.append("overtimeID", requestID);
  body.append("status", status);
  body.append("remarks", remarks);

  try {
    const payload = await apiPost(apiUrl("/approveovertime"), body);
    if (payload?.success) {
      await fetchRequest();
      showToast(payload.message || "Request updated successfully.", {
        type: "success",
      });
      return payload;
    }
    showToast(payload?.message || "Could not update request.", { type: "warning" });
    return payload;
  } catch (error) {
    console.error("Error approving overtime request:", error);
    showToast("Failed to process request. Please try again.", { type: "error" });
    throw error;
  }
}
