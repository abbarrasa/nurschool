<?php

namespace Nurschool\Tests\Unit;

use Nurschool\Mail\SendGrid\{Message\DynamicTemplateEmail, SendGridPasswordResetEmailSender, Template\SendGridTemplateProvider};
use Nurschool\PasswordReset\PasswordResetPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

final class SendGridPasswordResetEmailSenderTest extends TestCase
{
    /** @dataProvider locales */
    public function testQueuesResetTemplateWithTrustedOriginAndConfiguredLifetime(string $locale, int $ttl): void
    {
        $id = 'd-94364cdab2c64321b691171c8d4bf420';
        $templates = new SendGridTemplateProvider(['password_reset' => ['es' => $id, 'en' => $id]], 'es');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(function (DynamicTemplateEmail $email) use ($locale, $id, $ttl): void {
            self::assertSame($id, $email->getTemplateId());
            self::assertSame(['url' => 'https://school.example/reset-password?_locale='.rawurlencode($locale).'#secret', 'ttl' => $ttl, 'locale' => $locale], $email->getTemplateData());
            self::assertSame('ana@example.com', $email->getTo()[0]->getAddress());
            $email->ensureValidity();
        });
        (new SendGridPasswordResetEmailSender($mailer, new PasswordResetPolicy($ttl), $templates, 'https://school.example/', 'school@example.com'))->send('ana@example.com', 'secret', $locale);
    }

    public static function locales(): iterable
    {
        yield ['es', 3600];
        yield ['en', 900];
        yield ['fr', 7200];
    }
}
