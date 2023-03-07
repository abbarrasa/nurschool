<?php

/*
 * This file is part of the Nurschool project.
 *
 * (c) Nurschool <https://github.com/abbarrasa/nurschool>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Nurschool\Mailer\Sendgrid\Provider;

use Nurschool\Mailer\Sendgrid\Event\SendgridFailedEvent;
use Nurschool\Mailer\Sendgrid\Event\SendgridFinishedEvent;
use Nurschool\Mailer\Sendgrid\Event\SendgridStartedEvent;
use Nurschool\Mailer\Sendgrid\Exception\AccessDeniedSendgridException;
use Nurschool\Mailer\Sendgrid\Exception\BadRequestSendgridException;
use Nurschool\Mailer\Sendgrid\Exception\SendgridException;
use Nurschool\Mailer\Sendgrid\Exception\UnauthorizedSendgridException;
use SendGrid;
use SendGrid\Mail\Mail;
use SendGrid\Mail\MailSettings;
use SendGrid\Mail\SandBoxMode;
use SendGrid\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SendgridProvider
{
    private SendGrid $apiClient;
    private EventDispatcherInterface  $eventDispatcher;
    private bool $disableDelivery;
    private bool $sandbox;

    public function __construct(
        SendGrid $apiClient,
        EventDispatcherInterface $eventDispatcher,
        bool $disableDelivery = true,
        bool $sandbox = true
    ) {
        $this->apiClient = $apiClient;
        $this->eventDispatcher = $eventDispatcher;
        $this->disableDelivery = $disableDelivery;
        $this->sandbox = $sandbox;
    }

    public function createMail($to, $from, string $subject, string $content, bool $isHtml = true): Mail
    {
        $email = new Mail();
        if ($this->sandbox) {
            $sandboxMode  = new SandBoxMode();
            $sandboxMode->setEnable($this->sandbox);
            $mailSettings = new MailSettings();
            $mailSettings->setSandboxMode($sandboxMode);
            $email->setMailSettings($mailSettings);    
        }

        if (is_array($from)) {
            list($fromEmail, $senderName) = $from;
            $email->setFrom($fromEmail, $senderName);
        } else {
            $email->setFrom($from);
        }

        if (is_array($to)) {
            $email->addTos($to);
        } else {
            $email->addTo($to);
        }

        $email->setSubject($subject);
        $email->addContent($isHtml ? 'text/html' : 'text/plain', $content);

        return $email;
    }

    /**
     * Creates an email with SendGrid REST API
     * @param string|array $to
     * @param string|array $from
     * @throws \SendGrid\Mail\TypeException
     */
    public function createMailForTransactionalTemplate($to, $from, string $subject, string $templateId, array $data = []): Mail
    {
        $email = new Mail();
        if ($this->sandbox) {
            $sandboxMode  = new SandBoxMode();
            $sandboxMode->setEnable($this->sandbox);
            $mailSettings = new MailSettings();
            $mailSettings->setSandboxMode($sandboxMode);
            $email->setMailSettings($mailSettings);    
        }

        if (is_array($from)) {
            list($fromEmail, $senderName) = $from;
            $email->setFrom($fromEmail, $senderName);
        } else {
            $email->setFrom($from);
        }

        if (is_array($to)) {
            $email->addTos($to);
        } else {
            $email->addTo($to);
        }

        $email->setSubject($subject);
        $email->setTemplateId($templateId);

        foreach($data as $key => $value) {
            $email->addDynamicTemplateData($key, $value);
        }

        return $email;
    }

    /**
     * Sends an email with SendGrid REST API
     */
    public function sendMail(Mail $email): ?string
    {
        $this->eventDispatcher->dispatch(new SendgridStartedEvent($email));
        if ($this->disableDelivery) {
            $this->eventDispatcher->dispatch(new SendgridFinishedEvent($email));
            return null;
        }

        try {
            $response = $this->apiClient->send($email);

            $this->checkResponse($response);

            $messageId = $this->getMessageId($response);

            $this->eventDispatcher->dispatch(new SendgridFinishedEvent($email, $messageId));

            return $messageId;

        } catch (\Exception $exception) {
            $this->eventDispatcher->dispatch(
                new SendgridFailedEvent($email, $exception->getMessage())
            );
        }
    }

    /**
     * @throws SendGridException
     */
    private function getMessageId(Response $response): string
    {
        try {
            return $response->headers(true)['X-Message-Id'];
        } catch (\Exception $e) {
            throw new SendgridException('X-Message-Id header not found in SendGrid API response');
        }
    }

    /**
     * @throws SendGridException
     */
    private function checkResponse(Response $response): void
    {
        if ($response->statusCode() == 401) {
            throw new UnauthorizedSendgridException($response->body());
        }

        if ($response->statusCode() == 403) {
            throw new AccessDeniedSendgridException($response->body());
        }

        if (preg_match('/5[0-9]{2}/', strval($response->statusCode()))) {
            throw new SendgridException($response->body());
        }

        if (preg_match('/4[0-9]{2}/', strval($response->statusCode()))) {
            throw new BadRequestSendgridException($response->body());
        }
    }
}