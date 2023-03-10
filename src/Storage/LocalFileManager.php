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

use Nurschool\Storage\FileManagerInterface;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\Filesystem\Filesystem;

final class LocalFileManager implements FileManagerInterface
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/');
    }

    public function getPublicUrl(string $path): string
    {
        $package = new Package(new EmptyVersionStrategy());

        return $package->getUrl($path);
    }

    public function moveFile(string $source, string $filename, string $directory): void
    {
        $filesystem = new Filesystem();
        $target = $this->baseDir . '/'. ltrim($directory, '/');

        $filesystem->mkdir($target, 0755);

        if (substr($target, -1) === '/') {
            $target .= $filename;
        } else {
            $target .= "/$filename";
        }

        $filesystem->copy($source, $target, true);
        $filesystem->remove($source);
    }
}