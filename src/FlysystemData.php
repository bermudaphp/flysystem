<?php

namespace Bermuda\Flysystem;

use Carbon\Carbon;
use Bermuda\Arrayable;
use Bermuda\String\Stringable;
use League\Flysystem\FilesystemOperator;

abstract class FlysystemData implements Stringable, Arrayable, \IteratorAggregate
{
    protected Location $location;

    protected ?string $name = null;
    protected ?string $path = null;
    
    protected function __construct(string $location, protected Flysystem $flysystem)
    {
        $this->location = new Location($location);
    }
    
    protected static function system(?Flysystem $system = null): Flysystem
    {
        return $system ?? new Flysystem;
    }

    final public function getPath(): string
    {
        return $this->path === null ? 
            $this->path = $this->location->up();
            : $this->path;
    }

    abstract public function getSize(): int ;
    
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
        return $this->name === null ? 
            $this->name = $this->location->lastSegment() 
            : $this->name;
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
