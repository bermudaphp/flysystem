<?php

namespace Bermuda\Flysystem;

use League\Flysystem\FilesystemException;

trait Iterate
{
    /**
     * @param string $path
     * @param callable $callback
     * @return array
     * @throws FilesystemException
     */
    public function iterate(callable $callback, string $path = '/'): array
    {
        $results = [];
        foreach ($this->listContents($path) as $content) {
            if (($result = $callback($content)) !== null) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * @param string $path
     * @param callable|null $filter
     * @return array<File|Directory>
     * @throws FilesystemException
     */
    abstract public function listContents(string $path, callable $filter = null): array;
}
