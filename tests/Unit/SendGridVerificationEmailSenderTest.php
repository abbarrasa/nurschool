<?php

namespace Nurschool\Tests\Unit;

use Nurschool\Mail\SendGrid\Template\SendGridTemplateProvider;
use Nurschool\Mail\SendGrid\SendGridVerificationEmailSender;
use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;

final class SendGridVerificationEmailSenderTest extends TestCase
{
    /** @dataProvider locales */
    public function testQueuesLocalizedVerification(string $locale, string $expectedId): void
    {
        $templates = new SendGridTemplateProvider(['es' => 'd-'.str_repeat('a', 32), 'en' => 'd-'.str_repeat('b', 32)], 'es');
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::once())->method('send')->willReturnCallback(function (DynamicTemplateEmail $message) use ($locale, $expectedId): void {
            self::assertSame($expectedId, $message->getTemplateId());
            self::assertSame('ana@example.com', $message->getTo()[0]->getAddress());
            self::assertSame('school@example.com', $message->getFrom()[0]->getAddress());
            self::assertSame(['url' => 'https://school.example/verify-account?_locale='.rawurlencode($locale).'#abc', 'ttl' => '604800'], $message->getTemplateData());
            $message->ensureValidity();
        });
        (new SendGridVerificationEmailSender($mailer, $templates, 'https://school.example/', 'school@example.com', '604800'))->send('ana@example.com', 'abc', $locale);
    }

    public static function locales(): iterable
    {
        yield ['es', 'd-'.str_repeat('a', 32)];
        yield ['en', 'd-'.str_repeat('b', 32)];
        yield ['EN_us', 'd-'.str_repeat('b', 32)];
        yield ['fr', 'd-'.str_repeat('a', 32)];
    }

    public function testMissingDefaultTemplateFailsExplicitly(): void
    {
        $this->expectException(\LogicException::class);
        (new SendGridTemplateProvider([], 'es'))->getTemplateId('fr');
    }
}
