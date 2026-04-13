<?php
namespace Amea\Core;

class CsrfGuard
{
    private const SESSION_KEY = 'csrf_token';

    public function __construct(private Session $session) {}

    public function getToken(): string
    {
        if (!$this->session->has(self::SESSION_KEY)) {
            $this->session->set(self::SESSION_KEY, bin2hex(random_bytes(32)));
        }
        return $this->session->get(self::SESSION_KEY);
    }

    public function verify(string $token): bool
    {
        if (empty($token) || !$this->session->has(self::SESSION_KEY)) {
            return false;
        }
        return hash_equals($this->session->get(self::SESSION_KEY), $token);
    }

    public function regenerate(): void
    {
        $this->session->set(self::SESSION_KEY, bin2hex(random_bytes(32)));
    }
}
