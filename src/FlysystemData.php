<?php

namespace Bermuda\Flysystem;

use Carbon\Carbon;
use Bermuda\Arrayable;
use Bermuda\String\Stringable;
use League\Flysystem\FilesystemOperator;

abstract class FlysystemData implements Stringable, Arrayable, \IteratorAggregate
{
    protected ?string $name = null;
    protected ?string $path = null;

    protected const separator = DIRECTORY_SEPARATOR;

    protected function __construct(protected string $location, protected Flysystem $flysystem)
    {
        $this->location = $this->normalizePath($location);
    }
    
    protected static function system(?Flysystem $system = null): Flysystem
    {
        return $system ?? new Flysystem;
    }

    final public function getPath(): string
    {
        return $this->path === null ? 
            $this->path = implode(self::separator, $this->getSegments(true)) 
            : $this->path;
    }

    abstract public function getSize(): int ;
    
    /**
     * @param string $filename
     * @param Flysystem|null $system
     * @param int $bytesPerIteration
     * @return static
     * @throws \League\Flysystem\FilesystemException
     */
    abstract public static function open(
        string $filename, ?Flysystem $system = null,
        int $bytesPerIteration = 1024
    ): self ;
    
    /**
     * @param string|null $filename
     * @param string $content
     * @param Flysystem|null $system
     * @param int $bytesPerIteration
     * @return static
     * @throws \League\Flysystem\FilesystemException
     */
    abstract public static function create(?string $filename = null, string $content = '', Flysystem $system = null,
                                  int $bytesPerIteration = 1024
    ): self ;

    protected function getLastSegment(): string
    {
        $segments = $this->getSegments();
        return array_pop($segments);
    }
    
    protected function getSegments(bool $withoodLastSegment = false): array
    {
        $segments = explode(static::separator, $this->location);
        !$withoodLastSegment ?: array_pop($segments);   
        return $segments;
    }
    
    protected function normalizePath(string $path): string
    {
        return rtrim(str_replace(['\/'], static::separator, $path), static::separator);
    }

    /**
     * @return int
     * @throws \League\Flysystem\FilesystemException
     */
    final public function lastModified(bool $asCarbon = true): int|Carbon
    {
        $timestamp = $this->flysystem->getOperator()
            ->lastModified($this->location);
        return $asCarbon ? Carbon::createFromTimestamp($timestamp) : $timestamp;
    }

    /**
     * @return string
     */
    final public function getName(): string
    {
        return $this->name === null ? $this->name = $this->getLastSegment() : $this->name;
    }

    /**
     * @param string|null $visibility
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
    final public function visibility(string $visibility = null): string
    {
        $value = $this->flysystem->visibility($this->location);

        if ($visibility !== null)
        {
            $this->flysystem->setVisibility($this->location, $visibility);
        }

        return $value;
    }
}
