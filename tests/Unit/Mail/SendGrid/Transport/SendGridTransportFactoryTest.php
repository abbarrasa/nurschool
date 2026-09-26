<?php

namespace Nurschool\Tests\Unit\Mail\SendGrid\Transport;

use Nurschool\Mail\SendGrid\Transport\SendGridTransportFactory;
use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Transport\Dsn;

final class SendGridTransportFactoryTest extends TestCase
{
    public function testFactoryRecognizesSchemeAndDecodesCredentials(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertContains('Authorization: Bearer key+value', $options['headers']);
            return new MockResponse('', ['http_code' => 202]);
        });
        $factory = new SendGridTransportFactory(client: $client);
        self::assertTrue($factory->supports(Dsn::fromString('sendgrid+dynamic://key%2Bvalue@default')));
        self::assertFalse($factory->supports(Dsn::fromString('smtp://localhost')));
        $factory->create(Dsn::fromString('sendgrid+dynamic://key%2Bvalue@default'))->send(
            (new DynamicTemplateEmail('d-'.str_repeat('a', 32)))->from('a@example.com')->to('b@example.com'),
        );
        self::assertSame(1, $client->getRequestsCount());
    }

    /** @dataProvider invalidDsns */
    public function testInvalidConfigurationIsRejected(string $dsn): void
    {
        $this->expectException(\Exception::class);
        (new SendGridTransportFactory(client: new MockHttpClient([])))->create(Dsn::fromString($dsn));
    }

    public static function invalidDsns(): iterable
    {
        yield ['smtp://localhost'];
        yield ['sendgrid+dynamic://default'];
        yield ['sendgrid+dynamic://key@other-host'];
        yield ['sendgrid+dynamic://key@default:443'];
        yield ['sendgrid+dynamic://key:password@default'];
    }
}
