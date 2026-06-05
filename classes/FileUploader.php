<?php

require_once __DIR__ . '/../config/config.php';

class FileUploader
{
    private array  $errors   = [];
    private ?array $fileInfo = null;

    public function handle(array $file): bool
    {
        $this->errors = [];

        if (!$this->validateUploadError($file['error'])) return false;
        if (!$this->validateSize($file['size']))          return false;

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!$this->validateExtension($ext)) return false;
        if (!$this->validateMime($file['tmp_name'], $ext)) return false;

        return $this->store($file['tmp_name'], $file['name'], $ext, $file['size']);
    }

    public function getErrors(): array  { return $this->errors; }
    public function getFileInfo(): ?array { return $this->fileInfo; }

    // ─── Валидация ───────────────────────────────────────────────────────────

    private function validateUploadError(int $error): bool
    {
        if ($error === UPLOAD_ERR_OK) return true;
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'Файл превышает лимит сервера.',
            UPLOAD_ERR_FORM_SIZE  => 'Файл превышает лимит формы.',
            UPLOAD_ERR_PARTIAL    => 'Файл загружен частично.',
            UPLOAD_ERR_NO_FILE    => 'Файл не выбран.',
            UPLOAD_ERR_NO_TMP_DIR => 'Отсутствует временная директория.',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл.',
        ];
        $this->errors[] = $messages[$error] ?? 'Неизвестная ошибка загрузки.';
        return false;
    }

    private function validateSize(int $size): bool
    {
        if ($size <= MAX_FILE_SIZE) return true;
        $mb = round(MAX_FILE_SIZE / 1048576);
        $this->errors[] = "Размер файла превышает {$mb} МБ.";
        return false;
    }

    private function validateExtension(string $ext): bool
    {
        if (array_key_exists($ext, ALLOWED_TYPES)) return true;
        $allowed = implode(', ', array_keys(ALLOWED_TYPES));
        $this->errors[] = "Недопустимый тип файла. Разрешены: {$allowed}.";
        return false;
    }

    private function validateMime(string $tmpPath, string $ext): bool
    {
        if (!extension_loaded('fileinfo')) {
            return $this->validateMagicBytes($tmpPath, $ext);
        }

        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->file($tmpPath);
        $expected = ALLOWED_TYPES[$ext];

        if ($detected === $expected) return true;

        // Расширенный список алиасов MIME-типов.
        // XLS и DOC — бинарные форматы OLE2, их MIME сильно зависит от версии
        // libmagic и ОС, поэтому список намеренно широкий.
        $aliases = [
            // ZIP-based (DOCX, XLSX)
            'application/zip'              => ['docx', 'xlsx'],
            'application/octet-stream'     => ['doc', 'xls', 'docx', 'xlsx'],
            // OLE2 Compound Document (DOC, XLS)
            'application/x-cfb'            => ['doc', 'xls'],
            'application/x-ole-storage'    => ['doc', 'xls'],
            'application/CDFV2'            => ['doc', 'xls'],
            'application/CDFV2-Encrypted'  => ['doc', 'xls'],
            'application/CDFV2-corrupt'    => ['doc', 'xls'],
            // Альтернативные MIME для XLS, встречающиеся на разных системах
            'application/vnd.ms-office'    => ['doc', 'xls'],
            'application/msword'           => ['doc'],
            'application/msexcel'          => ['xls'],
            'application/x-msexcel'        => ['xls'],
            'application/x-excel'          => ['xls'],
            'application/x-ms-excel'       => ['xls'],
        ];

        if (isset($aliases[$detected]) && in_array($ext, $aliases[$detected], true)) {
            return true;
        }

        // Для XLS — последняя линия защиты: доверяем магическим байтам,
        // так как MIME-детекция XLS крайне ненадёжна между системами.
        if ($ext === 'xls') {
            return $this->validateMagicBytes($tmpPath, $ext);
        }

        $this->errors[] = "MIME-тип файла ({$detected}) не совпадает с расширением.";
        return false;
    }

    private function validateMagicBytes(string $tmpPath, string $ext): bool
    {
        $handle = fopen($tmpPath, 'rb');
        if ($handle === false) {
            $this->errors[] = 'Не удалось прочитать временный файл.';
            return false;
        }
        $bytes = fread($handle, 8);
        fclose($handle);

        if ($ext === 'pdf') {
            if (str_starts_with($bytes, '%PDF')) return true;
            $this->errors[] = 'Файл не является корректным PDF.';
            return false;
        }

        if (in_array($ext, ['docx', 'xlsx'], true)) {
            if (str_starts_with($bytes, "PK\x03\x04")) return true;
            $this->errors[] = "Файл не является корректным {$ext}.";
            return false;
        }

        if (in_array($ext, ['doc', 'xls'], true)) {
            if (str_starts_with($bytes, "\xD0\xCF\x11\xE0")) return true;
            if ($ext === 'doc' && str_starts_with($bytes, '{\\rtf')) return true;
            $this->errors[] = "Файл не является корректным {$ext}.";
            return false;
        }

        return true;
    }

    // ─── Сохранение ──────────────────────────────────────────────────────────

    private function store(string $tmpPath, string $originalName, string $ext, int $size): bool
    {
        if (!is_dir(UPLOAD_DIR)) {
            if (!mkdir(UPLOAD_DIR, 0755, true)) {
                $this->errors[] = 'Не удалось создать директорию для загрузки.';
                return false;
            }
        }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $destPath   = UPLOAD_DIR . $storedName;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            $this->errors[] = 'Не удалось сохранить файл.';
            return false;
        }

        $this->fileInfo = [
            'original_name' => $this->sanitizeName($originalName),
            'stored_name'   => $storedName,
            'extension'     => $ext,
            'size'          => $size,
            'mime_type'     => ALLOWED_TYPES[$ext],
        ];

        return true;
    }

    private function sanitizeName(string $name): string
    {
        return preg_replace('/[^\w\s\.\-_\(\)\[\]а-яёА-ЯЁ]/u', '', $name);
    }
}