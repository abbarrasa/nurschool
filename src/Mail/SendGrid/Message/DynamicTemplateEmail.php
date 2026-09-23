<?php

namespace Nurschool\Mail\SendGrid\Message;

use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\LogicException;
use Symfony\Component\Mime\Part\TextPart;

final class DynamicTemplateEmail extends Email
{
    private const TEMPLATE_HEADER = 'X-SendGrid-Template-Id';
    private const DATA_HEADER = 'X-SendGrid-Template-Data';

    /** @param array<array-key, mixed> $templateData JSON-compatible Handlebars variables. */
    public function __construct(string $templateId, array $templateData = [])
    {
        // An empty MIME representation preserves Symfony validation and profiler rendering.
        // SendGrid renders the actual email; the transport never sends this MIME body.
        parent::__construct(body: new TextPart(''));
        // Headers are already serialized by Email. Avoid overriding its internal serialization
        // methods or losing subclass properties when Messenger persists the message.
        $this->getHeaders()->addTextHeader(self::TEMPLATE_HEADER, $templateId);
        $this->getHeaders()->addTextHeader(self::DATA_HEADER, json_encode((object) $templateData, JSON_THROW_ON_ERROR));
        $this->validateTemplate();
    }

    public function getTemplateId(): string
    {
        return $this->headerValue(self::TEMPLATE_HEADER);
    }

    /** @return array<string, mixed> */
    public function getTemplateData(): array
    {
        $data = json_decode($this->headerValue(self::DATA_HEADER), false, 512, JSON_THROW_ON_ERROR);
        if (!$data instanceof \stdClass) {
            throw new \InvalidArgumentException('Template data must be a JSON object.');
        }
        $variables = [];
        foreach (get_object_vars($data) as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Template variables must have string keys.');
            }
            $variables[$key] = $value;
        }
        return $variables;
    }

    public function ensureValidity(): void
    {
        $this->validateTemplate();
        parent::ensureValidity();
        if ($this->getSubject() !== null || $this->getTextBody() !== null || $this->getHtmlBody() !== null
            || $this->getAttachments() !== [] || $this->getBody()->bodyToString() !== '') {
            throw new LogicException('Dynamic template emails must define their subject and content in SendGrid; local content and attachments are not supported.');
        }
        if (count($this->getFrom()) !== 1 || count($this->getReplyTo()) > 1) {
            throw new LogicException('Dynamic template emails require one From address and at most one Reply-To address.');
        }
    }

    private function headerValue(string $name): string
    {
        $value = $this->getHeaders()->getHeaderBody($name);
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Required SendGrid template metadata is missing.');
        }
        return $value;
    }

    private function validateTemplate(): void
    {
        if (!preg_match('/^d-[a-f0-9]{32}$/D', $this->getTemplateId())) {
            throw new \InvalidArgumentException('A valid SendGrid dynamic template ID is required.');
        }
        $this->getTemplateData();
    }
}
