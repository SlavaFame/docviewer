<?php

require_once __DIR__ . '/../config/config.php';

/**
 * Управляет токенами доступа и безопасностью запросов.
 */
class SecurityManager
{
    private array $tokens = [];

    public function __construct()
    {
        session_start();
        if (!isset($_SESSION['doc_tokens'])) {
            $_SESSION['doc_tokens'] = [];
        }
        $this->tokens = &$_SESSION['doc_tokens'];
    }

    /**
     * Создаёт одноразовый токен для доступа к документу.
     */
    public function createToken(string $fileKey): string
    {
        $this->purgeExpiredTokens();

        $token = hash('sha256', $fileKey . TOKEN_SALT . random_bytes(16));

        $this->tokens[$token] = [
            'file_key' => $fileKey,
            'ip'       => $this->getClientIp(),
            'expires'  => time() + TOKEN_TTL,
            'used'     => false,
        ];

        return $token;
    }

    /**
     * Проверяет токен без его сжигания (для потокового чтения).
     */
    public function validateToken(string $token): ?string
    {
        $this->purgeExpiredTokens();

        if (!isset($this->tokens[$token])) {
            return null;
        }

        $entry = $this->tokens[$token];

        if ($entry['expires'] < time()) {
            unset($this->tokens[$token]);
            return null;
        }

        if ($entry['ip'] !== $this->getClientIp()) {
            return null;
        }

        return $entry['file_key'];
    }

    /**
     * Проверяет и сжигает токен (одноразовый режим).
     */
    public function consumeToken(string $token): ?string
    {
        $fileKey = $this->validateToken($token);
        if ($fileKey !== null) {
            unset($this->tokens[$token]);
        }
        return $fileKey;
    }

    /**
     * Удаляет просроченные токены.
     */
    private function purgeExpiredTokens(): void
    {
        $now = time();
        foreach ($this->tokens as $token => $data) {
            if ($data['expires'] < $now) {
                unset($this->tokens[$token]);
            }
        }
    }

    /**
     * Возвращает IP клиента.
     */
    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }

    /**
     * Проверяет CSRF-токен формы.
     */
    public function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCsrf(string $token): bool
    {
        return isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}
