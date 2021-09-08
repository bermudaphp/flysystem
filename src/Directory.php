<?php

namespace Bermuda\Flysystem;

use League\Flysystem\FilesystemOperator;
use Psr\Http\Message\StreamFactoryInterface;

final class Directory extends FlysystemData implements \Countable
{
    /**
     * @param string $location
     * @param FilesystemOperator|null $system
     * @param StreamFactoryInterface|null $streamFactory
     * @return static
     * @throws \League\Flysystem\FilesystemException
     * @throws Exceptions\NoSuchDirectory
     */
    public static function open(
        string $location, ?Flysystem $system = null
    ): self
    {
        if (!($system = self::system($system))->isDirectory($location))
        {
           throw new Exceptions\NoSuchDirectory($location);
        }

        return new self($location, $system);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->location;
    }

    /**
     * @param string $location
     * @param FilesystemOperator|null $system
     * @param StreamFactoryInterface|null $streamFactory
     * @return static
     * @throws \League\Flysystem\FilesystemException
     */
    public static function create(string $location, ?Flysystem $system = null
    ): self
    {
        try {
            return self::open($location, $system);
        } catch (Exceptions\NoSuchDirectory $e) {
            ($system = self::system($system))->getOperator()
                ->createDirectory($location);
            
            return self::open($location, $system);
        }
    }

    /**
     * @param self[] $directories
     * @param bool $deleteMerged
     * @throws \League\Flysystem\FilesystemException
     */
    public function merge(array $directories, bool $deleteMerged = false): void
    {
        foreach ($directories as $directory)
        {
            /**
             * @var File|self $flysystemData
             */
            foreach ($directory as $flysystemData)
            {
                $flysystemData->copy($this->location, true);
            }

            if ($deleteMerged)
            {
                $directory->delete();
            }
        }
    }

    /**
     * @param string $destination
     * @return Directory
     * @throws \League\Flysystem\FilesystemException
     */
    final public function copy(string $destination): self
    {
        $destination = $this->normalizePath($destination);

        $directory = self::create($destination, $this->flysystem);

        foreach ($this as $flysystemData)
        {
            if ($directory->getName() !== $flysystemData->getName())
            {
                $directory->add($flysystemData);
            }
        }

        return $directory;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        $size = 0;

        foreach ($this as $item)
        {
            $size += $item->getSize();
        }

        return $size;
    }

    /**
     * @param string $path
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public function exists(string $path): bool
    {
        return $this->flysystem->exists($this->location->append($path));
    }

    // public function isRoot(): bool{}

    /**
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public function isEmpty(): bool
    {
        foreach ($this->flysystem->getOperator()->listContents($this->location) as $i)
        {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public function hasChildes(): bool
    {
        return $this->getChildes() !== [];
    }

    /**
     * @param bool $recursive
     * @return int
     */
    public function count(bool $recursive = false): int
    {
        $count = 0;

        foreach ($this as $item)
        {
            $count += $recursive ? $item->count() + 1 : 1;
        }

        return $count;
    }

    public function getIterator(): \Generator
    {
        foreach ($this->listContents() as $flysystemData)
        {
            yield $flysystemData;
        }
    }

    /**
     * @throws \League\Flysystem\FilesystemException
     */
    public function delete(): void
    {
        $this->flysystem->getOperator()->deleteDirectory($this->location);
    }

    /**
     * @param File|Directory $flysystemData
     * @throws \League\Flysystem\FilesystemException
     */
    public function add(File|self $flysystemData): void
    {
        if ($flysystemData instanceof self)
        {
            $flysystemData->copy($this->location . self::separator . $flysystemData->getName());
        }

        else
        {
            $flysystemData->copy($this->location, true);
        }
    }

    /**
     * @param string $filename
     * @param string $content
     * @return File
     * @throws \League\Flysystem\FilesystemException
     */
    public function addNewFile(string $filename, string $content): File
    {
        return File::create($this->location->append($filename), $content, $this->system, $this->streamFactory);
    }

    /**
     * @param string $location
     * @return $this
     * @throws \League\Flysystem\FilesystemException
     */
    public function addNewDirectory(string $location): self
    {
        return self::create($this->location->append($location), $this->flysystem);
    }

    /**
     * @return File[]
     */
    public function getFiles(): array
    {
        return $this->listContents('/', static fn($v) => $v instanceof File);
    }

    /**
     * @return self[]
     * @throws \League\Flysystem\FilesystemException
     */
    public function getChildes(): array
    {
        return $this->listContents('/', static fn($v) => $v instanceof self);
    }

    /**
     * @param string $path
     * @param callable|null $filter
     * @return array
     * @throws \League\Flysystem\FilesystemException
     */
    public function listContents(string $path = '/', callable $filter = null): array
    {
        return $this->flysystem->listContents($this->location . self::separator . $path, $filter);
    }

    /**
     * @return array
     * @throws \League\Flysystem\FilesystemException
     */
    public function toArray(): array
    {
        return $this->listContents();
    }
}
