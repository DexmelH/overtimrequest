import { showToast } from "../components/toast.js";
import { fetchRequest } from "./fetchRequest.js";
import { renderTable } from "../ui/renderOvertime.js";

export async function approveOvertimeRequest(requestID, status) {
  const newFormData = new FormData();
  newFormData.append("overtimeID", requestID);
  newFormData.append("status", status);

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
      showToast(`Overtime request processed successfully.`, {
        type: "success",
      });
    } else {
      throw new Error(
        "Failed to approve overtime request: " +
          (payload.message || "Unknown error"),
      );
    }
  } catch (error) {
    renderTable();
    console.error("Error approving overtime request:", error);
    showToast(error, {
      type: "error",
    });
    throw error;
  }
}
