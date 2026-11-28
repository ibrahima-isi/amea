<?php
namespace Amea\Repository;

use Amea\Cache\AppCache;
use Amea\Core\Container;
use Amea\Model\Setting;

class SettingRepository
{
    private const CACHE_TTL = 300;

    public function __construct(
        private \PDO $pdo,
        private ?AppCache $cache = null
    ) {}

    public function findByKey(string $key): ?Setting
    {
        $stmt = $this->pdo->prepare("SELECT * FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? Setting::fromRow($row) : null;
    }

    /**
     * Load all settings in a single indexed query, cached in AppCache to eliminate
     * per-request repeated lookups from BaseController::injectGlobalVariables().
     *
     * @return array<string, string>
     */
    public function getAllMap(): array
    {
        return $this->resolveCache()->remember(AppCache::NS_SETTINGS, 'all_map', self::CACHE_TTL, function (): array {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM settings");
            $map = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                if (isset($row['setting_key'])) {
                    $map[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
                }
            }
            return $map;
        });
    }

    public function getValue(string $key, string $default = ''): string
    {
        $map = $this->getAllMap();
        return array_key_exists($key, $map) ? $map[$key] : $default;
    }

    public function upsert(string $key, string $value): bool
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value"
            );
        } else {
            $stmt = $this->pdo->prepare(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
        }
        $ok = $stmt->execute([$key, $value]);
        if ($ok && !$this->pdo->inTransaction()) {
            $this->resolveCache()->invalidateNamespace(AppCache::NS_SETTINGS);
        }
        return $ok;
    }

    public function saveAll(array $settings): void
    {
        $this->pdo->beginTransaction();
        try {
            foreach ($settings as $key => $value) {
                if (!$this->upsert($key, $value)) {
                    throw new \RuntimeException('Unable to save settings.');
                }
            }
            if (!$this->pdo->commit()) {
                throw new \RuntimeException('Unable to commit settings.');
            }
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
        $this->resolveCache()->invalidateNamespace(AppCache::NS_SETTINGS);
    }

    private function resolveCache(): AppCache
    {
        return $this->cache ?? Container::cacheForConnection($this->pdo);
    }
}
