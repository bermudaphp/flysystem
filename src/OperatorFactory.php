<?php

namespace Bermuda\Flysystem;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

final class OperatorFactory
{
    public function __invoke(): Filesystem
    {
        return self::makeLocal();
    }

    /**
     * @param string|null $path
     * @return Filesystem
     */
    public static function makeLocal(string $path = null): Filesystem
    {
        return new Filesystem(new LocalFilesystemAdapter($path ?? getcwd()));
    }
}
