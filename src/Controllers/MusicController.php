<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\AuditLogger;
use App\Core\Csrf;
use App\Core\Env;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Models\AuditLog;
use App\Models\MusicFile;

class MusicController
{
    /** Папка на диске для хранения файлов музыки (вне public/, отдаётся только через stream()) */
    private static function storageDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/music';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    private static function maxUploadBytes(): int
    {
        $mb = (int)Env::get('MUSIC_MAX_UPLOAD_MB', 20);
        return max(1, $mb) * 1024 * 1024;
    }

    public function index(): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $search = trim($_GET['q'] ?? '');
        View::render('music/index', [
            'title' => 'Музыка',
            'files' => MusicFile::all($search),
            'search' => $search,
            'maxUploadMb' => (int)Env::get('MUSIC_MAX_UPLOAD_MB', 20),
        ]);
    }

    public function show(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $file = MusicFile::find((int)$params['id']);
        if (!$file) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        [$mohUsages, $announcementUsages] = MusicFile::usages((int)$file['id']);
        View::render('music/show', [
            'title' => $file['title'],
            'file' => $file,
            'mohUsages' => $mohUsages,
            'announcementUsages' => $announcementUsages,
            'history' => AuditLog::forEntity('music_file', (int)$file['id']),
        ]);
    }

    public function create(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        View::render('music/form', [
            'title' => 'Добавить файл музыки',
            'file' => null,
            'errors' => [],
            'maxUploadMb' => (int)Env::get('MUSIC_MAX_UPLOAD_MB', 20),
        ]);
    }

    public function store(): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $v = new Validator($_POST);
        $v->required('title', 'Название')->maxLength('title', 190, 'Название');

        $upload = $_FILES['file'] ?? null;
        $storedInfo = $this->validateAndStoreUpload($upload, $v);

        if ($v->fails()) {
            View::render('music/form', [
                'title' => 'Добавить файл музыки',
                'file' => $_POST,
                'errors' => $v->errors(),
                'maxUploadMb' => (int)Env::get('MUSIC_MAX_UPLOAD_MB', 20),
            ]);
            return;
        }

        $title = $v->get('title');
        if ($title === '' || $title === null) {
            $title = pathinfo($storedInfo['original_filename'], PATHINFO_FILENAME);
        }

        $data = [
            'title' => $title,
            'original_filename' => $storedInfo['original_filename'],
            'stored_filename' => $storedInfo['stored_filename'],
            'mime_type' => $storedInfo['mime_type'],
            'size_bytes' => $storedInfo['size_bytes'],
            'created_by' => Auth::id(),
        ];
        $id = MusicFile::create($data);
        AuditLogger::logCreate('music_file', $id, $data);

        Flash::success('Файл добавлен в справочник «Музыка»');
        header('Location: /music/' . $id);
    }

    /**
     * Быстрая загрузка "своего" файла прямо из формы номера (MOH / рекламная
     * фраза): AJAX-запрос, возвращает JSON {id, title} или {error}.
     */
    public function quickUpload(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Auth::check() || !Auth::canEdit()) {
            http_response_code(403);
            echo json_encode(['error' => 'Недостаточно прав']);
            return;
        }
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo json_encode(['error' => 'Сессия истекла, обновите страницу']);
            return;
        }

        $v = new Validator($_POST);
        $upload = $_FILES['file'] ?? null;
        $storedInfo = $this->validateAndStoreUpload($upload, $v);
        if ($v->fails()) {
            http_response_code(422);
            echo json_encode(['error' => $v->firstError()]);
            return;
        }

        $title = trim((string)($_POST['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo($storedInfo['original_filename'], PATHINFO_FILENAME);
        }

        $data = [
            'title' => $title,
            'original_filename' => $storedInfo['original_filename'],
            'stored_filename' => $storedInfo['stored_filename'],
            'mime_type' => $storedInfo['mime_type'],
            'size_bytes' => $storedInfo['size_bytes'],
            'created_by' => Auth::id(),
        ];
        $id = MusicFile::create($data);
        AuditLogger::logCreate('music_file', $id, $data);

        echo json_encode(['id' => $id, 'title' => $title]);
    }

    public function edit(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        $file = MusicFile::findRaw((int)$params['id']);
        if (!$file) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }
        View::render('music/form', [
            'title' => 'Переименовать файл',
            'file' => $file,
            'errors' => [],
            'maxUploadMb' => (int)Env::get('MUSIC_MAX_UPLOAD_MB', 20),
        ]);
    }

    public function update(array $params): void
    {
        if (!Auth::requireEdit()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = MusicFile::findRaw($id);
        if (!$old) {
            http_response_code(404);
            View::render('errors/404', []);
            return;
        }

        $v = new Validator($_POST);
        $v->required('title', 'Название')->maxLength('title', 190, 'Название');
        if ($v->fails()) {
            View::render('music/form', [
                'title' => 'Переименовать файл',
                'file' => array_merge($old, $_POST, ['id' => $id]),
                'errors' => $v->errors(),
                'maxUploadMb' => (int)Env::get('MUSIC_MAX_UPLOAD_MB', 20),
            ]);
            return;
        }

        $newTitle = $v->get('title');
        MusicFile::rename($id, $newTitle, Auth::id());
        AuditLogger::logUpdate('music_file', $id, $old, array_merge($old, ['title' => $newTitle]));

        Flash::success('Изменения сохранены');
        header('Location: /music/' . $id);
    }

    public function destroy(array $params): void
    {
        if (!Auth::requireAdmin()) {
            return;
        }
        Csrf::verifyRequestOrFail();

        $id = (int)$params['id'];
        $old = MusicFile::findRaw($id);
        if (!$old) {
            header('Location: /music');
            return;
        }

        if (MusicFile::isInUse($id)) {
            Flash::error('Нельзя удалить файл «' . $old['title'] . '» — он используется в настройках номера. Сначала отвяжите его.');
            header('Location: /music/' . $id);
            return;
        }

        $path = self::storageDir() . '/' . $old['stored_filename'];
        AuditLogger::logDelete('music_file', $id, $old);
        MusicFile::delete($id);
        if (is_file($path)) {
            @unlink($path);
        }
        Flash::success('Файл удалён');
        header('Location: /music');
    }

    /** Отдаёт содержимое файла в браузер (плеер/скачивание), с поддержкой Range для перемотки */
    public function stream(array $params): void
    {
        if (!Auth::requireLogin()) {
            return;
        }
        $file = MusicFile::findRaw((int)$params['id']);
        if (!$file) {
            http_response_code(404);
            return;
        }
        $path = self::storageDir() . '/' . $file['stored_filename'];
        if (!is_file($path)) {
            http_response_code(404);
            echo 'Файл не найден на диске';
            return;
        }

        $size = filesize($path);
        $mime = $file['mime_type'] ?: 'application/octet-stream';
        $start = 0;
        $end = $size - 1;

        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Content-Disposition: inline; filename="' . rawurlencode($file['original_filename']) . '"');

        $range = $_SERVER['HTTP_RANGE'] ?? null;
        if ($range && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
            $start = $m[1] === '' ? 0 : (int)$m[1];
            $end = $m[2] === '' ? $size - 1 : (int)$m[2];
            $end = min($end, $size - 1);
            http_response_code(206);
            header("Content-Range: bytes {$start}-{$end}/{$size}");
        }

        header('Content-Length: ' . ($end - $start + 1));

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            http_response_code(500);
            return;
        }
        fseek($handle, $start);
        $remaining = $end - $start + 1;
        $chunk = 8192;
        while ($remaining > 0 && !feof($handle)) {
            $read = (int)min($chunk, $remaining);
            echo fread($handle, $read);
            $remaining -= $read;
            flush();
        }
        fclose($handle);
    }

    /**
     * Валидирует загруженный файл (наличие, формат wav/mp3, размер) и, если
     * всё в порядке, перемещает его в storage/music/ под безопасным именем.
     * Возвращает данные для сохранения в БД или null, если валидация не прошла
     * (ошибка уже добавлена в $v).
     */
    private function validateAndStoreUpload(?array $upload, Validator $v): ?array
    {
        if (!$upload || !isset($upload['error']) || $upload['error'] === UPLOAD_ERR_NO_FILE) {
            $v->addError('file', 'Выберите файл в формате WAV или MP3');
            return null;
        }
        if ($upload['error'] !== UPLOAD_ERR_OK) {
            $v->addError('file', 'Ошибка загрузки файла (код ' . $upload['error'] . ')');
            return null;
        }
        if (!is_uploaded_file($upload['tmp_name'])) {
            $v->addError('file', 'Некорректная загрузка файла');
            return null;
        }
        if ($upload['size'] > self::maxUploadBytes()) {
            $mb = (int)Env::get('MUSIC_MAX_UPLOAD_MB', 20);
            $v->addError('file', "Файл слишком большой (максимум {$mb} МБ)");
            return null;
        }

        $originalName = $upload['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, MusicFile::ALLOWED_EXTENSIONS, true)) {
            $v->addError('file', 'Допустимые форматы: WAV, MP3');
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $upload['tmp_name']) : null;
        if ($finfo) {
            finfo_close($finfo);
        }
        if ($mime && !in_array($mime, MusicFile::ALLOWED_MIME_TYPES, true)) {
            // Некоторые системы определяют wav/mp3 нестандартной MIME-строкой —
            // не блокируем жёстко по MIME, если расширение верное, но
            // подстраховываемся от явно постороннего контента (html, php и т.п.)
            if (!str_starts_with((string)$mime, 'audio/')) {
                $v->addError('file', 'Файл не похож на аудио (WAV/MP3)');
                return null;
            }
        }

        $storedFilename = bin2hex(random_bytes(16)) . '.' . $ext;
        $destination = self::storageDir() . '/' . $storedFilename;
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            $v->addError('file', 'Не удалось сохранить файл на сервере');
            return null;
        }

        return [
            'original_filename' => $originalName,
            'stored_filename' => $storedFilename,
            'mime_type' => $mime ?: ($ext === 'mp3' ? 'audio/mpeg' : 'audio/wav'),
            'size_bytes' => (int)$upload['size'],
        ];
    }
}
