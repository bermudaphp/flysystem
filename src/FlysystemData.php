<?php

namespace Bermuda\Flysystem;

use Carbon\Carbon;
use Bermuda\Arrayable;
use Bermuda\String\Stringable;
use League\Flysystem\FilesystemOperator;

abstract class FlysystemData implements Stringable, Arrayable, \IteratorAggregate
{
    protected ?string $name = null;
    protected ?int $lastModified = null;
    protected ?string $path = null;

    protected const separator = '/';

    protected function __construct(protected string $location, protected Flysystem $flysystem)
    {
    }

    final public function getPath(): string
    {
        if ($this->path == null)
        {
            $segments = $this->getSegments();
            array_pop($segments);
            return $this->path = implode(static::separator, $segments);
        }

        return $this->path;
    }

    abstract public function getSize(): int ;

    protected function getSegments(): array
    {
        return explode(static::separator, str_replace(['\\'], static::separator, $this->location));
    }

    protected function normalizePath(string $path): string
    {
        return rtrim(str_replace(['\\'], static::separator, $path), '\\');
    }

    /**
     * @return int
     * @throws \League\Flysystem\FilesystemException
     */
    final public function lastModified(bool $asCarbon = true): int|Carbon
    {
        $timestamp = $this->flysystem->lastModified($this->location);

        if ($asCarbon)
        {
            return Carbon::createFromTimestamp($timestamp);
        }

        return $timestamp;
    }

    /**
     * @return string
     */
    final public function getName(): string
    {
        if ($this->name == null)
        {
            $segments = $this->getSegments();
            return $this->name = array_pop($segments);
        }

        return $this->name;
    }

    /**
     * @param string|null $visibility
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
    final public function visibility(string $visibility = null)
    {
        $value = $this->flysystem->visibility($this->location);

        if ($visibility !== null)
        {
            $this->flysystem->setVisibility($this->location, $visibility);
        }

        return $value;
    }
}
