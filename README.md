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
# Student-Assignment-Submission-Tracking-System 

ASATS is a PHP 8+ student assignment submission and tracking system for XAMPP.

## Local setup

1. Start Apache and MySQL from the XAMPP Control Panel.
2. Import `database/asats_schema.sql` in phpMyAdmin. It creates `asats_db` and sample lecturer, student, course, enrollment, and assignment records.
3. Copy the local database config template:

   ```powershell
   Copy-Item config\db.example.php config\db.php
   ```

4. Update `config\db.php` with your local MySQL credentials. This file is ignored by Git; only the credential-free example is shared.
5. Open `http://localhost/<project-folder>/auth/login.php` or `http://localhost/<project-folder>/auth/register.php`.

The sample accounts all use `AsatsDemo123!`:

| Role | Email |
| --- | --- |
| Lecturer | `lecturer@example.com` |
| Student | `alex@example.com` |
| Student | `bailey@example.com` |
| Student | `casey@example.com` |

Public registration creates student accounts only. Create lecturer accounts through trusted administrative provisioning; do not make lecturer access selectable on the public registration form. The dashboard pages are supplied by the lecturer and student modules.

Uploaded assignments and submissions are stored locally under `uploads/` and are not tracked by Git.
