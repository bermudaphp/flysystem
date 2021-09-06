<?php

namespace Bermuda\Flysystem\Exceptions;

final class NoSuchFileException extends \RuntimeException
{
    public function __construct(string $filename)
    {
        parent::__construct('No such file: ' . $filename);
    }
}
