<?php

declare(strict_types=1);

namespace Amea\Cache;

use Amea\Core\Runtime;

/**
 * Cloudflare Zone Cache Purge API v4 client.
 *
 * Uses a scoped least-privilege Bearer token (Zone -> Cache Purge: Purge) and fails open
 * with short bounded timeouts so a transient Cloudflare API error never blocks staff writes.
 * Never logs or returns API credentials.
 */
final class CloudflareCachePurger
{
    public const DEFAULT_API_BASE = 'https://api.cloudflare.com/client/v4';
    public const MAX_FILES_PER_REQUEST = 30;
    public const DEFAULT_TIMEOUT_SECONDS = 3;

    /** @var callable(string, array<string, string>, string, int): array{status: int, body: string, error: string} */
    private $transport;

    public function __construct(
        private string $zoneId,
        private string $apiToken,
        private bool $enabled = true,
        private string $apiBase = self::DEFAULT_API_BASE,
        private int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
        ?callable $transport = null
    ) {
        $this->zoneId = trim($zoneId);
        $this->apiToken = trim($apiToken);
        $this->apiBase = rtrim(trim($apiBase) !== '' ? trim($apiBase) : self::DEFAULT_API_BASE, '/');
        $this->timeoutSeconds = max(1, min(15, $timeoutSeconds));
        $this->transport = $transport ?? [self::class, 'curlPost'];
    }

    public static function fromEnvironment(?callable $transport = null): self
    {
        $zoneId = trim(Runtime::env('CLOUDFLARE_ZONE_ID'));
        $apiToken = trim(Runtime::env('CLOUDFLARE_API_TOKEN'));
        $enabledRaw = strtolower(trim(Runtime::env('CLOUDFLARE_CACHE_ENABLED')));
        $apiBase = trim(Runtime::env('CLOUDFLARE_API_BASE', self::DEFAULT_API_BASE));
        $timeoutRaw = trim(Runtime::env('CLOUDFLARE_CACHE_PURGE_TIMEOUT', (string) self::DEFAULT_TIMEOUT_SECONDS));
        $timeout = filter_var($timeoutRaw, FILTER_VALIDATE_INT) !== false
            ? (int) $timeoutRaw
            : self::DEFAULT_TIMEOUT_SECONDS;

        $explicitlyDisabled = in_array($enabledRaw, ['0', 'false', 'off', 'no'], true);
        $enabled = !$explicitlyDisabled && $zoneId !== '' && $apiToken !== '';

        return new self(
            $zoneId,
            $apiToken,
            $enabled,
            $apiBase !== '' ? $apiBase : self::DEFAULT_API_BASE,
            $timeout,
            $transport
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled
            && self::isValidZoneId($this->zoneId)
            && self::isValidToken($this->apiToken)
            && self::isAllowedApiBase($this->apiBase);
    }

    /**
     * Non-secret diagnostic summary safe for CLI output and logs.
     *
     * @return array{enabled: bool, zone_configured: bool, token_configured: bool, api_base: string, timeout_seconds: int}
     */
    public function status(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'zone_configured' => self::isValidZoneId($this->zoneId),
            'token_configured' => self::isValidToken($this->apiToken),
            'api_base' => $this->apiBase,
            'timeout_seconds' => $this->timeoutSeconds,
        ];
    }

