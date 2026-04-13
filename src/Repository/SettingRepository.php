<?php
namespace Amea\Repository;

use Amea\Model\Setting;

class SettingRepository
{
    public function __construct(private \PDO $pdo) {}

    public function findByKey(string $key): ?Setting
    {
        $stmt = $this->pdo->prepare("SELECT * FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? Setting::fromRow($row) : null;
    }

    public function getValue(string $key, string $default = ''): string
    {
        return $this->findByKey($key)?->getValue() ?? $default;
    }

    public function upsert(string $key, string $value): bool
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        return $stmt->execute([$key, $value]);
    }

    public function getFooterReplacements(): array
    {
        return [
            '{{contact_email}}' => htmlspecialchars($this->getValue('contact_email', ''), ENT_QUOTES, 'UTF-8'),
            '{{contact_phone}}' => htmlspecialchars($this->getValue('contact_phone', ''), ENT_QUOTES, 'UTF-8'),
            '{{association_name}}' => htmlspecialchars($this->getValue('association_name', 'AEESGS'), ENT_QUOTES, 'UTF-8'),
            '{{year}}'          => (string)date('Y'),
        ];
    }
}
