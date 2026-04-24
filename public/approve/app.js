import { fetchRequest } from "./api/fetchRequest.js";
import { overtime } from "./services/state.js";
import { populateModal } from "./ui/populateModal.js";
import { showToast } from "./components/toast.js";
import { approveOvertimeRequest } from "./api/approveRequest.js";
import { modal } from "./components/modal.js";

fetchRequest().catch(() => {});

$("#btnApproveRequest").on("click", function () {
  const requestId = $("#rd-requestID").text();
  approveOvertimeRequest(requestId, 1);
  modal.hide();
});

$("#btnRejectRequest").on("click", function () {
  const requestId = $("#rd-requestID").text();
  approveOvertimeRequest(requestId, 0);
  modal.hide();
});
