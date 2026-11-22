# ASATS QA & Test Checklist

Test on the leader's local network at `http://<leader-IP>/Student-Assignment-Submission-Tracking-System/`  
Complete every checkbox. Record any failure as a GitHub Issue using the bug report template at the bottom of this file.

---

## 1. Shared UI — Both Roles

### PC (Chrome / Edge at 1366 px+)
- [ ] Logo and ASATS brand mark visible in the navbar on every page.
- [ ] Unauthenticated visitors see **Log in** and **Register** links; no Tracking or Dashboard link.
- [ ] Authenticated **student** sees **My submissions** link; no lecturer link.
- [ ] Authenticated **lecturer** sees **Submission tracking** link; no student My submissions link.
- [ ] Active nav link is highlighted in blue.
- [ ] Log-out link ends the session and redirects to the home page.
- [ ] Footer renders on every page with Home, tracking link, and User guide links.

### Phone (Chrome at 360 px width, over LAN)
- [ ] Hamburger toggle button appears; desktop nav links are hidden.
- [ ] Tapping the hamburger expands the mobile menu.
- [ ] Tapping the hamburger again collapses the menu.
- [ ] All nav links in the mobile menu are tappable without zooming.
- [ ] Footer stacks vertically and all footer links are tappable.

---

## 2. Home / Landing Page (`index.php`)

### PC
- [ ] Unauthenticated visit shows the hero section with **Get started** and **View tracking** buttons.
- [ ] **Get started** opens `auth/register.php`.
- [ ] **View tracking** opens `student/my_submissions.php`.
- [ ] Logging in as a **student** redirects automatically to `student/my_submissions.php`.
- [ ] Logging in as a **lecturer** redirects automatically to `lecturer/tracking.php`.
- [ ] Re-visiting `index.php` while logged in redirects to the correct dashboard (no flash of the landing page).

### Phone (360 px, over LAN)
- [ ] Hero heading and copy are readable without horizontal scroll.
- [ ] Hero action buttons stack vertically and span full width.
- [ ] Dashboard illustration is hidden on small screens (not broken, just hidden).

---

## 3. Authentication Pages

### Registration (`auth/register.php`)
- [ ] PC: form renders with Full name, Email, Password, Role fields.
- [ ] Empty submission shows the validation error banner.
- [ ] Password shorter than 8 characters shows the validation error.
- [ ] Valid registration creates the session and redirects to the correct dashboard.
- [ ] Phone: form is usable without horizontal scroll.

### Login (`auth/login.php`)
- [ ] Empty submission shows the validation error.
- [ ] Valid login creates the session and redirects correctly by role.
- [ ] Phone: form renders correctly.

### Logout (`auth/logout.php`)
- [ ] Session is destroyed and the user lands on `index.php`.
- [ ] Back button after logout does not restore the authenticated state.

---

## 4. Student Tracking Page (`student/my_submissions.php`)

### PC
- [ ] Summary cards show correct counts: Total, Submitted, Pending, Overdue.
- [ ] Submitted badge is **green**.
- [ ] Pending badge is **orange**.
- [ ] Overdue badge is **red**.
- [ ] Countdown column shows days/hours remaining for open assignments; shows "Deadline passed" for overdue ones.
- [ ] Countdown marks due within 24 hours in **red bold**.
- [ ] **All** filter tab shows every row.
- [ ] **Submitted** filter shows only submitted rows.
- [ ] **Pending** filter shows only pending rows.
- [ ] **Overdue** filter shows only overdue rows.
- [ ] Typing in the search box filters by assignment name in real time.
- [ ] Clearing the search box restores all rows for the current filter.
- [ ] "Submit work" action link appears for pending assignments.
- [ ] "View file" action link appears for submitted assignments.
- [ ] "Details" action link appears for overdue assignments.

### Phone (360 px, over LAN)
- [ ] Summary cards display in a 2-column grid.
- [ ] Tracking table scrolls horizontally without breaking the page layout.
- [ ] Filter buttons are scrollable horizontally.
- [ ] Search input spans the full toolbar width.
- [ ] All action links are tappable.

---

## 5. Lecturer Tracking Page (`lecturer/tracking.php`)

