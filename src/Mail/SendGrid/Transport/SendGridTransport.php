<?php

namespace Nurschool\Mail\SendGrid\Transport;

use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;
use Nurschool\Mail\SendGrid\Transport\Exception\PermanentTransportException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeException;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SendGridTransport extends AbstractTransport
{
    public function __construct(
        private readonly HttpClientInterface $client,
        #[\SensitiveParameter] private readonly string $apiKey,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    public function __toString(): string
    {
        return 'sendgrid+dynamic://default';
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        try {
            return parent::send($message, $envelope);
        } catch (MimeException | \InvalidArgumentException | \JsonException) {
            // Invalid local content cannot be repaired by a retry. Do not retain sensitive data.
            throw new PermanentTransportException('The SendGrid dynamic template email is invalid.');
        }
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (!$email instanceof DynamicTemplateEmail) {
            throw new PermanentTransportException('This transport only supports dynamic template emails.');
        }
        if (trim($this->apiKey) === '') {
            throw new PermanentTransportException('SendGrid API key is not configured.');
        }
        $payload = $this->getPayload($email, $message->getEnvelope());
        try {
            $response = $this->client->request('POST', 'https://api.sendgrid.com/v3/mail/send', [
                'auth_bearer' => $this->apiKey,
                'timeout' => 10,
                'max_duration' => 30,
                'max_redirects' => 0,
                'json' => $payload,
            ]);
            $status = $response->getStatusCode();
            if ($status === 202) {
                $id = $response->getHeaders(false)['x-message-id'][0] ?? null;
                if ($id !== null) {
                    $message->setMessageId($id);
                }
            }
            $response->cancel();
        } catch (TransportExceptionInterface) {
            // Network errors are retryable; HTTP debug information may contain credentials.
            throw new TransportException('SendGrid could not be reached.');
        }
        if ($status === 202) {
            return;
        }
        if ($status === 408 || $status === 429 || $status >= 500) {
            // Do not use RecoverableExceptionInterface: it bypasses the retry limit.
            throw new TransportException(sprintf('SendGrid temporarily rejected the email (HTTP %d).', $status));
        }
        throw new PermanentTransportException(sprintf('SendGrid rejected the email (HTTP %d).', $status));
    }

    /** @return array<string, mixed> */
    private function getPayload(DynamicTemplateEmail $email, Envelope $envelope): array
    {
        $addresses = [];
        $kinds = [];
        foreach (['to' => $email->getTo(), 'cc' => $email->getCc(), 'bcc' => $email->getBcc()] as $kind => $group) {
            foreach ($group as $address) {
                $addresses[$address->getAddress()] = $address;
                $kinds[$address->getAddress()] = $kind;
            }
        }
        $personalization = ['dynamic_template_data' => (object) $email->getTemplateData()];
        // The envelope is authoritative, including development recipient overrides. Never
        // reintroduce original Cc/Bcc recipients that a Mailer listener removed from it.
        foreach ($envelope->getRecipients() as $recipient) {
            $key = $recipient->getAddress();
            $personalization[$kinds[$key] ?? 'to'][] = $this->address($addresses[$key] ?? $recipient);
        }
        if (empty($personalization['to'])) {
            throw new PermanentTransportException('SendGrid requires at least one To recipient in the envelope.');
        }
        $payload = [
            'from' => $this->address($envelope->getSender()),
            'template_id' => $email->getTemplateId(),
            'personalizations' => [$personalization],
        ];
        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = $this->address($replyTo[0]);
        }

        return $payload;
    }

    /** @return array{email: string, name?: string} */
    private function address(Address $address): array
    {
        $result = ['email' => $address->getAddress()];
        if ($address->getName() !== '') {
            $result['name'] = $address->getName();
        }
        return $result;
    }
}
