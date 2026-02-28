<?php

namespace NinjaPortal\Mfa\Tests\Unit;

use NinjaPortal\Mfa\Support\Base32;
use NinjaPortal\Mfa\Support\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_it_generates_and_verifies_totp_codes(): void
    {
        $service = new TotpService(new Base32);
        $secret = $service->generateSecret(20);

        $timestamp = 1_700_000_000;
        $counter = intdiv($timestamp, 30);
        $code = $service->at($secret, $counter, 6);

        $this->assertTrue($service->verifyCode($secret, $code, 1, 30, 6, $timestamp));
        $this->assertFalse($service->verifyCode($secret, '000000', 0, 30, 6, $timestamp));
    }

    public function test_base32_round_trip(): void
    {
        $base32 = new Base32;
        $raw = random_bytes(16);

        $this->assertSame($raw, $base32->decode($base32->encode($raw)));
    }
}
