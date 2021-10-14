<?php

namespace Bermuda\Flysystem;

use Countable;
use Generator;
use League\Flysystem\FilesystemException;

final class Directory extends AbstractFile implements Countable
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
     * @param bool $deleteAfter
     * @throws FilesystemException
     */
    public function merge(array $directories, bool $deleteAfter = false): self
    {
        foreach ($directories as $directory) {
            foreach ($directory as $file) {
                $file->copy($this->location, true);
            }

            if ($deleteAfter) {
                $directory->delete();
            }
        }
        
        return $this;
    }

    final public function up():? self
    {
        if (($loc = $this->location->up()) === '/') {
            return null;
        }

        return $this->flysystem->open($loc);
    }

    /**
     * @param string $destination
     * @return Directory
     * @throws FilesystemException
     */
    final public function copy(string $destination): self
    {
        $directory = self::create($destination, $this->flysystem);
        foreach ($this as $file) {
            if ($directory->basename() !== $file->basename()) {
                $directory->add($file);
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
        } catch (NoSuchFile) {
            ($system = self::system($system))->operator->createDirectory($location);
            return self::open($location, $system);
        }
    }

    /**
     * @param string $location
     * @param Flysystem|null $system
     * @return self
     * @throws NoSuchFile
     */
    public static function open(
        string $location, ?Flysystem $system = null
    ): self
    {
        if (!($system = self::system($system))->isDirectory($location)) {
            throw new NoSuchFile($location);
        }

        return new self($location, $system);
    }

    /**
     * @param File|Directory $file
     * @throws FilesystemException
     */
    public function add(File|self $file): void
    {
        if ($file instanceof self) {
            $file->copy($this->location->append($file->basename()));
        } else {
            $file->copy($this->location, true);
        }
    }

    /**
     * @throws FilesystemException
     */
    public function delete(): void
    {
        $this->flysystem->deleteDirectory($this->location);
    }

    // public function isRoot(): bool{}

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
        foreach ($this->flysystem->listContents($this->location) as $i) {
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
        return $this->listContents('/', static fn(AbstractFile $file) => $file instanceof self);
    }

    /**
     * @param string $path
     * @param callable|null $filter
     * @return array<File|Directory>
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
            $count += $recursive && $item instanceof self ? $item->count() + 1 : 1;
        }

        return $count;
    }

    /**
     * @return iterable<File|Directory>
     * @throws FilesystemException
     */
    public function getIterator(): Generator
    {
        foreach ($this->listContents() as $file) {
            yield $file;
        }
    }
    
    /**
     * @param string $filename
     * @param ?string $content
     * @return File|self
     * @throws FilesystemException
     */
    public function createFile(string $location, string $content = ''): File
    {
        return File::create($this->location->append($location), $content, $this->flysystem);
    }

    /**
     * @param string $filename
     * @param ?string $content
     * @return File|self
     * @throws FilesystemException
     */
    public function createDirectory(string $location): self
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
     * @return array<File|Directory>
     * @throws FilesystemException
     */
    public function toArray(): array
    {
        return $this->listContents();
    }
}
