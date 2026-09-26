<?php

namespace Nurschool\Mail;

interface VerificationEmailSender
{
    public function send(string $recipient, string $token, string $locale): void;
}
