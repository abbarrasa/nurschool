<?php

namespace Nurschool\Mail\SendGrid\Template;

final class SendGridTemplateProvider
{

    /** @param array<string, array<string, string>> $templates Template families indexed by locale. */
    public function __construct(private array $templates, private string $defaultLocale)
    {
    }

    public function getTemplateId(string $locale): string
    {

        $shortLocale = strtolower(substr($locale, 0, 2));

        return $this->templates['verification'][$shortLocale] ?? $this->templates['verification'][$this->defaultLocale]
            ?? throw new \LogicException('The default verification template is not configured.');
    }
}
