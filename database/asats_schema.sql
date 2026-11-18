CREATE DATABASE IF NOT EXISTS asats_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE asats_db;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('lecturer', 'student') NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_code VARCHAR(30) NOT NULL,
  course_name VARCHAR(150) NOT NULL,
  lecturer_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_courses_course_code (course_code),
  KEY idx_courses_lecturer_id (lecturer_id),
  CONSTRAINT fk_courses_lecturer
    FOREIGN KEY (lecturer_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS enrollments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_enrollments_course_student (course_id, student_id),
  KEY idx_enrollments_student_id (student_id),
  CONSTRAINT fk_enrollments_course
    FOREIGN KEY (course_id) REFERENCES courses (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_enrollments_student
    FOREIGN KEY (student_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assignments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  course_id INT UNSIGNED NOT NULL,
  lecturer_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  attachment_path VARCHAR(255) DEFAULT NULL,
  due_date DATETIME NOT NULL,
  status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_assignments_course_status_due (course_id, status, due_date),
  KEY idx_assignments_lecturer_id (lecturer_id),
  CONSTRAINT fk_assignments_course
    FOREIGN KEY (course_id) REFERENCES courses (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_assignments_lecturer
    FOREIGN KEY (lecturer_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submissions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  comment TEXT DEFAULT NULL,
  submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  grade DECIMAL(5,2) DEFAULT NULL,
  feedback TEXT DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_submissions_assignment_student (assignment_id, student_id),
  KEY idx_submissions_student_id (student_id),
  CONSTRAINT fk_submissions_assignment
    FOREIGN KEY (assignment_id) REFERENCES assignments (id)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_submissions_student
    FOREIGN KEY (student_id) REFERENCES users (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO users (full_name, email, password_hash, role)
VALUES
  ('Demo Lecturer', 'lecturer@example.com', '$2y$12$g0QAJGkSIgWFjreUjGorkOQdIgrkQWh5OSi51xMDNMWoqvYQhZq6C', 'lecturer'),
  ('Alex Student', 'alex@example.com', '$2y$12$g0QAJGkSIgWFjreUjGorkOQdIgrkQWh5OSi51xMDNMWoqvYQhZq6C', 'student'),
  ('Bailey Student', 'bailey@example.com', '$2y$12$g0QAJGkSIgWFjreUjGorkOQdIgrkQWh5OSi51xMDNMWoqvYQhZq6C', 'student'),
  ('Casey Student', 'casey@example.com', '$2y$12$g0QAJGkSIgWFjreUjGorkOQdIgrkQWh5OSi51xMDNMWoqvYQhZq6C', 'student')
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  password_hash = VALUES(password_hash),
  role = VALUES(role);

INSERT INTO courses (course_code, course_name, lecturer_id)
SELECT 'CS101', 'Introduction to Computing', id
FROM users
WHERE email = 'lecturer@example.com'
ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), lecturer_id = VALUES(lecturer_id);

INSERT INTO courses (course_code, course_name, lecturer_id)
SELECT 'DB201', 'Database Systems', id
FROM users
WHERE email = 'lecturer@example.com'
ON DUPLICATE KEY UPDATE course_name = VALUES(course_name), lecturer_id = VALUES(lecturer_id);

INSERT INTO enrollments (course_id, student_id)
SELECT c.id, u.id
FROM courses c
CROSS JOIN users u
WHERE c.course_code = 'CS101'
  AND u.email IN ('alex@example.com', 'bailey@example.com', 'casey@example.com')
  AND NOT EXISTS (
    SELECT 1
    FROM enrollments e
    WHERE e.course_id = c.id AND e.student_id = u.id
  );

INSERT INTO enrollments (course_id, student_id)
SELECT c.id, u.id
FROM courses c
CROSS JOIN users u
WHERE c.course_code = 'DB201'
  AND u.email IN ('alex@example.com', 'bailey@example.com')
  AND NOT EXISTS (
    SELECT 1
    FROM enrollments e
    WHERE e.course_id = c.id AND e.student_id = u.id
  );

INSERT INTO assignments (course_id, lecturer_id, title, description, due_date, status)
SELECT c.id, u.id, 'Computing Fundamentals', 'Explain the key components of a computer system.', '2030-10-15 23:59:00', 'published'
FROM courses c
JOIN users u ON u.email = 'lecturer@example.com'
WHERE c.course_code = 'CS101'
  AND NOT EXISTS (
    SELECT 1 FROM assignments a
    WHERE a.course_id = c.id AND a.title = 'Computing Fundamentals'
  );

INSERT INTO assignments (course_id, lecturer_id, title, description, due_date, status)
SELECT c.id, u.id, 'Introduction to Data Models', 'Compare relational and document data models.', '2030-11-01 23:59:00', 'published'
FROM courses c
JOIN users u ON u.email = 'lecturer@example.com'
WHERE c.course_code = 'DB201'
  AND NOT EXISTS (
    SELECT 1 FROM assignments a
    WHERE a.course_id = c.id AND a.title = 'Introduction to Data Models'
  );

INSERT INTO assignments (course_id, lecturer_id, title, description, due_date, status)
SELECT c.id, u.id, 'Normalization Practice', 'Normalize the supplied sample relations to third normal form.', '2030-11-15 23:59:00', 'draft'
FROM courses c
JOIN users u ON u.email = 'lecturer@example.com'
WHERE c.course_code = 'DB201'
  AND NOT EXISTS (
    SELECT 1 FROM assignments a
    WHERE a.course_id = c.id AND a.title = 'Normalization Practice'
  );
