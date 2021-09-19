<?php

namespace Bermuda\Flysystem;

use Bermuda\Arrayable;
use Bermuda\String\Str;
use Bermuda\String\Stringable;
use League\Flysystem\PathNormalizer;

final class Location implements Stringable, Arrayable
{
    private const separator = '/';
    public function __construct(private string $path)
    {
        $this->path = empty($path) ? self::separator
            : $this->normalize($path);
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
        $segments = $this->explode();

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
        $copy->path .= $this->implode($segments);

        return $copy;
    }

    /**
     * @param string ...$segments
     * @return Location
     */
    public function prepend(string ... $segments): Location
    {
        $copy = clone $this;
        $copy->path = $this->implode($segments) 
            . self::separator . $this->path;

        return $copy;
    }

    /**
     * @return string
     */
    public function basename(): string
    {
        $segments = $this->segments();
        return array_pop($segments);
    }

    /**
     * @return Location
     */
    public function up(): Location
    {
        $copy = clone $this;
        $copy->path = $this->implode($this->toArray(true));

        if (empty($copy->path)) {
            $copy->path = '/';
        }

        return $copy;
    }

    private function normalize(string $path): string
    {
        if ($path === self::separator){
            return $path;
        }

        $path = str_replace('\\', self::separator, $path);
        return $this->implode($this->explode($path));
    }

    private function explode(?string $path = null): array
    {
        return explode(self::separator, $path ?? $this->path);
    }

    private function implode(array $segments): string
    {
        $path = '';
        foreach ($segments as $segment) {
            if (!empty($segment) && !Str::equalsAny($segment, ['.', '..'])) {
                $path .= self::separator . $segment;
            }
        }

        return $path;
    }
}
