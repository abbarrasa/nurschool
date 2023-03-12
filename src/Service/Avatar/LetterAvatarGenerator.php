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

namespace Nurschool\Service\Avatar;

use Nurschool\Entity\User;
use Nurschool\Service\Avatar\Avatar;
use Nurschool\Service\Avatar\AvatarGeneratorInterface;
use Nurschool\Service\Avatar\Exception\AvatarNotSaved;
use Nurschool\Storage\FileManagerInterface;
use YoHang88\LetterAvatar\LetterAvatar;

final class LetterAvatarGenerator implements AvatarGeneratorInterface
{
    private const DIR_AVATAR = '/uploads/avatar/';

    private FileManagerInterface $fileManager;

    public function __construct(FileManagerInterface $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    public function generateUserAvatar(User $user, array $options = []): Avatar
    {
        // Square shape, size 64px
        $name = \sprintf("%s %s", $user->getFirstname(), $user->getLastname());
        $avatar = new LetterAvatar($name, 'square', 64);

        $tmpFilename = \tempnam(\sys_get_temp_dir(), 'avatar_');

        // Save image as JPEG
        if (!$avatar->saveAs($tmpFilename, LetterAvatar::MIME_TYPE_JPEG)) {
            throw AvatarNotSaved::create();
        }
        
        $filename = (string)$user->getId() . '.jpg';
        $this->fileManager->moveFile($tmpFilename, $filename, self::DIR_AVATAR);

        return new Avatar(self::DIR_AVATAR . $filename);
    }

 /*   public function createAvatarFromFile(string $destinationPath, $file)
    {
        $filename = $this->getUniqueFileName();
        \file_put_contents(\sprintf('%s/%s', $destinationPath, $filename), \file_get_contents($file));

        return $filename;
    }*/
}