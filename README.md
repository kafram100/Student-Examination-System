# Student Online Examination System

A web-based examination system built with PHP and MySQL for creating, taking, and grading exams.

## Features
- **Lecturer**: Register/Login, create exams, add questions (MCQ, theory, file upload), publish exams, view results, manual grading.
- **Student**: Login with exam code & index number, take timed exams, autosave answers, resume ongoing attempts, view results (when released).
- **Resilience**: Autosave + resume to protect against network drops.
- **Security**: CSRF protection, secure sessions, and basic security headers.

## Requirements
- PHP 7.4+ (8.x recommended)
- MySQL 5.7+ / MariaDB 10.3+
- XAMPP/WAMP/LAMP (or any PHP-capable web server)

## Setup
1. **Configure database**:
   - Open `db.php` and update `$host`, `$db`, `$user`, `$pass` if needed.
2. **Install tables**:
   - Visit `http://localhost/student_exam_system/install.php`.
3. **Create lecturer account**:
   - Go to `http://localhost/student_exam_system/register.php`.
4. **Students join exam**:
   - Share `http://localhost/student_exam_system/index.php` with students.
   - Students will need the **Exam Code** from your dashboard and their Index Number.

## Demo Seed (Optional)
- `seed_user.php` creates an admin account:
  - Username: `admin`
  - Password: `admin123`
- `seed_exam.php` creates a demo exam (draft).

## Autosave + Resume
While a student is taking an exam, answers are autosaved. If the network drops or the browser reloads, the student can continue from where they stopped.

## File Storage
- Uploaded files are stored in `uploads/`.
- See `uploads/README.md` for details.

## Project Structure
- `auth.php`: Session, CSRF, and security helpers.
- `db.php`: Database connection (auto-creates DB if missing).
- `install.php`: Schema installer.
- `dashboard.php`: Lecturer main page.
- `create_exam.php`: Create new exam.
- `view_exam.php`: Manage exam and questions.
- `add_question.php`: Add questions to exam.
- `index.php`: Student entry.
- `take_exam.php`: Student exam interface.
- `submit_exam.php`: Submission + grading logic.
- `student_result.php`: Student result view.
- `exam_stats.php`: Lecturer stats view.

## Database Scripts
- Base schema: `sql/schema.sql`
- Student table: `sql/students_table.sql`
- Other updates: see `update_db_v*.php`
