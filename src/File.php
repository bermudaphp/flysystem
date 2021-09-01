<?php

namespace Bermuda\Files;

use Bermuda\Iterator\StreamIterator;
use League\Flysystem\FilesystemOperator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class File implements \Stringable, StreamInterface, \IteratorAggregate
{
    /**
     * @var resource
     */
    private $fileHandler = null;
    private ?StreamInterface $stream = null;
    private StreamFactoryInterface $streamFactory;

    private ?int $filesize = null;
    private ?string $extension = null;
    private ?string $mimeType = null;
    private ?int $lastModified = null;
    private ?string $name = null;
    private ?string $path = null;

    /**
     * @throws \League\Flysystem\FilesystemException
     */
    private function __construct(private string $filename,
        private FilesystemOperator $system,
        ?StreamFactoryInterface $streamFactory = null,
        private int $bytesPerIteration = 1024
    )
    {
        $this->filename = $this->normalizePath($filename);
        $this->streamFactory = $streamFactory ?? new Psr17Factory();
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
    final public static function open(string $filename, ?FilesystemOperator $system = null,
                                ?StreamFactoryInterface $streamFactory = null,
                                int $bytesPerIteration = 1024
    ): self
    {
        $system !== null ?: $system = FileSystemFactory::makeSystem();

        if (!$system->fileExists($filename))
        {
            throw new \InvalidArgumentException(
                sprintf(
                    'Argument [$filename] for %s must be valid path to file',
                    __METHOD__
                )
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
    final public static function create(string $content, string $filename, ?FilesystemOperator $system = null,
                                  ?StreamFactoryInterface $streamFactory = null,
                                  int $bytesPerIteration = 1024
    ): self
    {
        $system !== null ?: $system = FileSystemFactory::makeSystem();
        $system->write($filename, $content);

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
    private function getFileHandler()
    {
        if ($this->fileHandler == null)
        {
            $this->fileHandler = $this->system->readStream($this->filename);
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

    private function normalizePath(string $path): string
    {
        return str_replace(['\\'], '/', $path);
    }

    /**
     * @return StreamIterator
     */
    final public function getIterator(int $bytesPerIteration = null): StreamIterator
    {
        return new StreamIterator($this, $bytesPerIteration ?? $this->bytesPerIteration);
    }

    private function getSegments(): array
    {
        return explode('/', $this->filename);
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
        $this->filename = $filename;
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
    final public function getMimeType(): string
    {
        if ($this->mimeType == null)
        {
            return $this->mimeType = $this->system->mimeType($this->filename);
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
            return $this->extension = FileInfo::extension($this->filename, $this->system);
        }

        return $this->extension;
    }

    /**
     * @return int
     * @throws \League\Flysystem\FilesystemException
     */
    final public function getFileSize(): int
    {
        if ($this->filesize == null)
        {
            return $this->filesize = $this->system->fileSize($this->filename);
        }

        return $this->filesize;
    }

    /**
     * @return int
     * @throws \League\Flysystem\FilesystemException
     */
    final public function lastModified(): int
    {
        if ($this->lastModified == null)
        {
            $this->lastModified = $this->system->lastModified($this->filename);
        }

        return $this->lastModified;
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

        $this->system->move($this->filename, $destination);
        $this->filename = $destination;

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
        $this->system->copy($this->filename, $destination);
    }

    /**
     * @return array
     * @throws \League\Flysystem\FilesystemException
     */
    public function toArray(): array
    {
        return [
            'filename' => $this->filename,
            'extension' => $this->getExtension(),
            'mimeType' => $this->getMimeType(),
            'size' => $this->getFileSize()
        ];
    }

    /**
     * @inerhitDoc
     */
    final public function __toString(): string
    {
        return $this->system->read($this->filename);
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
        return $this->system->read($this->filename);
    }

    /**
     * @inerhitDoc
     */
    final public function getMetadata($key = null)
    {
        $this->getStream()->getMetadata($key);
    }
}
