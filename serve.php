<?php

declare(strict_types=1);

/**
 * Единственная точка отдачи файлов.
 * Файлы НИКОГДА не отдаются напрямую — только через этот скрипт.
 *
 * Защита:
 * - Проверка сессионного токена (привязан к IP, живёт TOKEN_TTL секунд)
 * - Content-Disposition: inline (не attachment — не даём браузеру предлагать скачать)
 * - Cache-Control: no-store
 * - X-Content-Type-Options: nosniff
 * - Отдача потоком (stream) — файл не читается целиком в память
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/SecurityManager.php';
require_once __DIR__ . '/classes/DocumentManager.php';

class ServeController
{
    private SecurityManager $security;
    private DocumentManager $docManager;

    public function __construct()
    {
        $this->security   = new SecurityManager();
        $this->docManager = new DocumentManager();
    }

    public function run(): void
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            $this->deny(400, 'Отсутствует токен.');
            return;
        }

        // Валидируем токен (не сжигаем — JS грузит файл один раз, но может переспросить)
        $docId = $this->security->validateToken($token);

        if ($docId === null) {
            $this->deny(403, 'Токен недействителен или истёк.');
            return;
        }

        $doc = $this->docManager->getById($docId);

        if ($doc === null) {
            $this->deny(404, 'Документ не найден.');
            return;
        }

        $filePath = UPLOAD_DIR . $doc->storedName;

        if (!file_exists($filePath) || !is_readable($filePath)) {
            $this->deny(404, 'Файл отсутствует на сервере.');
            return;
        }

        $this->stream($filePath, $doc->mimeType, $doc->originalName);
    }

    private function stream(string $path, string $mimeType, string $originalName): void
    {
        // Сбрасываем буфер
        if (ob_get_level()) {
            ob_end_clean();
        }

        $fileSize = filesize($path);

        // Заголовки безопасности
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline'); // НЕ attachment
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        // Запрещаем браузеру угадывать тип
        header('X-Download-Options: noopen');
        // Запрещаем сохранение в PDF viewer Chrome
        header('Content-Security-Policy: default-src \'none\'');

        // Потоковая отдача по 8 КБ
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->deny(500, 'Ошибка чтения файла.');
            return;
        }

        while (!feof($handle) && !connection_aborted()) {
            echo fread($handle, 8192);
            flush();
        }

        fclose($handle);
    }

    private function deny(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}

// ─── Bootstrap ────────────────────────────────────────────────────────────────
(new ServeController())->run();