<?php

namespace Amea\Tests\Unit;

use PHPUnit\Framework\TestCase;

// Throwaway: proves the CI check turns red. Reverted before merge.
class CiRedProofTest extends TestCase
{
    public function testDeliberatelyFailingToProveTheCiCheckGoesRed(): void
    {
        $this->assertTrue(false, 'Deliberate failure: CI must report this check as failed.');
    }
}
