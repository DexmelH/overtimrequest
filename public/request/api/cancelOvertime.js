import { apiUrl } from "../../shared/js/api.js";
import { apiPost } from "../../shared/js/http.js";
import { showToast } from "../../shared/js/toast.js";
import { fetchHistory } from "./fetchHistory.js";
import { closeModal } from "../components/modal.js";

export async function cancelOvertimeRequest(requestId) {
  const body = new FormData();
  body.append("overtimeID", requestId);

  try {
    const payload = await apiPost(apiUrl("/cancelovertime"), body);
    if (payload?.success) {
      await fetchHistory();
      closeModal();
      showToast(payload.message || "Request cancelled.", { type: "success" });
      return payload;
    }
    showToast(payload?.message || "Could not cancel request.", { type: "warning" });
    return payload;
  } catch (error) {
    console.error("Failed to cancel request:", error);
    showToast("Failed to cancel request. Please try again.", { type: "error" });
    throw error;
  }
}
