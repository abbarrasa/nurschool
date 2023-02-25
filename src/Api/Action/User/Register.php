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

namespace Nurschool\Api\Action\User;

use Nurschool\Entity\User;
use Symfony\Component\HttpKernel\Attribute\AsController;

#[AsController]
class Register
{
    public function __invoke(User $user): User
    {
        return $user;
    }
}
