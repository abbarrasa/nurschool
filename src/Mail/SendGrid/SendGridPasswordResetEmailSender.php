<?php

namespace Nurschool\Mail\SendGrid;

use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;
use Nurschool\Mail\SendGrid\Template\SendGridTemplateProvider;
use Nurschool\Mail\PasswordResetEmailSender;
use Nurschool\PasswordReset\PasswordResetPolicy;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

final readonly class SendGridPasswordResetEmailSender implements PasswordResetEmailSender
{
    public function __construct(
        private MailerInterface $mailer,
        private PasswordResetPolicy $policy,
        private SendGridTemplateProvider $templates,
        #[Autowire('%env(PASSWORD_RESET_BASE_URL)%')] private string $baseUrl,
        #[Autowire('%env(MAILER_FROM)%')] private string $sender,
    ) {
    }

    public function send(string $recipient, string $token, string $locale): void
    {
        // The configured origin prevents untrusted Host headers from entering password reset emails.
        $url = rtrim($this->baseUrl, '/').'/reset-password?_locale='.rawurlencode($locale).'#'.$token;
        $email = (new DynamicTemplateEmail(
            $this->templates->getTemplateId($locale, 'password_reset'),
            ['url' => $url, 'ttl' => $this->policy->ttl, 'locale' => $locale],
        ))->from($this->sender)->to($recipient);
        $email->ensureValidity();
        $this->mailer->send($email);
    }
}
