<?php

require_once __DIR__ . '/../models/StudentModel.php';
require_once __DIR__ . '/../core/Response.php';

class StudentController
{
    private StudentModel $students;

    public function __construct()
    {
        $this->students = new StudentModel();
    }

    public function index(): void
    {
        $q = $_GET['q'] ?? null;
        $data = $this->students->all($q);

        Response::json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function show(int $id): void
    {
        $student = $this->students->find($id);

        if (!$student) {
            Response::error('Không tìm thấy sinh viên.', 404);
            return;
        }

        Response::json([
            'success' => true,
            'data' => $student,
        ]);
    }

    public function store(array $payload): void
    {
        $validation = $this->validate($payload);
        if ($validation !== true) {
            Response::error($validation, 422);
            return;
        }

        try {
            $id = $this->students->create($payload);
            $student = $this->students->find($id);

            Response::json([
                'success' => true,
                'message' => 'Tạo sinh viên thành công.',
                'data' => $student,
            ], 201);
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                Response::error('Mã sinh viên hoặc email đã tồn tại.', 409);
                return;
            }

            Response::error('Không thể tạo sinh viên.', 500);
        }
    }

    public function update(int $id, array $payload): void
    {
        if (!$this->students->find($id)) {
            Response::error('Không tìm thấy sinh viên.', 404);
            return;
        }

        $validation = $this->validate($payload);
        if ($validation !== true) {
            Response::error($validation, 422);
            return;
        }

        try {
            $this->students->update($id, $payload);
            $student = $this->students->find($id);

            Response::json([
                'success' => true,
                'message' => 'Cập nhật sinh viên thành công.',
                'data' => $student,
            ]);
        } catch (PDOException $e) {
            if ((int) $e->getCode() === 23000) {
                Response::error('Mã sinh viên hoặc email đã tồn tại.', 409);
                return;
            }

            Response::error('Không thể cập nhật sinh viên.', 500);
        }
    }

    public function destroy(int $id): void
    {
        if (!$this->students->find($id)) {
            Response::error('Không tìm thấy sinh viên.', 404);
            return;
        }

        $this->students->delete($id);

        Response::json([
            'success' => true,
            'message' => 'Xóa sinh viên thành công.',
        ]);
    }

    private function validate(array $payload)
    {
        if (empty(trim($payload['ma_sv'] ?? ''))) {
            return 'Mã sinh viên là bắt buộc.';
        }

        if (empty(trim($payload['ho_ten'] ?? ''))) {
            return 'Họ tên là bắt buộc.';
        }

        if (empty(trim($payload['email'] ?? ''))) {
            return 'Email là bắt buộc.';
        }

        if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            return 'Email không hợp lệ.';
        }

        return true;
    }
}
