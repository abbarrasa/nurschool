<?php

namespace Nurschool\Mail\SendGrid\Template;

final class SendGridTemplateProvider
{

    /** @param array<string, array<string, string>> $templates Template families indexed by locale. */
    public function __construct(private array $templates, private string $defaultLocale)
    {
    }

    public function getTemplateId(string $locale, string $family = 'verification'): string
    {

        $shortLocale = strtolower(substr($locale, 0, 2));

        return $this->templates[$family][$shortLocale] ?? $this->templates[$family][$this->defaultLocale]
            ?? throw new \LogicException('The default email template is not configured.');
    }
}
