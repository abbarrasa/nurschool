<?php

namespace Nurschool\Mail;

interface PasswordResetEmailSender
{
    public function send(string $recipient, string $token, string $locale): void;
}
