import { showToast } from "../../request/components/toast";
import { fetchRequest } from "./fetchRequest.js";
import { renderOvertime } from "../ui/renderOvertime.js";

export async function approveOvertimeRequest(formData) {
  const newFormData = new FormData();
  newFormData.append("overtime_id", formData.overtime_id);
  newFormData.append("remarks", formData.remarks);
  newFormData.append("status", formData.status);

  try {
    const response = await fetch("../api/approveovertime", {
      method: "POST",
      credentials: "same-origin",
      body: newFormData,
    });
    if (!response.ok) {
      throw new Error("Network response was not ok: " + response.status);
    }
    const payload = await response.json();
    if (payload && payload.success) {
      await fetchRequest();
      showToast;
      ("Overtime request processed successfully.", { type: "success" });
    } else {
      throw new Error(
        "Failed to approve overtime request: " +
          (payload.message || "Unknown error"),
      );
    }
  } catch (error) {
    renderOvertime();
    console.error("Error approving overtime request:", error);
    throw error;
  }
}
