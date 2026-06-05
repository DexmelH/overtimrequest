import { apiUrl } from "../../shared/js/api.js";
import { apiPost } from "../../shared/js/http.js";
import { showToast } from "../../shared/js/toast.js";
import { fetchHistory } from "./fetchHistory.js";
import { openModal } from "../components/modal.js";

export async function addOvertimeRequest(formData) {
  const body = new FormData();
  body.append("date", formData.date);
  body.append("group", formData.group);
  body.append("location", formData.location);
  body.append("project", formData.project);
  body.append("item", formData.item);
  body.append("jobdesc", formData.jobdesc);
  body.append("work", formData.work);
  body.append("remarks", formData.remarks);
  body.append("hours", formData.hours);

  try {
    const payload = await apiPost(apiUrl("/addovertime"), body);
    if (payload?.success) {
      await fetchHistory();
      if (payload.id) {
        openModal(payload.id);
      }
      showToast("Overtime request submitted successfully.", { type: "success" });
      return payload;
    }
    showToast(payload?.message || "Failed to submit request.", { type: "warning" });
    return payload;
  } catch (error) {
    console.error("Failed to add overtime request:", error);
    showToast("Failed to submit request. Please try again.", { type: "error" });
    throw error;
  }
}
