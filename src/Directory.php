<?php

namespace Bermuda\Flysystem;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
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
     */
    public static function open(
        string $location, ?FilesystemOperator $system = null,
        ?StreamFactoryInterface $streamFactory = null
    ): self
    {
        $system = self::system($system);

        if (!FileInfo::isDirectory($location, $system))
        {
            throw new \InvalidArgumentException(
                sprintf('No such directory: %s', $location)
            );
        }

        return new self($location, $system, $streamFactory);
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
    public static function create(string $location, ?FilesystemOperator $system = null,
                                  ?StreamFactoryInterface $streamFactory = null,
    ): self
    {
        try {
            return self::open($location, $system, $streamFactory);
        }

        catch (\InvalidArgumentException $e)
        {
            ($system = self::system($system))->createDirectory($location);
            return self::open($location, $system, $streamFactory);
        }
    }
    
    /**
     * @param self[] $directories
     * @param bool $deleteMerged
     */
    public function merge(array $directories, bool $deleteMerged = false): void
    {
        foreach ($directories as $directory)
        {
            /**
             * @var File|self $fileOrDirectory
             */
            foreach ($directory as $fileOrDirectory)
            {
                $fileOrDirectory->copy($this->location, true);
            }

            if ($deleteMerged)
            {
                $directory->delete();
            }
        }
    }

    /**
     * @param string $destination
     * @param bool $destinationIsDir
     * @throws \League\Flysystem\FilesystemException
     */
    final public function copy(string $destination): self
    {
        $destination = $this->normalizePath($destination);

        $directory = self::create($destination, $this->system, $this->streamFactory);

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
        $path = $this->location . self::separator . $this->normalizePath($path);
        return FileInfo::exists($path, $this->system);
    }

    // public function isRoot(): bool{}

    /**
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public function isEmpty(): bool
    {
        foreach ($this->system->listContents($this) as $item)
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

    public function delete(): void
    {
        $this->system->deleteDirectory($this->location);
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
        return File::create($this->location . self::separator . ltrim( $filename, '\/'), $content, $this->system, $this->streamFactory);
    }

    /**
     * @param string $location
     * @return $this
     * @throws \League\Flysystem\FilesystemException
     */
    public function addNewDirectory(string $location): self
    {
        $location = $this->normalizePath($location);
        return self::create($this->location . self::separator . $location, $this->system, $this->streamFactory);
    }

    /**
     * @return File[]
     */
    public function getFiles(): array
    {
        return $this->listContents(static fn($v) => $v instanceof File);
    }

    /**
     * @return self[]
     * @throws \League\Flysystem\FilesystemException
     */
    public function getChildes(): array
    {
        return $this->listContents(static fn($v) => $v instanceof self);
    }

    /**
     * @return array
     * @throws \League\Flysystem\FilesystemException
     */
    public function listContents(callable $filter = null): array
    {
        $listing = [];

        foreach ($this->system->listContents($this->location) as $attribute)
        {
            if ($attribute instanceof FileAttributes)
            {
                $attribute = File::open($attribute->path(), $this->system, $this->streamFactory);
            }

            else
            {
                /**
                 * @var DirectoryAttributes $attribute
                 */
                $attribute = Directory::open($attribute->path(), $this->system, $this->streamFactory);
            }

            if ($filter !== null && !$filter($attribute))
            {
                continue;
            }

            $listing[] = $attribute;
        }

        return $listing;
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
