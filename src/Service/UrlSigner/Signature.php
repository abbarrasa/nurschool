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

final class Signature
{
    private string $signedUrl;
    private ?DateTimeInterface $expiresAt;

    public function __construct(string $signedUrl, ?DateTimeInterface $expiresAt = null)
    {
        $this->signedUrl = $signedUrl;
        $this->expiresAt = $expiresAt;
    }

    /**
     * Returns the full signed URL that should be sent to the user.
     */
    public function getSignedUrl(): string
    {
        return $this->signedUrl;
    }

    /**
     * Gets the length of time a signature is valid for.
     */
    public function expiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    /**
     * Checks if signature has expired.
     */
    public function isExpired(): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        return $this->expiresAt->getTimestamp() <= time();
    }
}