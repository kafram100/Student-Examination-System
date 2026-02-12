# Student Online Examination System

A web-based examination system built with PHP and MySQL.

## Features
- **Lecturer**: Register/Login, Create Exams (Title, Duration), Add Questions (Multiple Choice), Publish Exams, View Results.
- **Student**: Login with Exam Code & Index Number, Take Timed Exam, Auto-grading, View Result (if released).
- **Security**: Timer enforced on backend, sessions for auth.

## Setup Instructions

1.  **Database Configuration**:
    - Open `db.php` and update the database credentials (`$user`, `$pass`) if your MySQL configuration is different from the default (root, empty password).

2.  **Installation**:
    - Move this entire folder to your web server's root directory (e.g., `htdocs` in XAMPP).
    - Open your browser and navigate to: `http://localhost/student_exam_system/install.php`
    - This will create the database and necessary tables.

3.  **Usage**:
    - **Lecturer**: Go to `http://localhost/student_exam_system/register.php` to create an account.
    - **Student**: Provide students with the link `http://localhost/student_exam_system/index.php`. They will need the **Exam Code** (visible in your dashboard) and their Index Number.

## File Structure
- `auth.php`: Session helpers.
- `db.php`: Database connection.
- `install.php`: Setup script.
- `dashboard.php`: Lecturer main page.
- `create_exam.php`: Create new exam.
- `view_exam.php`: Manage exam and questions.
- `add_question.php`: Add questions to exam.
- `index.php`: Student login.
- `take_exam.php`: Student exam interface.
- `submit_exam.php`: Grading logic.
- `student_result.php`: Student result view.
- `exam_stats.php`: Lecturer stats view.
