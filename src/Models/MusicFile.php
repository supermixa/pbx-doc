<?php

namespace App\Models;

use App\Core\Db;

/**
 * Справочник "Музыка" — аудиофайлы WAV/MP3 (фоновая музыка, объявления и
 * т.п.), используемые в настройках очередей/групп обзвона.
 *
 * original_filename — как файл назывался при загрузке (поддерживаются
 * русские названия); stored_filename — безопасное уникальное имя файла на
 * диске в storage/music/, не зависящее от кодировки файловой системы сервера.
 */
class MusicFile
{
    public const ALLOWED_EXTENSIONS = ['wav', 'mp3'];
    public const ALLOWED_MIME_TYPES = [
        'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave',
        'audio/mpeg', 'audio/mp3',
    ];

    private static array $fillable = ['title', 'original_filename', 'stored_filename', 'mime_type', 'size_bytes'];

    /** Список файлов, по умолчанию по дате и времени добавления (новые сверху) */
    public static function all(string $search = ''): array
    {
        $sql = 'SELECT m.*, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM music_files m
                LEFT JOIN users cu ON cu.id = m.created_by
                LEFT JOIN users uu ON uu.id = m.updated_by';
        $params = [];
        if ($search !== '') {
            $sql .= ' WHERE m.title LIKE :search';
            $params['search'] = "%{$search}%";
        }
        $sql .= ' ORDER BY m.created_at DESC, m.id DESC';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT m.*, cu.name AS created_by_name, uu.name AS updated_by_name
                FROM music_files m
                LEFT JOIN users cu ON cu.id = m.created_by
                LEFT JOIN users uu ON uu.id = m.updated_by
                WHERE m.id = :id';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::connection()->prepare('SELECT * FROM music_files WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Для выпадающих списков (MOH / объявление в форме номера) — по названию */
    public static function options(): array
    {
        $stmt = Db::connection()->query('SELECT id, title FROM music_files ORDER BY title');
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $fields = self::$fillable;
        $columns = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO music_files ({$columns}, created_by, updated_by) VALUES ({$placeholders}, :created_by, :updated_by)";

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

    /** Переименование (меняется только название, файл на диске не трогаем) */
    public static function rename(int $id, string $title, ?int $updatedBy): void
    {
        $stmt = Db::connection()->prepare('UPDATE music_files SET title = :title, updated_by = :updated_by WHERE id = :id');
        $stmt->execute(['id' => $id, 'title' => $title, 'updated_by' => $updatedBy]);
    }

    public static function delete(int $id): void
    {
        Db::connection()->prepare('DELETE FROM music_files WHERE id = :id')->execute(['id' => $id]);
    }

    public static function count(): int
    {
        return (int)Db::connection()->query('SELECT COUNT(*) FROM music_files')->fetchColumn();
    }

    /**
     * Где используется файл (для блокировки удаления). Пока музыка
     * используется только в настройках очереди (MOH и рекламная фраза) —
     * возвращает список номеров с указанием, в каком именно поле.
     * @return array{0: array, 1: array} [используется как MOH, используется как объявление]
     */
    public static function usages(int $id): array
    {
        $sql = 'SELECT e.id, e.number, e.category, e.type
                FROM extension_queue_settings eqs
                JOIN extensions e ON e.id = eqs.extension_id
                WHERE eqs.moh_music_id = :id
                ORDER BY e.number';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $mohUsages = $stmt->fetchAll();

        $sql = 'SELECT e.id, e.number, e.category, e.type
                FROM extension_queue_settings eqs
                JOIN extensions e ON e.id = eqs.extension_id
                WHERE eqs.announcement_music_id = :id
                ORDER BY e.number';
        $stmt = Db::connection()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $announcementUsages = $stmt->fetchAll();

        return [$mohUsages, $announcementUsages];
    }

    public static function isInUse(int $id): bool
    {
        [$moh, $announcement] = self::usages($id);
        return !empty($moh) || !empty($announcement);
    }
}
