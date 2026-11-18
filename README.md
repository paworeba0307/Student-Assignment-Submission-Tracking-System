# Student-Assignment-Submission-Tracking-System

## Lecturer module

The lecturer pages are in `lecturer/`: the dashboard, assignment create/edit form,
assignment management, submission roster, and authenticated submission download.
They expect the shared application foundation described in the project brief:

- `includes/auth.php` must start the session and provide `require_login()` and
  `require_role('lecturer')`.
- `config/db.php` must provide a MySQL PDO connection in `$pdo`.
- The database must contain the `users`, `courses`, `enrollments`, `assignments`,
  and `submissions` tables and columns listed in the project brief.
- The web server must be able to write to `uploads/assignments/` and
  `uploads/submissions/`. Assignment attachments are restricted to PDF, DOCX,
  and ZIP files up to 10 MB. Submission downloads are served through an
  authenticated lecturer route rather than exposing their stored paths.

Open `lecturer/dashboard.php` after logging in as a lecturer. Students only see
assignments with `status = 'published'`; unpublishing changes that status back
to `draft`.
