<?php

namespace Bermuda\Flysystem;

use Bermuda\Iterator\StreamIterator;
use Bermuda\String\StringHelper;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use function error_get_last;
use function feof;
use function fseek;
use function ftell;
use function fwrite;
use function stream_get_contents;
use function stream_get_meta_data;
use function var_export;
use const SEEK_CUR;

class File extends AbstractFile implements StreamInterface
{
    /**
     * @var resource
     */
    private $fh = null;
    private ?bool $isWritable = null;
    private ?bool $isSeekable = null;
    private ?bool $isReadable = null;

    private ?string $extension = null;
    private ?string $mimeType = null;

    /**
     * @param string $filename
     * @param Flysystem $flysystem
     * @param int $bytesPerIteration
     * @throws FilesystemException
     */
    private function __construct(string      $filename, Flysystem $flysystem,
                                 private int $bytesPerIteration = 1024
    )
    {
        parent::__construct($filename, $flysystem);
        $this->setFh();
    }

    /**
     * @throws FilesystemException
     */
    private function setFh(): void
    {
        if ($this->fh == null) {
            $this->fh = $this->flysystem->readStream($this->location);
            $meta = stream_get_meta_data($this->fh);
            $this->isSeekable = $meta['seekable'] && 0 === fseek($this->fh, 0, SEEK_CUR);

            $this->isReadable = match ($meta['mode']) {
                'r', 'w+', 'r+', 'x+', 'c+', 'rb', 'w+b', 'r+b', 'x+b',
                'c+b', 'rt', 'w+t', 'r+t', 'x+t', 'c+t', 'a+' => true,
                default => false
            };

            $this->isWritable = match ($meta['mode']) {
                'w', 'w+', 'rw', 'r+', 'x+', 'c+', 'wb', 'w+b', 'r+b',
                'x+b', 'c+b', 'w+t', 'r+t', 'x+t', 'c+t', 'a', 'a+' => true,
                default => false
            };
        }
    }

    /**
     * @throws RuntimeException
     */
    final public function __clone(): void
    {
        throw new RuntimeException('This object is not cloneable');
    }

    /**
     * @param int|null $bytes
     * @return int
     */
    public function bytesPerIteration(?int $bytes = null): int
    {
        if ($bytes !== null) {
            return $this->bytesPerIteration = $bytes;
        }

        return $this->bytesPerIteration;
    }

    /**
     * @param string $string
     * @return int
     * @throws RuntimeException
     */
    final public function write($string): int
    {
        $this->isDetached();

        if (!$this->isWritable) {
            throw new RuntimeException('Cannot write to a non-writable file');
        }

        if (($length = @fwrite($this->fh, $string)) === false) {
            throw new RuntimeException('Unable to write to file: ' . (error_get_last()['message'] ?? ''));
        }

        return $length;
    }

    private function isDetached(): void
    {
        if ($this->fh === null) {
            throw new RuntimeException('File is detached');
        }
    }

    /**
     * @param int|null $bytesPerIteration
     * @return StreamIterator
     */
    final public function getIterator(int $bytesPerIteration = null): StreamIterator
    {
        return new StreamIterator($this, $bytesPerIteration ?? $this->bytesPerIteration);
    }