### PC
- [ ] Page is accessible only when logged in as a **lecturer**; students are redirected to `index.php`.
- [ ] Summary strip shows Assignments count, Submissions received, Still pending, Distinct students.
- [ ] One table per assignment is rendered.
- [ ] Each table header shows the assignment title, due date, and open/past badge.
- [ ] Each table header shows the submitted count in `X/Y students submitted` format.
- [ ] Student rows show name, email, status badge, submitted-at timestamp, and file name.
- [ ] Submitted students show a green **Submitted** badge.
- [ ] Students who missed a past deadline show a red **Not submitted** badge.
- [ ] Students with time remaining and no file show an orange **Pending** badge.
- [ ] File name is a link when a file exists; shows "—" when no file.
- [ ] Demo data renders correctly when no database connection is available.

### Phone (360 px, over LAN)
- [ ] Summary cards display in a 2-column grid.
- [ ] Each assignment table scrolls horizontally without breaking layout.

---

## 6. Edge Cases

- [ ] **Duplicate submission:** submit the same assignment twice as a student; confirm only one record appears (database `UNIQUE KEY` on `assignment_id + student_id`).
- [ ] **Wrong file type:** attempt to submit a file type not permitted; confirm the upload is rejected with a clear error message (validate when file upload is implemented).
- [ ] **Late submission:** set a due date in the past and submit a file; confirm the row shows "Overdue" in the student view and "Not submitted" in the lecturer view.
- [ ] **Student opening lecturer URL:** log in as a student and navigate directly to `lecturer/tracking.php`; confirm redirection to `index.php` with no lecturer data exposed.
- [ ] **Lecturer opening student URL:** log in as a lecturer and navigate to `student/my_submissions.php`; confirm the page loads (no hard block) but shows only that user's (empty) submissions.
- [ ] **Unauthenticated access to tracking:** visit `student/my_submissions.php` without logging in; confirm demo data is shown and no real user data leaks.
- [ ] **Database unavailable:** stop MySQL, reload tracking pages; confirm demo/fallback data renders without a PHP fatal error.
- [ ] **Very long assignment title:** confirm the table cell wraps or truncates cleanly without breaking the layout.
- [ ] **Timezone/date formatting:** confirm due dates display in `dd Mon YYYY, HH:mm` format consistently across pages.

---

## 7. Cross-Browser & Device Matrix

| Browser | Viewport | Home | Login | Register | Student tracking | Lecturer tracking |
|---------|----------|------|-------|----------|-----------------|-------------------|
| Chrome  | 1366 px  | [ ]  | [ ]   | [ ]      | [ ]             | [ ]               |
| Edge    | 1366 px  | [ ]  | [ ]   | [ ]      | [ ]             | [ ]               |
| Chrome  | 768 px   | [ ]  | [ ]   | [ ]      | [ ]             | [ ]               |
| Edge    | 768 px   | [ ]  | [ ]   | [ ]      | [ ]             | [ ]               |
| Chrome  | 360 px   | [ ]  | [ ]   | [ ]      | [ ]             | [ ]               |
| Edge    | 360 px   | [ ]  | [ ]   | [ ]      | [ ]             | [ ]               |

---

## 8. Keyboard & Accessibility

- [ ] Every interactive element (links, buttons, inputs) is reachable using Tab.
- [ ] Enter/Space activates buttons.
- [ ] Focus outlines are visible on all interactive elements.
- [ ] All images and decorative icons have `aria-hidden="true"` or empty `alt` attributes.
- [ ] Page has a logical heading hierarchy (one `<h1>` per page).

---

## GitHub Issue Bug Report Template

Use this template when logging a confirmed defect in GitHub Issues.

```
**Title:** [Page / Feature] — short description of the problem

**Summary**
One sentence describing what goes wrong.

**Steps to reproduce**
1. 
2. 
3. 

**Expected result**
What should happen.

**Actual result**
What actually happens.

**Environment**
- Browser & version:
- Viewport / device:
- URL tested:
- Logged in as (student / lecturer / guest):

**Severity**
[ ] Critical — blocks core functionality
[ ] High — major feature broken
[ ] Medium — feature works but behaves incorrectly
[ ] Low — cosmetic or minor UX issue

**Screenshot / recording**
Attach here.
```
