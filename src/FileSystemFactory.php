<?php

namespace Bermuda\Flysystem;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

final class FileSystemFactory
{
    public function __invoke(): Filesystem
    {
        return self::makeSystem();
    }

    /**
     * @param string|null $path
     * @return Filesystem
     */
    public static function makeSystem(string $path = null): Filesystem
    {
        return new Filesystem(new LocalFilesystemAdapter($path ?? getcwd()));
    }
}