    /**
     * @param string|null $name
     * @return self
     * @throws FilesystemException
     */
    final public function rename(?string $name = null): self
    {
        if ($name === null) {
            $name = StringHelper::filename($this->getExtension());
        } elseif (!str_contains($name, '.')) {
            $name = sprintf('%s.%s', $name, $this->getExtension());
        }

        $location = $this->location->up()->append($name);

        $this->move($location);
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    final public function getExtension(): string
    {
        if ($this->extension == null) {
            return $this->extension = $this->flysystem->fileExtension($this->location);
        }

        return $this->extension;
    }

    /**
     * @param string $destination
     * @return self
     * @throws FilesystemException
     */
    final public function move(string $destination): self
    {
        $destination = new Location($destination);

        if ($this->flysystem->isDirectory($destination)) {
            $destination = $destination->append($this->basename());
        }

        $this->flysystem->move($this->location, $destination);
        $this->location = $destination;

        $this->setFh();
        $this->name = null;
        $this->path = null;

        return $this;
    }

    /**
     * @return string
     */
    public function getFilename(): string
    {
        return $this->location;
    }

    /**
     * @return Directory
     * @throws FilesystemException
     */
    public function getDirectory(): Directory
    {
        return $this->flysystem->open($this->location->up());
    }

    /**
     * @throws FilesystemException
     */
    final public function delete(): void
    {
        $this->flysystem->delete($this->location);
    }

    /**
     * @param string $destination
     * @param bool $destinationIsDir
     * @return static
     * @throws FilesystemException
     */
    final public function copy(string $destination, bool $destinationIsDir = false): self
    {
        $destination = new Location($destination);

        if ($destinationIsDir) {
            $destination = $destination->append($this->basename());
        }

        return self::create($destination, (string)$this, $this->flysystem, $this->bytesPerIteration);
    }

    /**
     * @param string|null $filename
     * @param string $content
     * @param Flysystem|null $system
     * @param int $bytesPerIteration
     * @return static
     * @throws FilesystemException
     */
    public static function create(?string $filename = null, string $content = '', Flysystem $system = null,
                                  int     $bytesPerIteration = 1024
    ): self
    {
        $system = self::system($system);

        if ($filename === null) {
            $extension = $system->extension($content, true);
            $filename = StringHelper::filename($extension);

            $system->write($filename, $content);
            return self::open($filename, $system, $bytesPerIteration);
        }

        try {
            return self::open($filename, $system, $bytesPerIteration);
        } catch (NoSuchFile) {
            $system->write($filename, $content);
            return self::open($filename, $system, $bytesPerIteration);
        }
    }

    /**
     * @param string $filename
     * @param Flysystem|null $system
     * @param int $bytesPerIteration
     * @return static
     * @throws FilesystemException
     * @throws Exceptions\NoSuchFile;
     */
    public static function open(
        string $filename, ?Flysystem $system = null,
        int    $bytesPerIteration = 1024
    ): self
    {
        if (!($system = self::system($system))->isFile($filename)) {
            throw new NoSuchFile($filename);
        }

        if ($system->isImage($filename)) {
            return new Image($filename, $system, $bytesPerIteration);
        }

        return new self($filename, $system, $bytesPerIteration);
    }

    /**
     * @return array
     * @throws FilesystemException
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
     * @return string
     */
    final public function getMimeType(): string
    {
        if ($this->mimeType == null) {
            return $this->mimeType = $this->flysystem->mimeType($this->location);
        }

        return $this->mimeType;
    }

    /**
     * @return string
     * @throws FilesystemException
     */
    final public function __toString(): string
    {
        if ($this->isSeekable) {
            $this->seek(0);
        }

        return $this->getContents();
    }

    /**
     * @inerhitDoc
     */
    final public function seek($offset, $whence = SEEK_SET): void
    {
        $this->isDetached();

        if (!$this->isSeekable) {
            throw new RuntimeException('File is not seekable');
        }

        if (fseek($this->fh, $offset, $whence) === -1) {
            throw new RuntimeException('Unable to seek to file position "' . $offset . '" with whence ' . var_export($whence, true));
        }
    }

    /**
     * @return string
     * @throws RuntimeException
     */
    final public function getContents(): string
    {
        $this->isDetached();

        if (($contents = @stream_get_contents($this->fh)) === false) {
            throw new RuntimeException('Unable to read file contents: ' . (error_get_last()['message'] ?? ''));
        }

        return $contents;
    }

    /**
     * @inerhitDoc
     */
    final public function close(): void
    {
        if ($this->fh !== null) {
            fclose($this->fh);
            $this->detach();
        }
    }

    /**
     * @inerhitDoc
     */
    final public function detach()
    {
        $fh = $this->fh;
        $this->fh =
        $this->isReadable =
        $this->isWritable =
        $this->isSeekable = null;

        return $fh;
    }

    /**
     * @inerhitDoc
     */
    final public function tell(): int
    {
        $this->isDetached();

        if (($pos = @ftell($this->fh)) === false) {
            throw new RuntimeException('Unable to determine stream position: ' . (error_get_last()['message'] ?? ''));
        }

        return $pos;
    }

    /**
     * @inerhitDoc
     */
    final public function eof(): bool
    {
        return $this->fh === null || feof($this->fh);
    }

    /**
     * @inerhitDoc
     */
    final public function isSeekable(): bool
    {
        return $this->isSeekable;
    }

    /**
     * @inerhitDoc
     */
    final public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * @inerhitDoc
     */
    final public function isWritable(): bool
    {
        return $this->isWritable;
    }

    /**
     * @inerhitDoc
     */
    final public function isReadable(): bool
    {
        return $this->isReadable;
    }

    /**
     * @inerhitDoc
     */
    final public function read($length): string
    {
        $this->isDetached();

        if (!$this->isWritable) {
            throw new RuntimeException('Cannot write to a non-writable file');
        }

        if ($length = @fwrite($this->fh, $length) === false) {
            throw new RuntimeException('Unable to write to file: ' . (error_get_last()['message'] ?? ''));
        }

        return $length;
    }

    /**
     * @inerhitDoc
     */
    final public function getMetadata($key = null)
    {
        if ($this->fh === null) {
            return $key ? null : [];
        }

        $meta = stream_get_meta_data($this->fh);
        return $key === null ? $meta : $meta[$key] ?? null;
    }
}
