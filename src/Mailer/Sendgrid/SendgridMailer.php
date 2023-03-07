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

namespace Nurschool\Mailer\Sendgrid;

use Nurschool\Entity\User;
use Nurschool\Mailer\MailerInterface;
use Nurschool\Mailer\Sendgrid\Exception\SendgridException;
use Nurschool\Mailer\Sendgrid\Provider\SendgridProvider;

final class SendgridMailer implements MailerInterface
{
    private SendgridProvider $provider;
    private array $configuration;

    public function __construct(SendgridProvider $provider, array $configuration = array())
    {
        $this->provider = $provider;
        $this->configuration = $configuration;
    }

    public function sendAccountActivationMail(User $user): void
    {
        $email = $user->getEmail();
        $firstname = $user->getFirstname();
        $lastname = $user->getLastname();
        $templateId = $this->getTemplateIdFor(__FUNCTION__);
        $senderAddress = $this->getSenderAddressFor(__FUNCTION__);
        $senderName = $this->getSenderNameFor(__FUNCTION__);
        $subject = $this->getSubjectFor(__FUNCTION__);

        $url = '';

        $email = $this->provider->createMailForTransactionalTemplate(
            [$email => \sprintf("%s %s", $firstname, $lastname)],
            [$senderAddress, $senderName],
            $subject,
            $templateId,
            [
                'name' => $firstname,
                'url' => $url,
                'expiresAt' => ''
            ]
        );

        $this->provider->sendMail($email);
    }

    private function getTemplateIdFor(string $name): string
    {
        $templateId = $this->configuration[$name]['template'] ?? null;
        if (empty($templateId)) {
            throw new SendgridException(sprintf("No template defined for '%s'", $name));
        }

        return $templateId;
    }

    private function getSenderAddressFor(string $name): string
    {
        $address = $this->configuration[$name]['sender']['address'] ?? null;
        if (empty($address)) {
            throw new SendgridException(sprintf("No sender address defined for '%s'", $name));
        }

        return $address;
    }

    private function getSenderNameFor(string $name): string
    {
        return $this->configuration[$name]['sender']['name'] ?? '';
    }

    private function getSubjectFor(string $name): string
    {
        return $this->configuration[$name]['subject'] ?? '';
    }    
}