<?php

namespace Nurschool\Mail\SendGrid\Transport;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class SendGridTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, 'sendgrid+dynamic', $this->getSupportedSchemes());
        }
        if ($dsn->getHost() !== 'default' || $dsn->getPort() !== null || $dsn->getPassword() !== null) {
            throw new \InvalidArgumentException('Use sendgrid+dynamic://KEY@default for dynamic template delivery.');
        }
        return new SendGridTransport($this->client ?? HttpClient::create(), $this->getUser($dsn), $this->dispatcher, $this->logger);
    }

    /** @return list<string> */
    protected function getSupportedSchemes(): array
    {
        return ['sendgrid+dynamic'];
    }
}
