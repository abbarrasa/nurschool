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

namespace Nurschool\Service\UrlSigner\Exception;

use Exception;

class InvalidSignature extends Exception
{
    public static function invalidLink(): self
    {
        return new self('The link is invalid. Please request a new link.');
    }

    public static function isExpired(): self
    {
        return new self('The link has expired. Please request a new link.');
    }
}
