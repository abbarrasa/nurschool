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

class InvalidExpiration extends Exception
{
    public static function isInPast(): self
    {
        return new self('Expiration date must be in the future');
    }

    public static function wrongType(): self
    {
        return new self('Expiration date must be an instance of DateTimeInterface or an integer');
    }
}
