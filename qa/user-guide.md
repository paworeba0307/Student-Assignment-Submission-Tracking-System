# ASATS User Guide
**Student Assignment Submission and Tracking System**  
Version 1.0 · XAMPP Local Hosting

---

## What is ASATS?

ASATS is a web application that replaces email chains and paper handouts. Lecturers publish assignments online, students upload their work before the deadline, and everyone can see at a glance what has been submitted and what is still outstanding — all in one place.

**Access the system:** `http://<server-IP>/Student-Assignment-Submission-Tracking-System/`

---

## Getting Started — All Users

### 1. Register an account
1. Open the ASATS home page and click **Get started** (or **Register** in the top-right corner).
2. Fill in your **Full name**, **Email address**, and a **Password** (at least 8 characters).
3. Under **I am a**, choose **Student** or **Lecturer**.
4. Click **Create account**. You will be taken straight to your workspace.

### 2. Log in
1. Click **Log in** in the top-right corner.
2. Enter your registered email address and password.
3. Click **Log in**. ASATS redirects you to the correct dashboard for your role.

### 3. Log out
Click **Log out** in the top-right corner. Your session ends immediately and you are returned to the home page.

---

## For Students

### Your workspace — My Submissions
After logging in you land on **My Submissions**. The page has two parts.

#### Summary cards
Four cards at the top give you an instant count of your assignments:

| Card | Meaning |
|------|---------|
| **Total assignments** | Every assignment visible to you |
| **Submitted** | Assignments you have uploaded a file for |
| **Pending** | Assignments not yet submitted where the deadline is still in the future |
| **Overdue** | Assignments not yet submitted where the deadline has passed |

#### Assignment table
The table lists every assignment with the following columns:

- **Assignment** — title and course/type label
- **Status** — colour-coded badge (see below)
- **Due date** — exact date and time
- **Time left** — live countdown; turns red when fewer than 24 hours remain
- **Action** — a link relevant to the current status

#### Status badge colours
| Badge | Colour | Meaning |
|-------|--------|---------|
| Submitted | 🟢 Green | Your file has been received |
| Pending | 🟠 Orange | Deadline is coming — you still need to submit |
| Overdue | 🔴 Red | Deadline has passed and no file was submitted |

#### Filtering and searching
- Click **All**, **Submitted**, **Pending**, or **Overdue** to show only that group.
- Type in the search box to find an assignment by name or description. Results update as you type.

#### Submitting your work
1. Find the assignment with a **Pending** status.
2. Click **Submit work**.
3. Select your file and confirm the upload.
4. The status badge changes to **Submitted** and the **View file** link becomes available.

> **Important:** Submit before the deadline. Once the due date passes, the status changes to **Overdue** and late submissions may not be accepted by your lecturer.

---

## For Lecturers

### Your workspace — Submission Tracking
After logging in you land on **Submission Tracking**. It shows a summary strip and one table per assignment.

#### Summary strip
| Card | Meaning |
|------|---------|
| **Assignments** | Total number of assignments you have published |
| **Submissions received** | Total files submitted across all assignments |
| **Still pending** | Student–assignment pairs with no file yet |
| **Distinct students** | Number of unique students across all assignments |

#### Per-assignment tables
Each assignment has its own table showing every enrolled student. Columns:

- **Student** — full name
- **Email** — student's registered email
- **Status** — Submitted (green), Pending (orange), or Not submitted (red — past deadline only)
- **Submitted at** — exact date and time the file was received; "—" if none
- **File** — clickable file name if a file exists; "—" if none

A header bar above each table shows:
- Assignment title and due date
- Whether the deadline is **Open** (orange) or **Deadline passed** (red)
- A quick count of **X / Y students submitted**

#### Publishing and managing assignments
1. Open your assignment workspace (created by your team's backend developer).
2. Click **New assignment** and fill in the title, description, due date, and optional attachment.
3. Click **Publish** to make the assignment visible to students, or **Save as draft** to review it first.
4. To edit or delete an assignment, open it from your assignment list and use the Edit or Delete buttons.

---

## Troubleshooting

| Problem | What to check |
|---------|---------------|
| Page shows demo data instead of real assignments | The database is unavailable. Ask your system administrator to confirm MySQL is running and `database.sql` has been imported. |
| "Deadline passed" immediately | Check that the server clock is correct. Due dates are compared against the server time. |
| File upload fails | Check that the file type is allowed and the file size is within the permitted limit. |
| Logged out unexpectedly | PHP sessions expire after a period of inactivity. Log in again. |
| Cannot access `lecturer/tracking.php` | Only accounts registered as **Lecturer** can view that page. Log in with a lecturer account. |

---

## Status Reference

| Status | Who sees it | Condition |
|--------|-------------|-----------|
| Submitted | Student & Lecturer | A file is linked to the student–assignment pair |
| Pending | Student & Lecturer | No file submitted; deadline is in the future |
| Overdue | Student | No file submitted; deadline has passed |
| Not submitted | Lecturer | No file submitted; deadline has passed |

---

*For technical issues or bug reports, see the QA Test Checklist and log a GitHub Issue using the bug report template provided there.*
