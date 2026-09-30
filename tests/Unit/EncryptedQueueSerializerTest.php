<?php

namespace Nurschool\Tests\Unit;

use Nurschool\Messenger\{EncryptedQueueSerializer, PayloadCipher};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\Stamp\{DelayStamp, RedeliveryStamp};
use Symfony\Component\Messenger\Transport\Serialization\{PhpSerializer, SerializerInterface};
use Symfony\Component\Mime\Email;

final class EncryptedQueueSerializerTest extends TestCase
{
    private function cipher(): PayloadCipher
    {
        return new PayloadCipher(base64_encode(str_repeat('k', 32)));
    }

    public function testRoundTripProtectsEntireEnvelopeAndPreservesRetryStamps(): void
    {
        $message = new SendEmailMessage((new Email())->from('school@example.com')->to('ana@example.com')->text('live-token'));
        $envelope = new Envelope($message, [new RedeliveryStamp(2), new DelayStamp(1000)]);
        $serializer = new EncryptedQueueSerializer($this->cipher(), new PhpSerializer());
        $encoded = $serializer->encode($envelope);
        self::assertSame([], $encoded['headers']);
        self::assertStringNotContainsString('live-token', $encoded['body']);
        self::assertStringNotContainsString('ana@example.com', $encoded['body']);
        $decoded = $serializer->decode($encoded);
        self::assertEquals($message, $decoded->getMessage());
        self::assertSame(2, RedeliveryStamp::getRetryCountFromEnvelope($decoded));
        self::assertEquals($envelope->last(DelayStamp::class), $decoded->last(DelayStamp::class));
    }

    public function testInnerHeadersAreEncryptedAndRestoredRatherThanStoredInCleartext(): void
    {
        $inner = $this->createMock(SerializerInterface::class);
        $payload = ['body' => 'body-secret', 'headers' => ['X-Secret' => 'header-secret']];
        $envelope = new Envelope(new \stdClass());
        $inner->expects(self::once())->method('encode')->with($envelope)->willReturn($payload);
        $inner->expects(self::once())->method('decode')->with($payload)->willReturn($envelope);
        $serializer = new EncryptedQueueSerializer($this->cipher(), $inner);
        $encoded = $serializer->encode($envelope);
        self::assertSame([], $encoded['headers']);
        self::assertStringNotContainsString('header-secret', $encoded['body']);
        self::assertSame($envelope, $serializer->decode($encoded));
    }

    public function testPlaintextIsRejectedBeforeInnerDeserializerCanInstantiateObjects(): void
    {
        $inner = $this->createMock(SerializerInterface::class);
        $inner->expects(self::never())->method('decode');
        $this->expectException(MessageDecodingFailedException::class);
        (new EncryptedQueueSerializer($this->cipher(), $inner))->decode(['body' => 'plaintext']);
    }

    /** @dataProvider malformedEnvelopes */
    public function testAuthenticatedButInvalidEnvelopeIsRejected(string $payload): void
    {
        $cipher = $this->cipher();
        $inner = $this->createMock(SerializerInterface::class);
        $inner->expects(self::never())->method('decode');
        $this->expectException(MessageDecodingFailedException::class);
        (new EncryptedQueueSerializer($cipher, $inner))->decode(['body' => $cipher->encrypt($payload)]);
    }

    public static function malformedEnvelopes(): iterable
    {
        yield ['not JSON'];
        yield ['null'];
        yield ['{}'];
        yield ['{"body":3}'];
        yield ['{"body":"body","headers":"bad"}'];
        yield ['{"body":"body","headers":{"secret":3}}'];
    }
}
