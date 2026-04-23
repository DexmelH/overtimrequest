import { fetchRequest } from "./api/fetchRequest.js";
import { overtime } from "./services/state.js";
import { populateModal } from "./ui/populateModal.js";

fetchRequest().catch(() => {});
