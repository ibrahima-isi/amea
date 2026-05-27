<?php
namespace Amea\Core;

class Flash
{
    private const SESSION_KEY = 'flash_message';

    private static array $cssMap = [
        'success' => 'alert-success',
        'error'   => 'alert-danger',
        'warning' => 'alert-warning',
        'info'    => 'alert-info',
    ];

    public function __construct(private Session $session) {}

    public function set(string $type, string $message): void
    {
        $this->session->set(self::SESSION_KEY, ['type' => $type, 'message' => $message]);
    }

    public function add(string $type, string $message): void
    {
        $this->set($type, $message);
    }

    public function get(): ?array
    {
        $flash = $this->session->get(self::SESSION_KEY);
        $this->session->remove(self::SESSION_KEY);
        return $flash ?: null;
    }

    public function cssClass(string $type): string
    {
        return self::$cssMap[$type] ?? 'alert-secondary';
    }
}
