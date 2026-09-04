# Overtime Portal — Live Demo Script

**Audience:** managers / HR / IT  
**Length:** ~12–15 minutes  
**Pages:** Request → Approve → Admin  
**Base URL:** `/overtime/public/`

How to use this file: read the **Say** lines out loud. Do the **Do** steps on screen. Glance at **Tip** only if something stalls.

---

## Before you start (2 min, off-mic)

| Role | Who to sign in as | Open |
|------|-------------------|------|
| Employee | A regular staff account with OT history | `/request/` |
| Approver | Someone in Group Approvers (or Form PIC) | `/approve/` |
| Admin | Someone in Admin members | `/admin/` |

Use three browser profiles (or Incognito windows) so you can switch roles without logging out repeatedly. Have one pending request ready for the approver, or submit one live in Act 1.

**Optional:** Confirm email worker is running if you want to mention notifications.

---

## Opening (30 sec)

**Say:**  
“This is the Overtime Portal. Staff file requests, approvers decide, and admins manage who can approve and who can administer the app. I’ll walk through each role with live data.”

**Do:** Show the Request page briefly so people see the layout (header, form left, history right).

---

## Act 1 — Employee: Request overtime (~4 min)

### 1.1 Layout

**Say:**  
“Employees land on Overtime Request. On the left is the new-request form. On the right is their personal history — pending, approved, denied, cancelled — with filters.”

**Do:** Point to form → history → filter chips (All / Pending / Approved / Denied / Cancelled).

### 1.2 Theme (optional, 10 sec)

**Say:**  
“There’s a light and dark theme in the header — same on every page.”

**Do:** Toggle theme once, then leave it on whichever looks better on the projector.

### 1.3 File a request

**Say:**  
“To file overtime: pick the date, group, and location. Then add one or more projects and hours. The total updates as you go. Remarks are optional for the approver.”

**Do:**
1. Choose a valid **date** (today or a work day in range).
2. Select **group** → **location**.
3. **Add project**, enter hours (keep it small, e.g. 1–2 hrs).
4. Optional short remark: “Demo request — safe to approve.”
5. Click **Submit Request**.

**Say:**  
“On submit, the request is stored and the assigned approvers are notified. It appears in history as Pending.”

**Do:** Show the new row in history (Pending). Click it to open **Request Details**.

### 1.4 Details & cancel

**Say:**  
“From details they can review what was filed. If it’s still pending, they can cancel — the PIC is notified.”

**Do:** Open details; point at Cancel (do **not** cancel the demo request unless you have another pending one). Close the modal.

### 1.5 Cutoff (mention even if unlocked)

**Say:**  
“After the daily cutoff — currently 3:00 PM — employees can’t submit for themselves. The form locks and they must ask an approver to submit on their behalf. That keeps late filing under manager control.”

**Do:** If the lock banner is visible, point to it. If not, just say the rule.

---

## Act 2 — Approver: Decide (~5 min)

**Do:** Switch to the Approver browser → `/approve/`.

### 2.1 Dashboard

**Say:**  
“Approvers see a summary: total assigned, awaiting their action, and already acted. The list below is live — it refreshes without a full page reload.”

**Do:** Point at the three stat cards, then the **Live** indicator.

### 2.2 Filters

**Say:**  
“Filters help focus: Needs Action for what I still owe a decision; Acted for what I’ve already handled; Auto-approved and Auto-rejected for system outcomes — for example on-behalf submissions.”

**Do:** Click **Needs Action**, then back to **All**.

### 2.3 Approve one request

**Say:**  
“I’ll open the request we just filed — or any pending row.”

**Do:** Click a pending row → details modal.

**Say:**  
“Here they see who requested, when, projects, hours, and remarks. Approve or Reject with a confirmation — no accidental clicks.”

**Do:** Click **Approve** → confirm in the dialog. Show toast / status change. Optionally mention Reject the same way without doing it.

### 2.4 Bulk actions (if several pending)

**Say:**  
“For volume, select multiple pending rows and Approve or Reject in bulk. Reject asks for a shared reason.”

**Do:** If 2+ pending rows exist, check two boxes → show bulk bar → **don’t** submit unless you intend to. Uncheck / cancel if demoing only.

### 2.5 On-behalf (cutoff / manager assist)

**Say:**  
“When an employee misses cutoff — or a manager needs to file for someone — use Submit Member Request. This is only for approvers, and only for employees in groups they approve.”

**Do:** Click **Submit Member Request**.

**Say:**  
“Search the employee, choose their OT group and location, allocate projects and hours, then submit. That request is auto-approved for the chain — you’ll see it under Auto-approved.”

**Do:**
1. Search and pick a known employee in your approval scope.
2. Fill group / location / project / hours (keep short).
3. Submit.
4. Filter **Auto-approved** and show the new row.

**Tip:** If search returns nothing, the employee’s main group isn’t under this approver — pick someone else from your group.

---

## Act 3 — Admin (~3 min)

**Do:** Switch to Admin browser → `/admin/`.

### 3.1 Access

**Say:**  
“Admin is restricted. Non-admins see Access Denied. Admins get three tabs: Group Approvers, Admins, and Activity Logs.”

### 3.2 Group Approvers

**Say:**  
“Here we configure who approves for each group. Changes can be drafted and reviewed before they become official — so we don’t break the approval chain by accident.”

**Do:** Open **Group Approvers**. Show the group picker / draft vs official lists (point without saving unless planned).

### 3.3 Admin members

**Say:**  
“The Admins tab controls who can open this screen. Keep this list short.”

**Do:** Open **Admins** briefly.

### 3.4 Activity logs

**Say:**  
“Logs record who did what — submissions, approvals, config changes — with search and date filters for audit.”

**Do:** Open **Activity Logs**. Run a quick search or show recent rows.

---

## Closing (30 sec)

**Say:**  
“To recap: employees request and track their own OT; after cutoff they go through an approver. Approvers decide one-by-one or in bulk, and can file on behalf when needed. Admins own the approver map, admin access, and the audit trail. Questions?”

**Do:** Leave Approve or Request on screen (whichever looks busiest with real data).

---

## Quick reference — URLs

| Page | Path |
|------|------|
| Request | `/overtime/public/request/` |
| Approve | `/overtime/public/approve/` |
| Admin | `/overtime/public/admin/` |

---

## If something goes wrong

| Problem | What to say / do |
|---------|------------------|
| No pending rows | Submit a new request as Employee (Act 1), then refresh Approve. |
| Cutoff lock mid-demo | Switch to on-behalf (Act 2.5) — that’s the intended path. |
| On-behalf employee not found | “Only employees in groups I approve for.” Pick another name. |
| Admin Access Denied | Wrong account — switch to an admin member. |
| Form validation | Fill all required fields; hours must add up sensibly. |
| Blank projects | Select group first — projects load from that group. |

---

## Timing cheat sheet

| Section | Target |
|---------|--------|
| Opening | 0:30 |
| Act 1 Request | 4:00 |
| Act 2 Approve + on-behalf | 5:00 |
| Act 3 Admin | 3:00 |
| Closing + Q&A | 2:00+ |
| **Total** | **~15 min** |

For a **7-minute** version: skip theme, bulk, and Admin members; do one approve + one on-behalf mention + Admin logs only.
