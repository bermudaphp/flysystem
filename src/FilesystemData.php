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
            return $this->path = implode('/', $segments);
        }

        return $this->path;
    }

    protected function getSegments(): array
    {
        return explode('/', $this->location);
    }

    protected function normalizePath(string $path): string
    {
        return str_replace(['\\'], '/', $path);
    }

    /**
     * @return int
     * @throws \League\Flysystem\FilesystemException
     */
    final public function lastModified(): int
    {
        if ($this->lastModified == null)
        {
            $this->lastModified = $this->system->lastModified($this->location);
        }

        return $this->lastModified;
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
