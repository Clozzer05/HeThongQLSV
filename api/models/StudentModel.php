<?php

require_once __DIR__ . '/../core/Database.php';

class StudentModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function all(?string $search = null): array
    {
        if ($search !== null && $search !== '') {
            $sql = 'SELECT * FROM sinh_vien
                    WHERE ma_sv LIKE :q OR ho_ten LIKE :q OR email LIKE :q OR lop LIKE :q
                    ORDER BY id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['q' => '%' . $search . '%']);
            return $stmt->fetchAll();
        }

        $stmt = $this->db->query('SELECT * FROM sinh_vien ORDER BY id DESC');
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM sinh_vien WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $student = $stmt->fetch();

        return $student ?: null;
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO sinh_vien (ma_sv, ho_ten, email, sdt, ngay_sinh, gioi_tinh, lop)
                VALUES (:ma_sv, :ho_ten, :email, :sdt, :ngay_sinh, :gioi_tinh, :lop)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ma_sv' => $data['ma_sv'],
            'ho_ten' => $data['ho_ten'],
            'email' => $data['email'],
            'sdt' => $data['sdt'] ?? null,
            'ngay_sinh' => $data['ngay_sinh'] ?? null,
            'gioi_tinh' => $data['gioi_tinh'] ?? null,
            'lop' => $data['lop'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $sql = 'UPDATE sinh_vien
                SET ma_sv = :ma_sv,
                    ho_ten = :ho_ten,
                    email = :email,
                    sdt = :sdt,
                    ngay_sinh = :ngay_sinh,
                    gioi_tinh = :gioi_tinh,
                    lop = :lop,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :id';

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $id,
            'ma_sv' => $data['ma_sv'],
            'ho_ten' => $data['ho_ten'],
            'email' => $data['email'],
            'sdt' => $data['sdt'] ?? null,
            'ngay_sinh' => $data['ngay_sinh'] ?? null,
            'gioi_tinh' => $data['gioi_tinh'] ?? null,
            'lop' => $data['lop'] ?? null,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM sinh_vien WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
