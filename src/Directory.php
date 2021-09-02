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

        if (FileInfo::isDirectory($location, $system))
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
        ($system = self::system($system))->createDirectory($location);
        return self::open($location, $system, $streamFactory);
    }

    /**
     * @param self[] $directories
     * @param bool $deleteMerged
     */
    public function merge(array $directories, bool $deleteMerged = false): void
    {
        foreach ($directories as $directory)
        {
            foreach ($directory as $fileOrDirectory)
            {
                $fileOrDirectory->move($this->location);
            }

            if ($deleteMerged)
            {
                $directory->delete();
            }
        }
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
            if ($item instanceof File)
            {
                $count++;
            }

            else
            {
                $count += $recursive ? $item->count() : 1;
            }
        }

        return $count;
    }

    public function getIterator(): \Generator
    {
        foreach ($this->listContents() as $fileOrDirectory)
        {
            yield $fileOrDirectory;
        }
    }

    /**
     * @param File|Directory $fileOrDirectory
     * @throws \League\Flysystem\FilesystemException
     */
    public function add(File|self $fileOrDirectory): void
    {
        $fileOrDirectory->move($this->location);
    }

    /**
     * @param string $filename
     * @param string $content
     * @return File
     * @throws \League\Flysystem\FilesystemException
     */
    public function addNewFile(string $filename, string $content): File
    {
        return File::create($content, $this->location . DIRECTORY_SEPARATOR . ltrim( $filename, '\/'), $this->system, $this->streamFactory);
    }

    /**
     * @param string $location
     * @return $this
     * @throws \League\Flysystem\FilesystemException
     */
    public function addNewDirectory(string $location): self
    {
        $location = trim($location, '\/');
        return self::create($this->location . DIRECTORY_SEPARATOR . $location, $this->system, $this->streamFactory);
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
     * @return int
     * @throws \League\Flysystem\FilesystemException
     */
    public function lastModified(): int
    {
        return $this->system->lastModified($this->location);
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
