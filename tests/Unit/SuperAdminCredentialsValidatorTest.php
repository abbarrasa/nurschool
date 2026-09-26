<?php

namespace Nurschool\Tests\Unit;

use Nurschool\Validation\SuperAdminCredentialsValidator;
use PHPUnit\Framework\TestCase;

final class SuperAdminCredentialsValidatorTest extends TestCase
{
    public function testEmailWhitespaceIsRemoved(): void
    {
        self::assertSame('Admin@example.com', (new SuperAdminCredentialsValidator())->email(' Admin@example.com '));
    }

    /** @dataProvider invalidEmails */
    public function testInvalidEmailsAreRejected(mixed $email): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SuperAdminCredentialsValidator())->email($email);
    }

    public static function invalidEmails(): array
    {
        return [[null], [123], [''], ['invalid'], ['a@'], [str_repeat('a', 170).'@example.com']];
    }

    public function testPasswordIsNotTrimmedAndAcceptsBoundaries(): void
    {
        $validator = new SuperAdminCredentialsValidator();
        foreach ([' password with spaces ', str_repeat('x', 12), str_repeat('x', 72), str_repeat('á', 12)] as $password) {
            self::assertSame($password, $validator->password($password));
        }
    }

    /** @dataProvider invalidPasswords */
    public function testInvalidPasswordsAreRejectedWithoutExposingTheirValue(mixed $password): void
    {
        try {
            (new SuperAdminCredentialsValidator())->password($password);
            self::fail('Expected invalid password to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('The password must contain at least 12 characters and at most 72 bytes.', $exception->getMessage());
        }
    }

    public static function invalidPasswords(): array
    {
        return [[null], [123], [''], [str_repeat(' ', 12)], ['short-pass'], [str_repeat('x', 73)], [str_repeat('á', 37)]];
    }
}
