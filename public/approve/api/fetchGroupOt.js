import { apiUrl } from "../../shared/js/api.js";
import { apiGet } from "../../shared/js/http.js";
import { getDashboardMonth, setDashboardMonth } from "../ui/dashboardPeriod.js";
import { renderGroupOt } from "../ui/groupOt.js";
import { updateStats } from "../ui/stats.js";

export async function fetchGroupOt() {
  try {
    const json = await apiGet(
      apiUrl("/approve/group-ot") + `?month=${encodeURIComponent(getDashboardMonth())}`,
    );
    if (!json?.success) {
      renderGroupOt({ groups: [] });
      return null;
    }
    if (json.month) {
      setDashboardMonth(json.month);
    }
    if (json.counts) {
      updateStats(json.counts);
    }
    renderGroupOt(json);
    return json;
  } catch (error) {
    console.error("Failed to fetch group overtime totals:", error);
    renderGroupOt({ groups: [] });
    throw error;
  }
}
