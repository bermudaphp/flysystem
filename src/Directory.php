<?php

namespace Bermuda\Flysystem;

use Countable;
use Generator;
use League\Flysystem\FilesystemException;

final class Directory extends FlysystemData implements Countable
{
    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->location;
    }

    /**
     * @param self[] $directories
     * @param bool $deleteMerged
     * @throws FilesystemException
     */
    public function merge(array $directories, bool $deleteMerged = false): self
    {
        foreach ($directories as $directory) {
            /**
             * @var File|self $flysystemData
             */
            foreach ($directory as $flysystemData) {
                $flysystemData->copy($this->location, true);
            }

            if ($deleteMerged) {
                $directory->delete();
            }
        }
        
        return $this;
    }

    /**
     * @param string $destination
     * @return Directory
     * @throws FilesystemException
     */
    final public function copy(string $destination): self
    {
        $directory = self::create($destination, $this->flysystem);
        foreach ($this as $flysystemData) {
            if ($directory->getName() !== $flysystemData->getName()) {
                $directory->add($flysystemData);
            }
        }

        return $directory;
    }

    /**
     * @param string $destination
     * @return self
     * @throws FilesystemException
     */
    public function move(string $destination): self
    {
        $new = $this->copy($destination);
        $this->delete();
        return $new;
    }

    /**
     * @param string $location
     * @param Flysystem|null $system
     * @return self
     * @throws FilesystemException
     */
    public static function create(string $location, ?Flysystem $system = null
    ): self
    {
        try {
            return self::open($location, $system);
        } catch (Exceptions\NoSuchDirectory) {
            ($system = self::system($system))->getOperator()
                ->createDirectory($location);
            return self::open($location, $system);
        }
    }

    /**
     * @param string $location
     * @param Flysystem|null $system
     * @return self
     * @throws Exceptions\NoSuchDirectory
     */
    public static function open(
        string $location, ?Flysystem $system = null
    ): self
    {
        if (!($system = self::system($system))->isDirectory($location)) {
            throw new Exceptions\NoSuchDirectory($location);
        }

        return new self($location, $system);
    }

    /**
     * @param File|Directory $flysystemData
     * @throws FilesystemException
     */
    public function add(File|self $flysystemData): void
    {
        if ($flysystemData instanceof self) {
            $flysystemData->copy($this->location->append($flysystemData->getName()));
        } else {
            $flysystemData->copy($this->location, true);
        }
    }

    /**
     * @throws FilesystemException
     */
    public function delete(): void
    {
        $this->flysystem->getOperator()->deleteDirectory($this->location);
    }

    // public function isRoot(): bool{}

    /**
     * @return int
     */
    public function getSize(): int
    {
        $size = 0;

        foreach ($this as $item) {
            $size += $item->getSize();
        }

        return $size;
    }

    /**
     * @param string $path
     * @return bool
     * @throws FilesystemException
     */
    public function exists(string $path): bool
    {
        return $this->flysystem->exists($this->location->append($path));
    }

    /**
     * @return bool
     * @throws FilesystemException
     */
    public function isEmpty(): bool
    {
        foreach ($this->flysystem->getOperator()->listContents($this->location) as $i) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @throws FilesystemException
     */
    public function hasChildes(): bool
    {
        return $this->getChildes() !== [];
    }

    /**
     * @return self[]
     * @throws FilesystemException
     */
    public function getChildes(): array
    {
        return $this->listContents('/', static fn(FlysystemData $v) => $v instanceof self);
    }

    /**
     * @param string $path
     * @param callable|null $filter
     * @return array
     * @throws FilesystemException
     */
    public function listContents(string $path = '/', callable $filter = null): array
    {
        return $this->flysystem->listContents($this->location->append($path), $filter);
    }

    /**
     * @param bool $recursive
     * @return int
     */
    public function count(bool $recursive = false): int
    {
        $count = 0;

        foreach ($this as $item) {
            $count += $recursive ? $item->count() + 1 : 1;
        }

        return $count;
    }

    /**
     * @return Generator
     * @throws FilesystemException
     */
    public function getIterator(): Generator
    {
        foreach ($this->listContents() as $flysystemData) {
            yield $flysystemData;
        }
    }

    /**
     * @param string $filename
     * @param string $content
     * @return File
     * @throws FilesystemException
     */
    public function addNewFile(string $filename, string $content): File
    {
        return File::create($this->location->append($filename), $content, $this->flysystem);
    }

    /**
     * @param string $location
     * @return $this
     * @throws FilesystemException
     */
    public function addNewDirectory(string $location): self
    {
        return self::create($this->location->append($location), $this->flysystem);
    }

    /**
     * @return array
     * @throws FilesystemException
     */
    public function getFiles(): array
    {
        return $this->listContents('/', static fn($v) => $v instanceof File);
    }

    /**
     * @return array
     * @throws FilesystemException
     */
    public function toArray(): array
    {
        return $this->listContents();
    }
}
