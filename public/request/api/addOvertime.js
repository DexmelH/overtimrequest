import { renderHistory } from "../ui/renderHistory.js";
import { fetchHistory } from "./fetchHistory.js";
import { showToast } from "../components/toast.js";
import { openModal } from "../components/modal.js";

export async function addOvertimeRequest(formData) {
  const newFormData = new FormData();
  newFormData.append("date", formData.date);
  newFormData.append("group", formData.group);
  newFormData.append("location", formData.location);
  newFormData.append("project", formData.project);
  newFormData.append("item", formData.item);
  newFormData.append("jobdesc", formData.jobdesc);
  newFormData.append("work", formData.work);
  newFormData.append("remarks", formData.remarks);
  newFormData.append("hours", formData.hours);

  try {
    const response = await fetch("../api/addovertime", {
      method: "POST",
      credentials: "same-origin",
      body: newFormData,
    });
    if (!response.ok)
      throw new Error("Network response was not ok" + response.status);
    const payload = await response.json();
    if (payload && payload.data) {
      await fetchHistory();
      openModal(parseInt(payload.data));
      showToast("Overtime request submitted successfully.", {
        type: "success",
      });
    } else {
      console.warn("No requestID in payload, not opening modal.");
      showToast("Request submitted but no request ID returned.", {
        type: "warning",
      });
    }
  } catch (error) {
    renderHistory();
    console.log("Failed to add overtime request:", error);
    showToast("Failed to submit request. Please try again.", { type: "error" });
  }
}
