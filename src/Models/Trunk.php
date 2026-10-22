<?php

namespace App\Models;

use App\Core\Db;

class Trunk
{
    public const STATUSES = ['active', 'inactive'];
    public const TYPES = ['PJSIP', 'SIP', 'IAX2', 'DAHDI', 'Other'];

    /**
     * provider_id — провайдер из справочника «Провайдеры».
     * provider — устаревшее текстовое поле (значение, введённое вручную до
     * появления справочника и не сопоставленное с ним миграцией 0007).
     * Новые записи его не заполняют, см. TrunkController::validate().
     */
    private static array $fillable = [
        'name', 'type', 'provider_id', 'provider', 'line_capacity', 'host', 'context', 'status', 'description',
    ];

    private const SELECT_FROM = 'SELECT t.*, p.name AS provider_name,
                       cu.name AS created_by_name, uu.name AS updated_by_name
                FROM trunks t
                LEFT JOIN providers p ON p.id = t.provider_id
                LEFT JOIN users cu ON cu.id = t.created_by
                LEFT JOIN users uu ON uu.id = t.updated_by';

    public static function all(string $search = ''): array
    {
        $sql = self::SELECT_FROM;
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE t.name LIKE :search1 OR p.name LIKE :search2 OR t.provider LIKE :search3 OR t.host LIKE :search4';
            $params['search1'] = "%{$search}%";
            $params['search2'] = "%{$search}%";
            $params['search3'] = "%{$search}%";
            $params['search4'] = "%{$search}%";
        }
        $sql .= ' ORDER BY t.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $sql = self::SELECT_FROM . ' WHERE t.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Сырые данные без джойнов — для сравнения при аудите изменений */
    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM trunks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $fields = self::$fillable;
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO trunks ({$columns}, created_by, updated_by) VALUES ({$placeholders}, :created_by, :updated_by)";

        $params = [];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }
        $params['created_by'] = $data['created_by'] ?? null;
        $params['updated_by'] = $data['created_by'] ?? null;

        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return (int)Db::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $fields = self::$fillable;
        $set = implode(', ', array_map(fn($f) => "{$f} = :{$f}", $fields));
        $sql = "UPDATE trunks SET {$set}, updated_by = :updated_by WHERE id = :id";

        $params = ['id' => $id, 'updated_by' => $data['updated_by'] ?? null];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }

        Db::connection()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM trunks WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(*) FROM trunks')->fetchColumn();
    }

    public static function options(): array
    {
        $stmt = Db::connection()->query('SELECT id, name FROM trunks ORDER BY name');
        return $stmt->fetchAll();
    }

    /** Транки провайдера — для карточки провайдера */
    public static function forProvider(int $providerId): array
    {
        $stmt = Db::connection()->prepare('SELECT id, name, type, line_capacity, status FROM trunks WHERE provider_id = :id ORDER BY name');
        $stmt->execute(['id' => $providerId]);
        return $stmt->fetchAll();
    }
}
