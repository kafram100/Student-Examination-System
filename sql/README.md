# SQL Scripts

This folder contains the database schema used by the system.

Files:
- `schema.sql`: Base schema (users, exams, questions, attempts, answers, etc.)
- `students_table.sql`: Student table used by the student portal
- `add_program_column.sql`: Adds the `program` column to students (if needed)

To initialize the database, open:
`http://localhost/student_exam_system/install.php`

Additional schema updates are handled by:
`update_db_v2.php` through `update_db_v5.php`
