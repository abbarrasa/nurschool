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

namespace Nurschool\Controller\Api;

use Nurschool\Service\User\UserRegisterService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class Register
{
    private UserRegisterService $userRegisterService;

    public function __construct(UserRegisterService $userRegisterService)
    {
        $this->userRegisterService = $userRegisterService;
    }

    public function __invoke(Request $request)
    {
        $email = $this->getRequestParameter($request, 'email');  
        $firstname = $this->getRequestParameter($request, 'firstname');  
        $lastname = $this->getRequestParameter($request, 'lastname');  

        return $this->userRegisterService->registerUserFromEmail($email, $firstname, $lastname);
    }

    /**
     * getResquestParameter.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function getRequestParameter(Request $request, string $parameter, bool $isRequired = true, $default = null)
    {
        $parameters = \json_decode($request->getContent(), true);
        $value = $parameters[$parameter] ?? null;

        if ($isRequired && empty($value)) {
            throw new BadRequestHttpException(\sprintf('"%s" is required', $parameter));
        }

        return $value ?? $default;
    }

}