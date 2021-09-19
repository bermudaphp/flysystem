<?php

namespace Bermuda\Flysystem;

use Bermuda\Arrayable;
use Bermuda\String\Str;
use Bermuda\String\Stringable;
use Webmozart\PathUtil\Path;
use function Bermuda\substring;

final class Location implements Stringable, Arrayable
{
    private const separator = '/';
    public function __construct(private string $path)
    {
        $this->path = empty($path) ? self::separator
            : Path::canonicalize($this->path);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->path;
    }

    /**
     * @param bool $withoutBasename
     * @return array
     */
    public function toArray(bool $withoutBasename = false): array
    {
        $segments = explode('/', $this->path);

        if ($withoutBasename) {
            array_pop($segments);
        }

        return $segments;
    }

    /**
     * @param string ...$segments
     * @return Location
     */
    public function append(string ... $segments): Location
    {
        $copy = clone $this;
        $copy->path = Path::join($this->path, ... $segments);

        return $copy;
    }

    /**
     * @param string ...$segments
     * @return Location
     */
    public function prepend(string ... $segments): Location
    {
        $copy = clone $this;
        $copy->path = Path::join([... $segments, $this->path]);

        return $copy;
    }

    /**
     * @return string
     */
    public function basename(): string
    {
        return basename($this->path);
    }

    /**
     * @return Location
     */
    public function up(): Location
    {
        $copy = clone $this;
        $copy->path = substring($this->path, length: Str::length($this->basename()));

        return $copy;
    }
}
