# HeThongQLSV

Student Management System (QLSV) with PHP backend and a simple web UI.

Website: https://quanlysv.74tt21.software

## Features
- Role-based access: admin, teacher, student
- Class, subject, and user management
- Assignments, submissions, grading
- Announcements and materials
- File uploads (local or GCS)

## Tech Stack
- PHP 8.x, Apache
- MySQL
- Vanilla HTML/CSS/JS

## Project Structure
- api/ : REST API, controllers, core utilities
- public/ : UI pages, JS, CSS, uploads
- database/ : schema and seed data

## Getting Started (Local)
1. Create a MySQL database and import database/schema.sql
2. Update api/config/database.php with your credentials
3. Run with Docker (or Apache/PHP locally)

Example with Docker:
1. Build image: docker build -t qlsv .
2. Run: docker run -p 8080:8080 qlsv
3. Open: http://localhost:8080

## Configuration
- DB config: api/config/database.php
- Uploads: public/uploads/
- API base URL: public/js/common.js (auto-resolves by default)

## License
Private project.
