<?php

namespace Nurschool\Messenger;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Authenticate queued bytes before any PHP objects can be deserialized. */
final readonly class PayloadCipher
{
    private const PREFIX = 'v1:';
    private const ALGORITHM = 'aes-256-gcm';
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;
    private const CONTEXT = 'nurschool.messenger.payload.v1';

    private string $key;

    public function __construct(
        #[Autowire('%env(QUEUE_ENCRYPTION_KEY)%')] #[\SensitiveParameter] string $encodedKey,
    ) {
        $key = base64_decode($encodedKey, true);
        if ($key === false || strlen($key) !== 32) {
            throw new \InvalidArgumentException('QUEUE_ENCRYPTION_KEY must contain a base64-encoded 32-byte key.');
        }
        $this->key = $key;
    }

    public function encrypt(#[\SensitiveParameter] string $payload): string
    {
        $nonce = random_bytes(self::NONCE_LENGTH);
        $tag = '';
        $ciphertext = openssl_encrypt($payload, self::ALGORITHM, $this->key, OPENSSL_RAW_DATA, $nonce, $tag, self::CONTEXT, self::TAG_LENGTH);
        if ($ciphertext === false) {
            throw new \RuntimeException('Queued payload encryption failed.');
        }

        return self::PREFIX.base64_encode($nonce.$tag.$ciphertext);
    }

    public function decrypt(#[\SensitiveParameter] string $payload): string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            throw new \UnexpectedValueException('Invalid encrypted queue payload.');
        }
        $bytes = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($bytes === false || strlen($bytes) < self::NONCE_LENGTH + self::TAG_LENGTH) {
            throw new \UnexpectedValueException('Invalid encrypted queue payload.');
        }
        $plaintext = openssl_decrypt(
            substr($bytes, self::NONCE_LENGTH + self::TAG_LENGTH), self::ALGORITHM, $this->key,
            OPENSSL_RAW_DATA, substr($bytes, 0, self::NONCE_LENGTH),
            substr($bytes, self::NONCE_LENGTH, self::TAG_LENGTH), self::CONTEXT,
        );
        if ($plaintext === false) {
            throw new \UnexpectedValueException('Invalid encrypted queue payload.');
        }

        return $plaintext;
    }
}
