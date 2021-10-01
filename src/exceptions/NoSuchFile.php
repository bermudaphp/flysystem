<?php

namespace Bermuda\Flysystem\Exceptions;

final class NoSuchFile extends \RuntimeException
{
    public function __construct(string $filename)
    {
        parent::__construct('No such file or directory: ' . $filename);
    }
}
