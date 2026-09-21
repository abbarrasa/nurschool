<?php

namespace Nurschool\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TranslationCatalogTest extends KernelTestCase
{
    public function testEveryEnabledLocaleHasTheSameNonEmptyCatalogAndLoadsThroughSymfony(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $translator = $container->get(TranslatorInterface::class);
        $default = $container->getParameter('kernel.default_locale');
        $path = $container->getParameter('kernel.project_dir').'/translations/messages.';
        $reference = json_decode(file_get_contents($path.$default.'.json'), true, flags: JSON_THROW_ON_ERROR);
        foreach ($container->getParameter('kernel.enabled_locales') as $locale) {
            $catalog = json_decode(file_get_contents($path.$locale.'.json'), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(array_keys($reference), array_keys($catalog), 'Incomplete catalog: '.$locale);
            foreach ($catalog as $key => $value) {
                self::assertNotSame('', trim($value));
                self::assertSame($value, $translator->trans($key, locale: $locale));
            }
            self::assertArrayHasKey('locale.'.$locale, $catalog);
        }
    }
}
