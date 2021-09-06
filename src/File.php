<?php

namespace Bermuda\Flysystem;

use Bermuda\String\Str;
use Bermuda\Iterator\StreamIterator;
use Bermuda\Utils\Header;
use Bermuda\Utils\Headers\ContentDisposition;
use Bermuda\Utils\MimeType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class File extends FlysystemData implements StreamInterface
{
    /**
     * @var resource
     */
    private $fileHandler = null;
    private ?StreamInterface $stream = null;

    private ?string $extension = null;
    private ?string $mimeType = null;

    /**
     * @param string $filename
     * @param Flysystem $flysystem
     * @param int $bytesPerIteration
     */
    private function __construct(string $filename, Flysystem $flysystem,
        private int $bytesPerIteration = 1024
    )
    {
        parent::__construct($filename, $flysystem);
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
     * @param Flysystem|null $system
     * @param int $bytesPerIteration
     * @return static
     * @throws \League\Flysystem\FilesystemException
     * @throws Exceptions\NoSuchFileException;
     */
    public static function open(
        string $filename, ?Flysystem $system = null,
        int $bytesPerIteration = 1024
    ): self
    {
        if (!($system = self::system($system))->isFile($filename))
        {
            throw new NoSuchFileException($filename);
        }

        if ($system->isImage($filename))
        {
            return new Image($filename, $system, $bytesPerIteration);
        }

        return new self($filename, $system, $bytesPerIteration);
    }

    /**
     * @param string|null $filename
     * @param string $content
     * @param Flysystem|null $system
     * @param int $bytesPerIteration
     * @return static
     * @throws \League\Flysystem\FilesystemException
     */
    public static function create(?string $filename = null, string $content = '', Flysystem $system = null,
                                  int $bytesPerIteration = 1024
    ): self
    {
        $system = self::system($system);
        
        if ($filename === null)
        {
            $extension = $system->extension($content, true);
            $filename  = Str::filename($extension);

            $system->getOperator()->write($filename, $content);
            return self::open($filename, $system, $bytesPerIteration);
        }

        try {
            return self::open($filename, $system, $bytesPerIteration);
        }

        catch (Exceptions\NoSuchFileException $e)
        {
            $system->getOperator()->write($filename, $content);
            return self::open($filename, $system, $bytesPerIteration);
        }
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
            $this->fileHandler = $this->flysystem->getOperator()->readStream($this->location);
        }

        return $this->fileHandler;
    }

    private function getStream(): StreamInterface
    {
        if ($this->stream == null)
        {
            $handler = $this->getFileHandler();
            $this->stream =$this->flysystem->getStreamFactory()->createStreamFromResource($handler);
        }

        return $this->stream;
    }

    /**
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws \League\Flysystem\FilesystemException
     */
    public function responde(ResponseInterface $response): ResponseInterface
    {
        $response->withHeader(Header::contentDescription, 'File-transfer')
            ->withHeader(Header::contentType, $this->getMimeType())
            ->withHeader(Header::contentDisposition, ContentDisposition::attachment($this->getName()))
            ->withHeader(Header::contentLength, $this->getSize())
            ->withHeader(Header::contentTransferEncoding, 'binary')
            ->withHeader(Header::expires, 0)
            ->withHeader(Header::cacheControl, 'must-revalidate')
            ->withHeader(Header::pragma, 'public')
            ->getBody()->write($this);

        return $response;
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

        $this->move($filename = $this->getPath() . static::separator . $name);
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
            return $this->mimeType = $this->flysystem->mimeType($this->location);
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
            return $this->extension = $this->flysystem->extension($this->location);
        }

        return $this->extension;
    }

    /**
     * @throws \League\Flysystem\FilesystemException
     */
    final public function delete(): void
    {
        $this->flysystem->getOperator()->delete($this->location);
    }

    /**
     * @return int
     * @throws \League\Flysystem\FilesystemException
     */
    final public function getSize(): int
    {
        return $this->flysystem->getOperator()->fileSize($this->location);
    }

    /**
     * @param string $destination
     * @throws \League\Flysystem\FilesystemException
     */
    final public function move(string $destination): void
    {
        if ($this->flysystem->isDirectory($destination))
        {
            $destination = rtrim($destination, '\/') .
                static::separator . $this->getName();
        }

        $this->flysystem->getOperator()->move($this->location, $destination);
        $this->location = $destination;

        $this->fileHandler = null;
        $this->stream = null;
        $this->name = null;
        $this->path = null;
    }

    /**
     * @param string $destination
     * @param bool $destinationIsDir
     * @throws \League\Flysystem\FilesystemException
     */
    final public function copy(string $destination, bool $destinationIsDir = false): self
    {
        if ($destinationIsDir)
        {
            $destination = $this->normalizePath($destination) . static::separator . $this->getName();
        }

        return self::create($destination, $this->getContents(), $this->flysystem, $this->bytesPerIteration);
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
            'size' => $this->getSize(),
            'modified' => $this->lastModified(),
        ];
    }

    /**
     * @inerhitDoc
     */
    final public function __toString(): string
    {
        return $this->flysystem->getOperator()->read($this->location);
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
        return $this->flysystem->getOperator()->read($this->location);
    }

    /**
     * @inerhitDoc
     */
    final public function getMetadata($key = null)
    {
        $this->getStream()->getMetadata($key);
    }
}
