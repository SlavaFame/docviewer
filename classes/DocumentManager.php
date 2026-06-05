<?php

require_once __DIR__ . '/../config/config.php';

/**
 * Управляет метаданными документов через JSON-файл.
 */
class DocumentManager
{
    private string $indexFile;
    private array  $documents = [];

    public function __construct()
    {
        // Убеждаемся, что папка uploads существует
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }

        $this->indexFile = UPLOAD_DIR . '.index.json';
        $this->load();
    }

    public function add(array $meta): Document
    {
        $doc = new Document(
            id:           $this->generateId(),
            originalName: $meta['original_name'],
            storedName:   $meta['stored_name'],
            extension:    $meta['extension'],
            size:         $meta['size'],
            mimeType:     $meta['mime_type'],
            uploadedAt:   time(),
        );

        $this->documents[$doc->id] = $doc->toArray();
        $this->save();

        return $doc;
    }

    public function getAll(): array
    {
        $docs = array_values($this->documents);
        usort($docs, fn($a, $b) => $b['uploaded_at'] <=> $a['uploaded_at']);
        return array_map(fn($d) => Document::fromArray($d), $docs);
    }

    public function getById(string $id): ?Document
    {
        return isset($this->documents[$id])
            ? Document::fromArray($this->documents[$id])
            : null;
    }

    public function delete(string $id): bool
    {
        $doc = $this->getById($id);
        if (!$doc) return false;

        $path = UPLOAD_DIR . $doc->storedName;
        if (file_exists($path)) unlink($path);

        unset($this->documents[$id]);
        $this->save();
        return true;
    }

    private function load(): void
    {
        if (!file_exists($this->indexFile)) {
            $this->documents = [];
            return;
        }

        $raw = file_get_contents($this->indexFile);
        if ($raw === false) {
            error_log('DocumentManager: не удалось прочитать ' . $this->indexFile);
            $this->documents = [];
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            error_log('DocumentManager: повреждён JSON в ' . $this->indexFile);
            $this->documents = [];
            return;
        }

        $this->documents = $decoded;
    }

    private function save(): void
    {
        $dir = dirname($this->indexFile);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                error_log('DocumentManager: не удалось создать директорию ' . $dir);
                return;
            }
        }

        if (!is_writable($dir)) {
            error_log('DocumentManager: директория недоступна для записи: ' . $dir);
            return;
        }

        try {
            $json = json_encode(
                $this->documents,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            error_log('DocumentManager: ошибка сериализации JSON: ' . $e->getMessage());
            return;
        }

        $tmpFile = $this->indexFile . '.tmp.' . getmypid();
        $written = file_put_contents($tmpFile, $json, LOCK_EX);

        if ($written === false) {
            error_log('DocumentManager: не удалось записать временный файл ' . $tmpFile);
            @unlink($tmpFile);
            return;
        }

        if (!rename($tmpFile, $this->indexFile)) {
            error_log('DocumentManager: не удалось переименовать ' . $tmpFile . ' → ' . $this->indexFile);
            @unlink($tmpFile);
        }
    }

    private function generateId(): string
    {
        return 'doc_' . bin2hex(random_bytes(8));
    }
}


class Document
{
    public function __construct(
        public readonly string $id,
        public readonly string $originalName,
        public readonly string $storedName,
        public readonly string $extension,
        public readonly int    $size,
        public readonly string $mimeType,
        public readonly int    $uploadedAt,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id:           $d['id'],
            originalName: $d['original_name'],
            storedName:   $d['stored_name'],
            extension:    $d['extension'],
            size:         $d['size'],
            mimeType:     $d['mime_type'],
            uploadedAt:   $d['uploaded_at'],
        );
    }

    public function toArray(): array
    {
        return [
            'id'            => $this->id,
            'original_name' => $this->originalName,
            'stored_name'   => $this->storedName,
            'extension'     => $this->extension,
            'size'          => $this->size,
            'mime_type'     => $this->mimeType,
            'uploaded_at'   => $this->uploadedAt,
        ];
    }

    public function formattedSize(): string
    {
        if ($this->size >= 1048576) return round($this->size / 1048576, 1) . ' МБ';
        if ($this->size >= 1024)    return round($this->size / 1024, 1)    . ' КБ';
        return $this->size . ' Б';
    }

    public function formattedDate(): string
    {
        return date('d.m.Y H:i', $this->uploadedAt);
    }

    public function badgeClass(): string
    {
        return match($this->extension) {
            'pdf'         => 'danger',
            'docx', 'doc' => 'primary',
            'xlsx', 'xls' => 'success',
            default       => 'secondary',
        };
    }

    public function iconClass(): string
    {
        return match($this->extension) {
            'pdf'         => 'uil-file-alt',
            'docx', 'doc' => 'uil-file-copy-alt',
            'xlsx', 'xls' => 'uil-table',
            default       => 'uil-file',
        };
    }
}