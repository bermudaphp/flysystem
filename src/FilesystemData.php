<?php

namespace Bermuda\Flysystem;

use Bermuda\Arrayable;
use Bermuda\String\Stringable;
use League\Flysystem\FilesystemOperator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\StreamFactoryInterface;

abstract class FlysystemData implements Stringable, Arrayable, \IteratorAggregate
{
    protected ?string $name = null;
    protected ?int $lastModified = null;
    protected ?string $path = null;

    protected const separator = '/';

    protected function __construct(protected string $location, protected FilesystemOperator $system,
                                protected ?StreamFactoryInterface $streamFactory = null)
    {
        $this->streamFactory = $this->streamFactory ?? new Psr17Factory();
    }

    protected static function system(?FilesystemOperator $filesystemOperator): FilesystemOperator
    {
        return $filesystemOperator ?? FileSystemFactory::makeSystem();
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
    final public function lastModified(): int
    {
        return $this->system->lastModified($this->location);
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
        $value = $this->system->visibility($this->location);

        if ($visibility !== null)
        {
            $this->system->setVisibility($this->location, $visibility);
        }

        return $value;
    }
}
