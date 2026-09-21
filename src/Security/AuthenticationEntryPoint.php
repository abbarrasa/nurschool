<?php

namespace Nurschool\Security;

use Symfony\Component\HttpFoundation\{JsonResponse, RedirectResponse, Request, Response};
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class AuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private UrlGeneratorInterface $urls, private TranslatorInterface $translator)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse(['message' => $this->translator->trans('error.401', locale: $request->getLocale())], 401, ['Cache-Control' => 'no-store']);
        }

        return new RedirectResponse($this->urls->generate('nurschool_login'));
    }
}
