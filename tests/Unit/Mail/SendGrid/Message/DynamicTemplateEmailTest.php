<?php

namespace Nurschool\Tests\Unit\Mail\SendGrid\Message;

use Nurschool\Mail\SendGrid\Message\DynamicTemplateEmail;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Part\TextPart;

final class DynamicTemplateEmailTest extends TestCase
{
    private function email(): DynamicTemplateEmail
    {
        return (new DynamicTemplateEmail('d-'.str_repeat('a', 32), ['name' => 'Ana García', 'items' => [['title' => 'Course']], 'options' => new \stdClass(), 'long' => str_repeat('text ', 200)]))
            ->from('school@example.com')->to('ana@example.com');
    }

    public function testBodylessEmailSurvivesSerializationAndMimeRendering(): void
    {
        $original = $this->email();
        $email = unserialize(serialize($original));
        self::assertInstanceOf(DynamicTemplateEmail::class, $email);
        self::assertSame($original->getTemplateId(), $email->getTemplateId());
        self::assertEquals($original->getTemplateData(), $email->getTemplateData());
        self::assertInstanceOf(\stdClass::class, $email->getTemplateData()['options']);
        self::assertSame('ana@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('school@example.com', $email->getFrom()[0]->getAddress());
        self::assertNull($email->getTextBody());
        self::assertNull($email->getHtmlBody());
        self::assertNull($email->getSubject());
        $email->ensureValidity();
        self::assertStringContainsString('MIME-Version:', (new SentMessage($email, Envelope::create($email)))->toString());
        self::assertStringNotContainsString('template_id', $email->toString());
    }

    /** @dataProvider invalidMetadata */
    public function testModifiedMetadataIsRevalidated(string $header, ?string $value): void
    {
        $email = $this->email();
        $email->getHeaders()->remove($header);
        if ($value !== null) {
            $email->getHeaders()->addTextHeader($header, $value);
        }
        $this->expectException(\Exception::class);
        $email->ensureValidity();
    }

    public static function invalidMetadata(): iterable
    {
        yield ['X-SendGrid-Template-Id', null];
        yield ['X-SendGrid-Template-Data', '{'];
        yield ['X-SendGrid-Template-Data', 'null'];
        yield ['X-SendGrid-Template-Data', '[]'];
    }

    /** @dataProvider invalidTemplates */
    public function testInvalidTemplateDataIsRejected(string $id, array $data): void
    {
        $this->expectException(\Exception::class);
        new DynamicTemplateEmail($id, $data);
    }

    public static function invalidTemplates(): iterable
    {
        yield ['', []];
        yield ['legacy-template', []];
        yield ['d-'.str_repeat('a', 32), ['value' => INF]];
        yield ['d-'.str_repeat('a', 32), ['numeric key']];
    }

    /** @dataProvider invalidEmails */
    public function testMimeValidationRemainsActive(string $case): void
    {
        $email = $this->email();
        match ($case) {
            'missing recipient' => $email->to(),
            'missing sender' => $email->getHeaders()->remove('From'),
            'draft' => $email->getHeaders()->addTextHeader('X-Unsent', '1'),
            'text' => $email->text('ignored content'),
            'html' => $email->html('<p>ignored content</p>'),
            'subject' => $email->subject('ignored subject'),
            'attachment' => $email->attach('data', 'file.txt'),
            'body' => $email->setBody(new TextPart('ignored body')),
            'multiple from' => $email->addFrom('other@example.com'),
            'multiple reply-to' => $email->replyTo('one@example.com', 'two@example.com'),
        };
        $this->expectException(LogicException::class);
        $email->ensureValidity();
    }

    public static function invalidEmails(): iterable
    {
        foreach (['missing recipient', 'missing sender', 'draft', 'text', 'html', 'subject', 'attachment', 'body', 'multiple from', 'multiple reply-to'] as $case) {
            yield [$case];
        }
    }
}
