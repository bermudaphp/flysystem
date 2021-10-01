<?php

namespace Bermuda\Flysystem;

final class NoSuchFile extends \RuntimeException
{
    public function __construct(string $filename)
    {
        parent::__construct('No such file or directory: ' . $filename);
    }
}
