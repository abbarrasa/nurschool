<?php

namespace Nurschool\Tests;

use Nurschool\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelNamespaceTest extends TestCase
{
    public function testKernelIsAutoloadedFromTheNurschoolNamespace(): void
    {
        self::assertTrue(class_exists(Kernel::class));
        self::assertSame('Nurschool', (new \ReflectionClass(Kernel::class))->getNamespaceName());
    }
}
