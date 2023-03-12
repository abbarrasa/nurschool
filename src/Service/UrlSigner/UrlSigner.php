<?php

/*
 * This file is part of the Nurschool project.
 *
 * (c) Nurschool <https://github.com/abbarrasa/nurschool>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Nurschool\Service\UrlSigner;

use Symfony\Component\HttpFoundation\Request;

interface UrlSigner
{
    public function sign(string $url, \DatetimeInterface|int $expiration = null): Signature;

    public function signRoute(
        string $route,
        array $params = [],
        \DatetimeInterface|int $expiration = null
    ): Signature;

    public function check(string $url): void;

    public function checkRequest(Request $request): void;
}