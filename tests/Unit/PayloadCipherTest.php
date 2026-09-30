<?php

namespace Nurschool\Tests\Unit;

use Nurschool\Messenger\PayloadCipher;
use PHPUnit\Framework\TestCase;

final class PayloadCipherTest extends TestCase
{
    public function testRoundTripUsesFreshNonceAndSupportsBinaryPayloads(): void
    {
        $cipher = new PayloadCipher(base64_encode(str_repeat('k', 32)));
        $payload = "reset token\0\xffsecret";
        $first = $cipher->encrypt($payload);
        $second = $cipher->encrypt($payload);
        self::assertNotSame($first, $second);
        self::assertStringNotContainsString('reset token', $first);
        self::assertSame($payload, $cipher->decrypt($first));
        self::assertSame($payload, $cipher->decrypt($second));
        self::assertSame('', $cipher->decrypt($cipher->encrypt('')));
    }

    /** @dataProvider invalidKeys */
    public function testRejectsInvalidKey(string $key): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PayloadCipher($key);
    }

    public static function invalidKeys(): iterable
    {
        yield [''];
        yield ['not!base64'];
        yield [base64_encode(str_repeat('k', 31))];
        yield [base64_encode(str_repeat('k', 33))];
    }

    /** @dataProvider mutations */
    public function testRejectsTamperingAndWrongKey(string $kind): void
    {
        $cipher = new PayloadCipher(base64_encode(str_repeat('k', 32)));
        $payload = $cipher->encrypt('live reset token');
        if ($kind === 'wrong key') {
            $cipher = new PayloadCipher(base64_encode(str_repeat('x', 32)));
        } else {
            $bytes = base64_decode(substr($payload, 3), true);
            $offset = match ($kind) { 'nonce' => 0, 'tag' => 12, default => 28 };
            $bytes[$offset] = chr(ord($bytes[$offset]) ^ 1);
            $payload = 'v1:'.base64_encode($bytes);
        }
        $this->expectException(\UnexpectedValueException::class);
        $cipher->decrypt($payload);
    }

    public static function mutations(): iterable
    {
        foreach (['nonce', 'tag', 'ciphertext', 'wrong key'] as $kind) {
            yield [$kind];
        }
    }

    /** @dataProvider malformedPayloads */
    public function testRejectsMalformedAndLegacyPlaintext(string $payload): void
    {
        $this->expectException(\UnexpectedValueException::class);
        (new PayloadCipher(base64_encode(str_repeat('k', 32))))->decrypt($payload);
    }

    public static function malformedPayloads(): iterable
    {
        yield [''];
        yield ['O:8:"Envelope":0:{}'];
        yield ['v2:abc'];
        yield ['v1:not!base64'];
        yield ['v1:'.base64_encode(str_repeat('x', 27))];
    }
}
