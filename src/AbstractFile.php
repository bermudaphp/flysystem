<?php

namespace Bermuda\Flysystem;

use Bermuda\Arrayable;
use Carbon\CarbonInterface;
use IteratorAggregate;
use Bermuda\String\Stringable;
use League\Flysystem\FilesystemException;

abstract class AbstractFile implements Stringable, Arrayable, IteratorAggregate
{
    public readonly Location $location;

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

    /**
     * @param string|array $pattern
     * @return bool
     */
    final public function match(string|array $pattern): bool
    {
        return $this->location->match($pattern);
    }

    final public function getPath(): string
    {
        return $this->path === null ?
            $this->path = $this->location->up()
            : $this->path;
    }
    
    public function __get(string $name)
    {
        return match ($name) {
            'location' => $this->location,
            default => null
        };
    }

    public function getSize(): int
    {
        return $this->flysystem->filesize($this->location);
    }

    /**
     * @return int|CarbonInterface
     * @throws FilesystemException
     */
    final public function lastModified(bool $asCarbon = true): int|CarbonInterface
    {
        return $this->flysystem->lastModified($this->location, $asCarbon);
    }

    /**
     * @return string
     */
    final public function basename(): string
    {
        return $this->name === null ?
            $this->name = $this->location->basename()
            : $this->name;
    }

    /**
     * @param string|null $visibility
     * @return string
     * @throws FilesystemException
     */
    final public function visibility(string $visibility = null): string
    {
        $value = $this->flysystem->visibility($this->location);
        if ($visibility !== null) {
            $this->flysystem->setVisibility($this->location, $visibility);
        }

        return $value;
    }
}
