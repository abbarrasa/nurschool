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

use LogicException;
use Nurschool\Entity\User;
use Nurschool\Storage\FileStorage;
use YoHang88\LetterAvatar\LetterAvatar;

final class LetterAvatarGenerator implements AvatarGenerator
{
    private const DIR_AVATAR = '/uploads/avatar/';

    public function __construct(FileStorage $fileStorage)
    {
        $this->fileStorage = $fileStorage;
    }

    public function generateUserAvatar(User $user, array $options = []): string
    {
        // Square shape, size 64px
        $avatar = new LetterAvatar((string)$user->getFullName(), 'square', 64);

        $tmpFilename = \tempnam(\sys_get_temp_dir(), 'avatar_');

        // Save image as JPEG
        if (!$avatar->saveAs($tmpFilename, LetterAvatar::MIME_TYPE_JPEG)) {
            throw new LogicException("Avatar can not be saved");
        }
        
        $filename = (string)$user->getId() . '.jpg';
        $path = $this->fileStorage->moveFile($tmpFilename, $filename, self::DIR_AVATAR);

        return $path;
    }

 /*   public function createAvatarFromFile(string $destinationPath, $file)
    {
        $filename = $this->getUniqueFileName();
        \file_put_contents(\sprintf('%s/%s', $destinationPath, $filename), \file_get_contents($file));

        return $filename;
    }*/
}