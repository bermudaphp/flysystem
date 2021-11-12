<?php

namespace Bermuda\Flysystem;

use Bermuda\Arrayable;
use Bermuda\String\Stringable;
use Webmozart\PathUtil\Path;
use function Bermuda\String\str_match_any;

final class Location implements Stringable, Arrayable
{
    public function __construct(private string $path)
    {
        $this->path = empty($path) ? '/'
            : Path::canonicalize($this->path);
    }

    /**
     * @param string[]|string $pattern
     * @return bool
     */
    public function match(array|string $pattern): bool
    {
        return str_match_any(!is_array($pattern) ? [$pattern] : $pattern, $this->path);
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
        $copy->path = substr($this->path, length: mb_strlen($this->basename()));

        return $copy;
    }
}
