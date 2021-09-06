<?php

namespace Bermuda\Flysystem\Exceptions;

final class NoSuchDirectoryException extends \RuntimeException
{
    public function __construct(string $location)
    {
        parent::__construct('No such directory: ' . $location);
    }
}
