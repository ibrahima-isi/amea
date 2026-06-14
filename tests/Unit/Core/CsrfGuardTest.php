<?php

namespace Amea\Tests\Unit\Core;

use Amea\Core\CsrfGuard;
use Amea\Core\Session;
use PHPUnit\Framework\TestCase;

class CsrfGuardTest extends TestCase
{
    private Session $session;
    private CsrfGuard $csrf;

    protected function setUp(): void
    {
        $_SESSION = [];
        $this->session = new Session();
        $this->csrf    = new CsrfGuard($this->session);
    }

    public function testGetTokenReturns64CharHexString(): void
    {
        $token = $this->csrf->getToken();
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGetTokenReturnsSameTokenOnSubsequentCalls(): void
    {
        $first  = $this->csrf->getToken();
        $second = $this->csrf->getToken();
        $this->assertSame($first, $second);
    }

    public function testVerifyCorrectTokenReturnsTrue(): void
    {
        $token = $this->csrf->getToken();
        $this->assertTrue($this->csrf->verify($token));
    }

    public function testVerifyEmptyStringReturnsFalse(): void
    {
        $this->csrf->getToken(); // ensure token exists in session
        $this->assertFalse($this->csrf->verify(''));
    }

    public function testVerifyWrongTokenReturnsFalse(): void
    {
        $this->csrf->getToken(); // ensure token exists in session
        $this->assertFalse($this->csrf->verify('wrong_token'));
    }

    public function testVerifyWithNoTokenInSessionReturnsFalse(): void
    {
        // No call to getToken() — session has no csrf_token key
        $this->assertFalse($this->csrf->verify('anyvalue'));
    }

    public function testRegenerateProducesNewToken(): void
    {
        $before = $this->csrf->getToken();
        $this->csrf->regenerate();
        $after = $this->csrf->getToken();

        // Extremely unlikely to collide; tokens are random 32 bytes
        $this->assertNotSame($before, $after);
    }

    public function testOldTokenRejectedAfterRegenerate(): void
    {
        $oldToken = $this->csrf->getToken();
        $this->csrf->regenerate();

        $this->assertFalse($this->csrf->verify($oldToken));
    }

    public function testNewTokenValidAfterRegenerate(): void
    {
        $this->csrf->getToken(); // seed initial
        $this->csrf->regenerate();
        $newToken = $this->csrf->getToken();

        $this->assertTrue($this->csrf->verify($newToken));
    }
}
