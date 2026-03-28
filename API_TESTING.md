# API Testing Quick Guide

## 1) Import database

- Import `database/schema.sql` into MySQL (XAMPP).
- Ensure DB config matches `api/config/database.php`.

## 2) Start app

- Open `http://localhost/QuanLySinhVien/`.
- API base should be: `http://localhost/QuanLySinhVien/api`.

## 3) Test quickly with cURL

Health check:

```bash
curl -i http://localhost/QuanLySinhVien/api/health
```

Login and store session cookie:

```bash
curl -c /tmp/qlsv.cookie \
  -H "Content-Type: application/json" \
  -X POST http://localhost/QuanLySinhVien/api/auth/login \
  -d '{"ten_dang_nhap":"admin","mat_khau":"123456"}'
```

Current user from session:

```bash
curl -b /tmp/qlsv.cookie http://localhost/QuanLySinhVien/api/auth/me
```

Role-protected endpoint example:

```bash
curl -b /tmp/qlsv.cookie http://localhost/QuanLySinhVien/api/admin/stats
```

## 4) Raw JSON body format

For endpoints that use JSON, choose:

- Body -> `raw`
- Type -> `JSON`
- Header -> `Content-Type: application/json`

Example login body:

```json
{
  "ten_dang_nhap": "admin",
  "mat_khau": "123456"
}
```

## 5) Common auth errors

- `401 Chua dang nhap`:
  - You skipped login.
  - Session cookie was not sent.
  - You switched host (e.g. login at `localhost` then call `127.0.0.1`).
- `403 Khong co quyen truy cap`:
  - Logged in with wrong role for endpoint.

## 6) Frontend on port 3000

If you run frontend from `localhost:3000`, set API base in browser console:

```js
setApiBase('http://localhost/QuanLySinhVien/api');
location.reload();
```

This stores `qlsv_api_base` in localStorage and keeps credentials enabled.

## 7) RESTful aliases (recommended)

API supports both legacy routes and canonical v1 aliases.

- Prefix version: `/api/v1/...`
- Examples:
  - `POST /api/v1/student/enrollments` (legacy: `/api/student/enroll`)
  - `POST /api/v1/student/submissions` with form-data `id_bai_tap` + `file`
  - `PATCH /api/v1/teacher/submissions/{id}` (legacy: `POST /api/teacher/submissions/{id}/grade`)
  - `GET /api/v1/admin/users/{id}` and similar GET by id for subjects/classes/materials/announcements
