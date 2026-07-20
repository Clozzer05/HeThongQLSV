# Student Management System (HeThongQLSV)

A web-based Student Management System built with PHP and MySQL, providing role-based access control, RESTful APIs, and a responsive web interface. The project is containerized with Docker and automatically deployed to AWS EC2 using GitHub Actions.

---

## Live Demo

Website: https://quanlysv.74tt21.software

---

## Demo Accounts

| Role | Username | Password |
|------|----------|----------|
| Admin | admin | 123456 |
| Teacher | gv_anh | 123456 |
| Student | sv01 | 123456 |

> These demo accounts are provided for evaluation purposes.

---
## Screenshot
<img width="1658" height="1025" alt="image" src="https://github.com/user-attachments/assets/5122ab66-7b0f-40cd-bae2-2dd83d843a99" />
<img width="1667" height="1029" alt="image" src="https://github.com/user-attachments/assets/752d78b5-9395-480a-80d1-b7a1920670de" />
<img width="1668" height="1016" alt="image" src="https://github.com/user-attachments/assets/852226a8-f279-4e1e-bafc-787a3e5c26f1" />

##  Features

- Role-based authentication (Admin, Teacher, Student)
- Student management
- Teacher management
- Class management
- Subject management
- Assignment creation and submission
- Assignment grading
- Announcements
- Document management
- File upload
- RESTful API for frontend-backend communication

---

## Tech Stack

### Backend
- PHP 8.x
- Apache
- MySQL
- REST API

### Frontend
- HTML5
- CSS3
- JavaScript

### DevOps & Cloud
- Docker
- Docker Compose
- GitHub Actions (CI/CD)
- AWS EC2
- Linux (Ubuntu)

---

## Deployment

The application is deployed on an Ubuntu-based AWS EC2 instance.

Deployment workflow:

- Push code to GitHub
- GitHub Actions automatically builds the project
- The workflow deploys the latest version to AWS EC2
- Docker Compose restarts the application

---

## Project Structure

```
HeThongQLSV/
│
├── api/                # REST API, controllers, models, core
├── public/             # Web interface
│   ├── css/
│   ├── js/
│   ├── uploads/
│   └── images/
│
├── database/           # Database schema and sample data
├── Dockerfile
├── docker-compose.yml
└── README.md
```

---

## Run Locally

### 1. Clone the repository

```bash
git clone https://github.com/Clozzer05/HeThongQLSV.git
cd HeThongQLSV
```

### 2. Configure the database

Create a MySQL database and import:

```
database/schema.sql
```

Update:

```
api/config/database.php
```

---

### 3. Run with Docker

Build the Docker image:

```bash
docker build -t qlsv .
```

Run the container:

```bash
docker run -p 8080:8080 qlsv
```

Open:

```
http://localhost:8080
```

---

## Configuration

Database configuration:

```
api/config/database.php
```

Uploads directory:

```
public/uploads/
```

---

## REST API

Example endpoints:

```
POST   /api/login
GET    /api/students
POST   /api/students
PUT    /api/students/{id}
DELETE /api/students/{id}
```
