<?php

namespace Bermuda\Flysystem;

use Bermuda\Arrayable;
use Bermuda\String\Stringable;

final class Location implements Stringable, Arrayable
{
    public function __construct(private string $path, private string $separator = '/')
    {
        $this->path = $this->normalize($path);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->path;
    }

    /**
     * @param bool $withoutLastSegment
     * @return array
     */
    public function toArray(bool $withoutLastSegment = false): array
    {
        $segments = $this->explodePath();

        if ($withoutLastSegment) {
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
        $copy->path .= $this->implodeSegments($segments);

        return $copy;
    }

    /**
     * @param string ...$segments
     * @return Location
     */
    public function prepend(string ... $segments): Location
    {
        $copy = clone $this;
        $copy->path = $this->implodeSegments($segments)
            . DIRECTORY_SEPARATOR . $this->path;

        return $copy;
    }

    /**
     * @return string
     */
    public function lastSegment(): string
    {
        $segments = $this->explodePath();
        return array_pop($segments);
    }
    
    public function up(): Location
    {
        $copy = clone $this;
        $copy->path = $this->implodeSegments($this->toArray(true));
        
        return $copy;
    }

    private function normalize(string $path): string
    {
        $segments = $this->explodePath(str_replace(['/', '\\'], $this->separator, $path));
        return $this->implodeSegments($segments);
    }

    private function explodePath(?string $path = null): array
    {
        return explode($this->separator, $path ?? $this->path);
    }

    private function implodeSegments(array $segments): string
    {
        $path = '';
        foreach ($segments as $segment) {
            if (!empty($segment)) {
                $path .= $this->separator . trim($segment, '\/');
            }
        }

        return ltrim($path, $this->separator);
    }
}
