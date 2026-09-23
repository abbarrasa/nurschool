<?php

namespace Nurschool\Mail\SendGrid\Template;

final class SendGridTemplateProvider
{

    public function __construct(private array $templates, private string $defaultLocale)
    {
    }

    public function getTemplateId(string $locale): string
    {

        $shortLocale = strtolower(substr($locale, 0, 2));

        return $this->templates['verification'][$shortLocale] ?? $this->templates['verification'][$this->defaultLocale];
    }
}