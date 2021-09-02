<?php

namespace Bermuda\Flysystem;

use Bermuda\Iterator\StreamIterator;
use Bermuda\String\Str;
use League\Flysystem\FilesystemOperator;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class File extends FlysystemData implements StreamInterface
{
    /**
     * @var resource
     */
    private $fileHandler = null;
    private ?StreamInterface $stream = null;

    private ?int $filesize = null;
    private ?string $extension = null;
    private ?string $mimeType = null;

    /**
     * @throws \League\Flysystem\FilesystemException
     */
    private function __construct(string $filename, FilesystemOperator $system,
        ?StreamFactoryInterface $streamFactory = null,
        private int $bytesPerIteration = 1024
    )
    {
        parent::__construct($filename, $system, $streamFactory);
    }

    /**
     * @throws \RuntimeException
     */
    final public function __clone(): void
    {
        throw new \RuntimeException('This object is not cloneable');
    }

    /**
     * @param string $filename
     * @param FilesystemOperator|null $system
     * @param StreamFactoryInterface|null $streamFactory
     * @param int $bytesPerIteration
     * @return static
     * @throws \League\Flysystem\FilesystemException
     * @throws \InvalidArgumentException
     */
    public static function open(string $filename, ?FilesystemOperator $system = null,
                                ?StreamFactoryInterface $streamFactory = null,
                                int $bytesPerIteration = 1024
    ): self
    {
        if (!($system = self::system($system))->fileExists($filename))
        {
            throw new \InvalidArgumentException(
                sprintf('No such file: %s', $filename)
            );
        }

        if (FileInfo::isImage($filename, $system))
        {
            return new Image($filename, $system, $streamFactory, $bytesPerIteration);
        }

        return new self($filename, $system, $streamFactory, $bytesPerIteration);
    }

    /**
     * @param string $content
     * @param string $filename
     * @param FilesystemOperator|null $system
     * @param StreamFactoryInterface|null $streamFactory
     * @param int $bytesPerIteration
     * @return static
     * @throws \League\Flysystem\FilesystemException
     */
    public static function create(string $content, ?string $filename = null, ?FilesystemOperator $system = null,
                                  ?StreamFactoryInterface $streamFactory = null,
                                  int $bytesPerIteration = 1024
    ): self
    {
        if ($filename === null)
        {
            $extension = FileInfo::extension($content);
            $filename  = Str::filename($extension);
        }

        ($system = self::system($system))->write($filename, $content);
        return self::open($filename, $system, $streamFactory, $bytesPerIteration);
    }
    
    /**
     * @return int
     */
    public function bytesPerIteration(?int $bytes = null): int
    {
        if ($bytes !== null)
        {
            return $this->bytesPerIteration = $bytes;
        }

        return $this->bytesPerIteration;
    }

    /**
     * @return resource
     * @throws \League\Flysystem\FilesystemException
     */
    final public function getFileHandler()
    {
        if ($this->fileHandler == null)
        {
            $this->fileHandler = $this->system->readStream($this->location);
        }

        return $this->fileHandler;
    }

    private function getStream(): StreamInterface
    {
        if ($this->stream == null)
        {
            $handler = $this->getFileHandler();
            $this->stream = $this->streamFactory->createStreamFromResource($handler);
        }

        return $this->stream;
    }

    /**
     * @return StreamIterator
     */
    final public function getIterator(int $bytesPerIteration = null): StreamIterator
    {
        return new StreamIterator($this, $bytesPerIteration ?? $this->bytesPerIteration);
    }

    /**
     * @param string $name
     * @throws \League\Flysystem\FilesystemException
     */
    final public function rename(string $name): void
    {
        if (!str_contains($name, '.'))
        {
            $name = sprintf('%s.%s', $name, $this->getExtension());
        }

        $this->move($filename = $this->getPath() . '/' . $name);
        $this->location = $filename;
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->location;
    }

    /**
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
    final public function getMimeType(): string
    {
        if ($this->mimeType == null)
        {
            return $this->mimeType = $this->system->mimeType($this->location);
        }

        return $this->mimeType;
    }

    /**
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
    final public function getExtension(): string
    {
        if ($this->extension == null)
        {
            return $this->extension = FileInfo::extension($this->location, $this->system);
        }

        return $this->extension;
    }

    /**
     * @throws \League\Flysystem\FilesystemException
     */
    final public function delete(): void
    {
        $this->system->delete($this->location);
    }

    /**
     * @return int
     * @throws \League\Flysystem\FilesystemException
     */
    final public function getFileSize(): int
    {
        if ($this->filesize == null)
        {
            return $this->filesize = $this->system->fileSize($this->location);
        }

        return $this->filesize;
    }

    /**
     * @param string $destination
     * @throws \League\Flysystem\FilesystemException
     */
    final public function move(string $destination): void
    {
        if (FileInfo::isDirectory($destination, $this->system))
        {
            $destination = rtrim($destination, '\/') .
                '/' . $this->getName();
        }

        $this->system->move($this->location, $destination);
        $this->location = $destination;

        $this->fileHandler = null;
        $this->stream = null;
        $this->name = null;
        $this->path = null;
    }

    /**
     * @param string $destination
     * @throws \League\Flysystem\FilesystemException
     */
    final public function copy(string $destination): void
    {
        $this->system->copy($this->location, $destination);
    }

    /**
     * @return array
     * @throws \League\Flysystem\FilesystemException
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->location,
            'extension' => $this->getExtension(),
            'mimeType' => $this->getMimeType(),
            'size' => $this->getFileSize(),
            'modified' => $this->lastModified(),
        ];
    }

    /**
     * @inerhitDoc
     */
    final public function __toString(): string
    {
        return $this->system->read($this->location);
    }

    /**
     * @inerhitDoc
     */
    final public function close(): void
    {
        $this->getStream()->close();
    }

    /**
     * @inerhitDoc
     */
    final public function detach()
    {
        return $this->getStream()->detach();
    }

    /**
     * @inerhitDoc
     */
    final public function tell(): int
    {
        return $this->getStream()->tell();
    }

    /**
     * @inerhitDoc
     */
    final public function eof(): bool
    {
        return $this->getStream()->eof();
    }

    /**
     * @inerhitDoc
     */
    final public function isSeekable(): bool
    {
        return $this->getStream()->isSeekable();
    }

    /**
     * @inerhitDoc
     */
    final public function seek($offset, $whence = SEEK_SET): void
    {
        $this->getStream()->seek($offset, $whence);
    }

    /**
     * @inerhitDoc
     */
    final public function rewind(): void
    {
        $this->getStream()->rewind();
    }

    /**
     * @inerhitDoc
     */
    final public function isWritable(): bool
    {
        return $this->getStream()->isWritable();
    }

    /**
     * @inerhitDoc
     */
    final public function write($string): int
    {
        return $this->getStream()->write($string);
    }

    /**
     * @inerhitDoc
     */
    final public function isReadable(): bool
    {
        return $this->getStream()->isReadable();
    }

    /**
     * @inerhitDoc
     */
    final public function read($length): string
    {
        return $this->getStream()->read($length);
    }

    /**
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
    final public function getContents(): string
    {
        return $this->system->read($this->location);
    }

    /**
     * @inerhitDoc
     */
    final public function getMetadata($key = null)
    {
        $this->getStream()->getMetadata($key);
    }
}