    /**
     * Purge specific public URLs from Cloudflare edge cache in batches of 30.
     *
     * @param list<string> $urls
     * @return array{attempted: bool, ok: bool, purged: int, batches: int, errors: list<string>}
     */
    public function purgeUrls(array $urls): array
    {
        $validUrls = [];
        foreach ($urls as $url) {
            $trimmed = trim($url);
            if ($trimmed === '' || preg_match('/[\r\n\0]/', $trimmed) === 1) {
                continue;
            }
            $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));
            $host = parse_url($trimmed, PHP_URL_HOST);
            if (!in_array($scheme, ['http', 'https'], true) || !is_string($host) || $host === '') {
                continue;
            }
            $validUrls[$trimmed] = true;
        }
        $uniqueUrls = array_keys($validUrls);

        if (!$this->isEnabled() || $uniqueUrls === []) {
            return ['attempted' => false, 'ok' => true, 'purged' => 0, 'batches' => 0, 'errors' => []];
        }

        $purged = 0;
        $batches = 0;
        $errors = [];

        foreach (array_chunk($uniqueUrls, self::MAX_FILES_PER_REQUEST) as $chunk) {
            $batches++;
            $res = $this->sendPurge(['files' => $chunk]);
            if ($res['ok']) {
                $purged += count($chunk);
            } else {
                $errors[] = $res['error'];
            }
        }

        return [
            'attempted' => true,
            'ok' => $errors === [],
            'purged' => $purged,
            'batches' => $batches,
            'errors' => $errors,
        ];
    }

    /**
     * Purge the entire Cloudflare zone cache (used for deployments).
     *
     * @return array{attempted: bool, ok: bool, purged: int, batches: int, errors: list<string>}
     */
    public function purgeEverything(): array
    {
        return $this->purgeSelection(['purge_everything' => true], 1);
    }

    public function purgePrefixes(array $prefixes): array
    {
        return $this->purgeSelection(['prefixes' => $prefixes], count($prefixes));
    }

    private function purgeSelection(array $payload, int $count): array
    {
        if (!$this->isEnabled()) {
            return ['attempted' => false, 'ok' => true, 'purged' => 0, 'batches' => 0, 'errors' => []];
        }

        $res = $this->sendPurge($payload);
        return [
            'attempted' => true,
            'ok' => $res['ok'],
            'purged' => $res['ok'] ? $count : 0,
            'batches' => 1,
            'errors' => $res['ok'] ? [] : [$res['error']],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, error: string}
     */
    private function sendPurge(array $payload): array
    {
        $endpoint = $this->apiBase . '/zones/' . $this->zoneId . '/purge_cache';
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'AMEA-Cloudflare-Cache/1.0',
        ];

        try {
            $response = ($this->transport)($endpoint, $headers, $json, $this->timeoutSeconds);
        } catch (\Throwable $error) {
            $message = 'Cloudflare cache purge transport error: ' . $this->redact($error->getMessage());
            error_log('[cloudflare-cache] ' . $message);
            return ['ok' => false, 'error' => $message];
        }

        $status = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');
        $transportError = (string) ($response['error'] ?? '');

        if ($transportError !== '') {
            $message = 'Cloudflare cache purge request failed: ' . $this->redact($transportError);
            error_log('[cloudflare-cache] ' . $message);
            return ['ok' => false, 'error' => $message];
        }

        $decoded = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($decoded) && ($decoded['success'] ?? false) === true) {
            return ['ok' => true, 'error' => ''];
        }

        $apiMessage = "HTTP {$status}";
        if (is_array($decoded) && !empty($decoded['errors'][0]['message'])) {
            $apiMessage .= ' (' . (string) $decoded['errors'][0]['message'] . ')';
        }
        $message = 'Cloudflare cache purge rejected: ' . $this->redact($apiMessage);
        error_log('[cloudflare-cache] ' . $message);
        return ['ok' => false, 'error' => $message];
    }

    private function redact(string $text): string
    {
        if ($this->apiToken !== '') {
            $text = str_replace($this->apiToken, '[REDACTED]', $text);
        }
        return $text;
    }

    public static function isValidZoneId(string $zoneId): bool
    {
        return preg_match('/^[a-fA-F0-9]{32}$/', $zoneId) === 1;
    }

    public static function isValidToken(string $token): bool
    {
        return $token !== '' && strlen($token) <= 256 && preg_match('/[\r\n\0]/', $token) !== 1;
    }

    public static function isAllowedApiBase(string $apiBase): bool
    {
        $scheme = strtolower((string) parse_url($apiBase, PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($apiBase, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }
        if ($scheme === 'https') {
            return true;
        }
        $appEnv = strtolower(trim(Runtime::env('APP_ENV', 'production')));
        return $scheme === 'http'
            && $appEnv === 'test'
            && in_array($host, ['127.0.0.1', 'localhost'], true);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, error: string}
     */
    private static function curlPost(string $url, array $headers, string $body, int $timeoutSeconds): array
    {
        if (!function_exists('curl_init')) {
            return ['status' => 0, 'body' => '', 'error' => 'PHP curl extension is unavailable'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => '', 'error' => 'curl_init failed'];
        }

        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = $name . ': ' . $value;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = $responseBody === false ? curl_error($ch) : '';

        return [
            'status' => $status,
            'body' => is_string($responseBody) ? $responseBody : '',
            'error' => $error,
        ];
    }
}
