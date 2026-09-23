<?php

namespace Nurschool\Tests\Unit\Mail\SendGrid\Transport;

use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;
use Nurschool\Mail\SendGrid\Transport\SendGridTransport;
use Nurschool\Mail\SendGrid\Transport\Exception\PermanentTransportException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\Exception\TransportException as HttpException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mailer\Event\FailedMessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Messenger\Exception\UnrecoverableExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class SendGridTransportTest extends TestCase
{
    private function email(array $data = ['name' => 'Ana']): DynamicTemplateEmail
    {
        return (new DynamicTemplateEmail('d-'.str_repeat('a', 32), $data))->from('school@example.com')->to('ana@example.com');
    }

    public function testPayloadAndMailerEventsWithoutLocalContent(): void
    {
        $dispatcher = new EventDispatcher();
        $events = [];
        foreach ([MessageEvent::class, SentMessageEvent::class, FailedMessageEvent::class] as $eventClass) {
            $dispatcher->addListener($eventClass, static function ($event) use (&$events): void { $events[] = $event; });
        }
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame('POST', $method);
            self::assertSame('https://api.sendgrid.com/v3/mail/send', $url);
            self::assertContains('Authorization: Bearer secret-key', $options['headers']);
            self::assertSame(0, $options['max_redirects']);
            self::assertSame([
                'from' => ['email' => 'school@example.com'],
                'template_id' => 'd-'.str_repeat('a', 32),
                'personalizations' => [[
                    'dynamic_template_data' => ['name' => 'Ana'],
                    'to' => [['email' => 'ana@example.com']],
                    'cc' => [['email' => 'cc@example.com', 'name' => 'Copy']],
                    'bcc' => [['email' => 'bcc@example.com']],
                ]],
                'reply_to' => ['email' => 'reply@example.com'],
            ], json_decode($options['body'], true, 512, JSON_THROW_ON_ERROR));
            return new MockResponse('', ['http_code' => 202, 'response_headers' => ['x-message-id: provider-id']]);
        });
        $transport = new SendGridTransport($client, 'secret-key', $dispatcher);
        $sent = $transport->send($this->email()->cc(new Address('cc@example.com', 'Copy'))->bcc('bcc@example.com')->replyTo('reply@example.com'));
        self::assertSame('provider-id', $sent->getMessageId());
        self::assertSame('sendgrid+dynamic://default', (string) $transport);
        self::assertCount(2, $events);
        self::assertInstanceOf(MessageEvent::class, $events[0]);
        self::assertInstanceOf(SentMessageEvent::class, $events[1]);
        self::assertFalse($events[0]->isQueued());
        self::assertStringContainsString('MIME-Version:', $sent->toString());
    }

    public function testEnvelopeOverridesNeverReintroduceOriginalRecipients(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $payload = json_decode($options['body'], true);
            self::assertSame(['email' => 'school@example.com'], $payload['from']);
            self::assertSame([['email' => 'sink@example.com']], $payload['personalizations'][0]['to']);
            self::assertArrayNotHasKey('cc', $payload['personalizations'][0]);
            self::assertArrayNotHasKey('bcc', $payload['personalizations'][0]);
            self::assertStringContainsString('"dynamic_template_data":{}', $options['body']);
            return new MockResponse('', ['http_code' => 202]);
        });
        (new SendGridTransport($client, 'key'))->send($this->email([])->cc('cc@example.com')->bcc('bcc@example.com'),
            new Envelope(new Address('override@example.com'), [new Address('sink@example.com')]));
    }

    public function testEnvelopeSelectingOriginalCcAndBccKeepsRecipientsPrivate(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $payload = json_decode($options['body'], true);
            self::assertSame([
                ['to' => [['email' => 'cc@example.com']], 'dynamic_template_data' => ['name' => 'Ana']],
                ['to' => [['email' => 'bcc@example.com']], 'dynamic_template_data' => ['name' => 'Ana']],
            ], $payload['personalizations']);
            self::assertStringNotContainsString('ana@example.com', $options['body']);
            return new MockResponse('', ['http_code' => 202]);
        });
        (new SendGridTransport($client, 'key'))->send($this->email()->cc('cc@example.com')->bcc('bcc@example.com'),
            new Envelope(new Address('school@example.com'), [new Address('cc@example.com'), new Address('bcc@example.com')]));
    }

    public function testBccOnlyMessagesNeverExposeOtherRecipients(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $personalizations = json_decode($options['body'], true)['personalizations'];
            self::assertCount(2, $personalizations);
            self::assertSame([['email' => 'first@example.com']], $personalizations[0]['to']);
            self::assertSame([['email' => 'second@example.com']], $personalizations[1]['to']);
            foreach ($personalizations as $personalization) {
                self::assertArrayNotHasKey('cc', $personalization);
                self::assertArrayNotHasKey('bcc', $personalization);
            }
            return new MockResponse('', ['http_code' => 202]);
        });
        (new SendGridTransport($client, 'key'))->send($this->email()->to()->bcc('first@example.com', 'second@example.com'));
    }

    public function testDuplicateRecipientsAreSentOnceWithToPriority(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $personalization = json_decode($options['body'], true)['personalizations'][0];
            self::assertSame([['email' => 'ana@example.com']], $personalization['to']);
            self::assertArrayNotHasKey('cc', $personalization);
            self::assertArrayNotHasKey('bcc', $personalization);
            return new MockResponse('', ['http_code' => 202]);
        });
        (new SendGridTransport($client, 'key'))->send($this->email()->cc('ana@example.com')->bcc('ana@example.com'));
    }

    public function testVisibleFromIsIndependentOfBounceAddress(): void
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            self::assertSame(['email' => 'school@example.com', 'name' => 'School'], json_decode($options['body'], true)['from']);
            self::assertStringNotContainsString('bounce@example.com', $options['body']);
            return new MockResponse('', ['http_code' => 202]);
        });
        $email = $this->email()->from(new Address('school@example.com', 'School'))->returnPath('bounce@example.com');
        $sent = (new SendGridTransport($client, 'key'))->send($email);
        self::assertSame('bounce@example.com', $sent->getEnvelope()->getSender()->getAddress());
    }

    /** @dataProvider responseStatuses */
    public function testFailureClassificationAndEvents(int $status, bool $permanent): void
    {
        $dispatcher = new EventDispatcher();
        $failures = [];
        $dispatcher->addListener(FailedMessageEvent::class, static function (FailedMessageEvent $event) use (&$failures): void { $failures[] = $event; });
        $client = new MockHttpClient(new MockResponse('private response body', ['http_code' => $status]));
        try {
            (new SendGridTransport($client, 'key', $dispatcher))->send($this->email());
            self::fail('The transport must fail.');
        } catch (TransportException $exception) {
            self::assertSame($permanent, $exception instanceof UnrecoverableExceptionInterface);
            self::assertStringContainsString((string) $status, $exception->getMessage());
            self::assertStringNotContainsString('private', $exception->getMessage());
            self::assertCount(1, $failures);
            self::assertSame($exception, $failures[0]->getError());
        }
    }

    public static function responseStatuses(): iterable
    {
        foreach ([400, 401, 403, 404, 302] as $status) { yield [$status, true]; }
        foreach ([408, 429, 500, 503] as $status) { yield [$status, false]; }
    }

    public function testNetworkFailureIsRetryableAndRedacted(): void
    {
        $client = new MockHttpClient(static function (): never { throw new HttpException('secret details'); });
        try {
            (new SendGridTransport($client, 'key'))->send($this->email());
            self::fail('The transport must fail.');
        } catch (TransportException $exception) {
            self::assertNotInstanceOf(UnrecoverableExceptionInterface::class, $exception);
            self::assertSame('SendGrid could not be reached.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    /** @dataProvider localFailures */
    public function testLocalFailuresDoNotCallSendGrid(string $case): void
    {
        $client = new MockHttpClient(static function (): never { self::fail('No HTTP request expected.'); });
        $email = match ($case) {
            'plain' => (new Email())->from('a@example.com')->to('b@example.com')->text('text'),
            'content' => $this->email()->html('ignored'),
            default => $this->email(),
        };
        $this->expectException(PermanentTransportException::class);
        (new SendGridTransport($client, $case === 'missing key' ? '' : 'key'))->send($email);
    }

    public static function localFailures(): iterable
    {
        foreach (['plain', 'content', 'missing key'] as $case) { yield [$case]; }
    }

    public function testRejectedMessageEventPreventsSending(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(MessageEvent::class, static function (MessageEvent $event): void { $event->reject(); });
        $client = new MockHttpClient([]);
        self::assertNull((new SendGridTransport($client, 'key', $dispatcher))->send($this->email()));
        self::assertSame(0, $client->getRequestsCount());
    }
}
