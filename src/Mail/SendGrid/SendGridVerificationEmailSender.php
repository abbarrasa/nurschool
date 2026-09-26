<?php

namespace Nurschool\Mail\SendGrid;

use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;
use Nurschool\Mail\SendGrid\Template\SendGridTemplateProvider;
use Nurschool\Mail\VerificationEmailSender;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

final readonly class SendGridVerificationEmailSender implements VerificationEmailSender
{
    public function __construct(
        private MailerInterface $mailer,
        private SendGridTemplateProvider $templates,
        #[Autowire('%env(REGISTRATION_BASE_URL)%')] private string $baseUrl,
        #[Autowire('%env(MAILER_FROM)%')] private string $sender,
        #[Autowire('%env(REGISTRATION_TTL)%')] private string $ttl,
    ) {
    }

    public function send(string $recipient, string $token, string $locale): void
    {
        // The configured origin prevents untrusted Host headers from entering verification emails.
        $url = rtrim($this->baseUrl, '/').'/verify-account?_locale='.rawurlencode($locale).'#'.$token;
        $email = (new DynamicTemplateEmail(
            $this->templates->getTemplateId($locale),
            ['url' => $url, 'ttl' => $this->ttl],
        ))->from($this->sender)->to($recipient);
        $email->ensureValidity();
        $this->mailer->send($email);
    }
}
