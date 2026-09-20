<?php

namespace App\Models;

use App\Core\Db;

/**
 * Провайдер связи: реквизиты договора, до 3 контактных менеджеров
 * (провайдер_менеджеры, позиция sort_order 0..2) и список обслуживаемых
 * юрлиц (многие-ко-многим через provider_legal_entities), для каждой связи
 * можно указать ответственного менеджера.
 */
class Provider
{
    public const MAX_MANAGERS = 3;

    private static array $fillable = ['name', 'contract_number', 'support_phone', 'note'];

    public static function all(string $search = ''): array
    {
        $sql = 'SELECT p.*, cu.name AS created_by_name, uu.name AS updated_by_name,
                       (SELECT COUNT(*) FROM provider_legal_entities ple WHERE ple.provider_id = p.id) AS legal_entities_count
                FROM providers p
                LEFT JOIN users cu ON cu.id = p.created_by
                LEFT JOIN users uu ON uu.id = p.updated_by';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE p.name LIKE :search OR p.contract_number LIKE :search';
            $params['search'] = "%{$search}%";
        }
        $sql .= ' ORDER BY p.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT p.*, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM providers p
                LEFT JOIN users cu ON cu.id = p.created_by
                LEFT JOIN users uu ON uu.id = p.updated_by
                WHERE p.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM providers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Менеджеры провайдера, упорядочены по позиции на форме (0..2) */
    public static function managers(int $providerId): array
    {
        $stmt = Db::connection()->prepare(
            'SELECT * FROM provider_managers WHERE provider_id = :id ORDER BY sort_order'
        );
        $stmt->execute(['id' => $providerId]);
        return $stmt->fetchAll();
    }

    /** Привязанные юрлица с именем юрлица и именем ответственного менеджера */
    public static function legalEntityLinks(int $providerId): array
    {
        $sql = 'SELECT ple.*, le.name AS legal_entity_name, le.inn AS legal_entity_inn,
                       pm.name AS manager_name, pm.phone AS manager_phone
                FROM provider_legal_entities ple
                JOIN legal_entities le ON le.id = ple.legal_entity_id
                LEFT JOIN provider_managers pm ON pm.id = ple.manager_id
                WHERE ple.provider_id = :id
                ORDER BY le.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $providerId]);
        return $stmt->fetchAll();
    }

    /** Провайдеры, обслуживающие данное юрлицо (обратная связь) */
    public static function forLegalEntity(int $legalEntityId): array
    {
        $sql = 'SELECT p.id, p.name, pm.name AS manager_name, pm.phone AS manager_phone
                FROM provider_legal_entities ple
                JOIN providers p ON p.id = ple.provider_id
                LEFT JOIN provider_managers pm ON pm.id = ple.manager_id
                WHERE ple.legal_entity_id = :legal_entity_id
                ORDER BY p.name';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['legal_entity_id' => $legalEntityId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $fields = self::$fillable;
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO providers ({$columns}, created_by, updated_by) VALUES ({$placeholders}, :created_by, :updated_by)";

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
        $sql = "UPDATE providers SET {$set}, updated_by = :updated_by WHERE id = :id";

        $params = ['id' => $id, 'updated_by' => $data['updated_by'] ?? null];
        foreach ($fields as $f) {
            $params[$f] = $data[$f] ?? null;
        }

        Db::connection()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        // provider_managers и provider_legal_entities удалятся каскадом (ON DELETE CASCADE)
        Db::connection()->prepare('DELETE FROM providers WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(*) FROM providers')->fetchColumn();
    }

    public static function options(): array
    {
        $stmt = Db::connection()->query('SELECT id, name FROM providers ORDER BY name');
        return $stmt->fetchAll();
    }

    /**
     * Список провайдеров с ID привязанных юрлиц (для клиентской фильтрации
     * в форме внешнего номера: "выбрали юрлицо -> показали его провайдеров").
     * legal_entity_ids — строка вида "1,4,7" или пусто.
     */
    public static function optionsWithLegalEntities(): array
    {
        $sql = 'SELECT p.id, p.name,
                       GROUP_CONCAT(ple.legal_entity_id ORDER BY ple.legal_entity_id) AS legal_entity_ids
                FROM providers p
                LEFT JOIN provider_legal_entities ple ON ple.provider_id = p.id
                GROUP BY p.id, p.name
                ORDER BY p.name';
        $stmt = Db::connection()->query($sql);
        return $stmt->fetchAll();
    }

    /**
     * Полностью заменяет набор менеджеров провайдера.
     * $rows — [['sort_order' => int, 'name' => ?string, 'phone' => ?string], ...],
     * уже отфильтрованные от полностью пустых слотов.
     * Возвращает карту [sort_order => manager_id] для новых записей.
     */
    public static function replaceManagers(int $providerId, array $rows): array
    {
        $conn = Db::connection();
        $conn->prepare('DELETE FROM provider_managers WHERE provider_id = :id')->execute(['id' => $providerId]);

        $bySortOrder = [];
        if (empty($rows)) {
            return $bySortOrder;
        }
        $stmt = $conn->prepare(
            'INSERT INTO provider_managers (provider_id, name, phone, sort_order) VALUES (:provider_id, :name, :phone, :sort_order)'
        );
        foreach ($rows as $row) {
            $stmt->execute([
                'provider_id' => $providerId,
                'name' => $row['name'] ?? null,
                'phone' => $row['phone'] ?? null,
                'sort_order' => $row['sort_order'],
            ]);
            $bySortOrder[(int)$row['sort_order']] = (int)$conn->lastInsertId();
        }
        return $bySortOrder;
    }

    /**
     * Полностью заменяет набор привязанных юрлиц провайдера.
     * $links — [['legal_entity_id' => int, 'manager_id' => ?int], ...]
     */
    public static function replaceLegalEntityLinks(int $providerId, array $links): void
    {
        $conn = Db::connection();
        $conn->prepare('DELETE FROM provider_legal_entities WHERE provider_id = :id')->execute(['id' => $providerId]);

        if (empty($links)) {
            return;
        }
        $stmt = $conn->prepare(
            'INSERT INTO provider_legal_entities (provider_id, legal_entity_id, manager_id) VALUES (:provider_id, :legal_entity_id, :manager_id)'
        );
        foreach ($links as $link) {
            $stmt->execute([
                'provider_id' => $providerId,
                'legal_entity_id' => $link['legal_entity_id'],
                'manager_id' => $link['manager_id'] ?? null,
            ]);
        }
    }

    /** Короткое читаемое резюме менеджеров — для журнала аудита */
    public static function summarizeManagers(array $managerRows): string
    {
        if (empty($managerRows)) {
            return '';
        }
        $parts = [];
        foreach ($managerRows as $m) {
            $name = $m['name'] ?? '';
            $phone = $m['phone'] ?? '';
            if ($name === '' && $phone === '') {
                continue;
            }
            $parts[] = trim($name . ($phone !== '' ? " ({$phone})" : ''));
        }
        return implode('; ', $parts);
    }

    /** Короткое читаемое резюме привязанных юрлиц — для журнала аудита */
    public static function summarizeLegalEntityLinks(array $linkRows): string
    {
        if (empty($linkRows)) {
            return '';
        }
        $parts = [];
        foreach ($linkRows as $l) {
            $name = $l['legal_entity_name'] ?? ('#' . ($l['legal_entity_id'] ?? ''));
            $manager = $l['manager_name'] ?? null;
            $parts[] = $manager ? "{$name} (менеджер: {$manager})" : "{$name} (без менеджера)";
        }
        return implode('; ', $parts);
    }
}
