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

namespace Nurschool\Storage;

use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Filesystem\Filesystem;

final class LocalFileStorage implements FileStorage
{
    private const DIR_PUBLIC = '/public';

    private string $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function getPublicUrl(string $path): string
    {
        $package = new Package(new EmptyVersionStrategy());

        return $package->getUrl($path);
    }

    public function moveFile(string $source, string $filename, string $directory): string
    {
        $filesystem = new Filesystem();
        $basePath = $this->projectDir . self::DIR_PUBLIC;
        if (substr($directory, 0, 1) === '/') {
            $target = $basePath . $directory;
        } else {
            $target = $basePath . '/' . $directory;
        }

        $filesystem->mkdir($target, 0755);

        if (substr($target, -1) === '/') {
            $target .= $filename;
        } else {
            $target .= "/$filename";
        }

        $filesystem->copy($source, $target, true);
        $filesystem->remove($source);

        return substr($target, strlen($basePath));
    }
}