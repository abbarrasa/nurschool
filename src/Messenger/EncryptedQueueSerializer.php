<?php

namespace Nurschool\Messenger;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

/** Encrypt both message bytes and serialized headers, including retry/failure metadata. */
final readonly class EncryptedQueueSerializer implements SerializerInterface
{
    public function __construct(
        private PayloadCipher $cipher,
        #[Autowire(service: 'messenger.transport.native_php_serializer')] private SerializerInterface $inner,
    ) {
    }

    public function encode(Envelope $envelope): array
    {
        $payload = json_encode($this->inner->encode($envelope), JSON_THROW_ON_ERROR);

        return ['body' => $this->cipher->encrypt($payload), 'headers' => []];
    }

    public function decode(array $encodedEnvelope): Envelope
    {
        try {
            $payload = json_decode($this->cipher->decrypt($encodedEnvelope['body']), true, flags: JSON_THROW_ON_ERROR);
        } catch (\UnexpectedValueException | \JsonException) {
            // Do not attach the original exception: its arguments may contain decoded bytes.
            throw new MessageDecodingFailedException('The encrypted queue message could not be authenticated or decoded.');
        }
        if (!is_array($payload) || !is_string($payload['body'] ?? null)
            || (isset($payload['headers']) && !is_array($payload['headers']))) {
            throw new MessageDecodingFailedException('The encrypted queue message has an invalid structure.');
        }
        $headers = $payload['headers'] ?? [];
        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new MessageDecodingFailedException('The encrypted queue message has invalid headers.');
            }
        }

        return $this->inner->decode(['body' => $payload['body'], 'headers' => $headers]);
    }
}
