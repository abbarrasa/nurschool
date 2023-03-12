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

use DateTimeInterface;
use Nurschool\Service\UrlSigner\Exception\InvalidExpiration;
use Nurschool\Service\UrlSigner\Exception\InvalidSignature;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Sha256UrlSigner implements UrlSigner
{
    private string $secret;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(string $secret, UrlGeneratorInterface $urlGenerator)
    {
        $this->secret = $secret;
        $this->urlGenerator = $urlGenerator;
    }

    public function sign(string $url, \DatetimeInterface|int $expiration = null): Signature
    {
        $parsedUrl = \parse_url($url);        
        if (null !== $expiration) {
            $expires = $this->getExpiration($expiration);
            if (isset($parsedUrl['query'])) {
                $parsedUrl['query'] .= "&expires=$expires";
            } else {
                $parsedUrl['query'] = "expires=$expires";
            }
        }

        $signer = new UriSigner($this->secret, 'signature');
        
        return new Signature($signer->sign($this->buildUrl($parsedUrl)), $expires ?? null);
    }

    public function signRoute(
        string $route,
        array $params = [],
        \DatetimeInterface|int $expiration = null
    ): Signature
    {
        if (null !== $expiration) {
            $expires = $this->getExpiration($expiration);
            $params['expires'] = $expires->getTimestamp();
        }

        $signer = new UriSigner($this->secret, 'signature');
        $url = $this->urlGenerator->generate(
            $route, $params, UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new Signature($signer->sign($url), $expires ?? null);
    }

    public function check(string $url): void
    {
        $signer = new UriSigner($this->secret, 'signature');
        if (!$signer->check($url)) {
            throw InvalidSignature::invalidLink();
        }

        $queryParams = $this->getQueryParams($url);
        $expires = $queryParams['expires'] ?? null; 
        $expiresAt = !empty($expires) ? \DateTimeImmutable::createFromFormat('U', $expires) : null;
        $signature = new Signature($url, $expiresAt);
        if ($signature->isExpired()) {
            throw InvalidSignature::isExpired();
        }

    }

    public function checkRequest(Request $request): void
    {
        $this->check($request->getUri());
    }

    private function getExpiration(DateTimeInterface|int $expiration): DateTimeInterface
    {
        if (is_int($expiration)) {
            $expiration = (new \DateTime())->modify("+{$expiration} seconds");
        }

        if (!$expiration instanceof DateTimeInterface) {
            throw InvalidExpiration::wrongType();
        }

        if ($expiration->getTimestamp() < (new \DateTime())->getTimestamp()) {
            throw InvalidExpiration::isInPast();
        }

        return $expiration;
    }
    
    private function buildUrl(array $parts)
    {
        return \sprintf('%s://%s%s%s%s%s',
            $parts['scheme'],
            $parts['host'],
            $parts['port'] ? ":{$parts['port']}" : '', 
            $parts['path'] ?? '',
            $parts['query'] ? "?{$parts['query']}" : '',
            $parts['fragment'] ? "#{$parts['fragment']}" : ''
        );
    }
    
    private function getQueryParams(string $url): array
    {
        $params = [];
        $parsedUrl = \parse_url($url);

        if (isset($parsedUrl['query'])) {
            \parse_str($parsedUrl['query'] ?? '', $params);
        }

        return $params;
    }    
}