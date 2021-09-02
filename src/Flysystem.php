<?php

namespace Bermuda\Flysystem;

use Bermuda\String\Str;
use Bermuda\String\Stringy;
use League\Flysystem\FilesystemOperator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @mixin FilesystemOperator
 * @mixin StreamFactoryInterface
 */
final class Flysystem
{
    public function __construct(private ?FilesystemOperator $operator = null,
                                private ?StreamFactoryInterface $streamFactory = null
    )
    {
        $this->operator = $operator ?? FileSystemFactory::makeSystem();
        $this->streamFactory = $this->streamFactory ?? new Psr17Factory();
    }

    public function __call(string $name, array $arguments)
    {
        if (($stringy = new Stringy($name))->start(2)->equals('is'))
        {
            return $this->isFile($arguments[0], $stringy->slice(2));
        }

        if (method_exists($this->operator, $name))
        {
            return call_user_func_array([$this->operator, $name], $arguments);
        }

        if (method_exists($this->streamFactory, $name))
        {
            return call_user_func_array([$this->operator, $name], $arguments);
        }

        throw new \BadMethodCallException(
            sprintf('Method %s doesnt exists from %s', $name, __CLASS__)
        );
    }

    /**
     * @return FilesystemOperator
     */
    public function getOperator(): FilesystemOperator
    {
        return $this->operator;
    }

    /**
     * @param string $location
     * @return File|Directory|null
     * @throws \League\Flysystem\FilesystemException
     */
    public function open(string $location): File|Directory|null
    {
        try {
            $this->openFile($location);
        }

        catch (\InvalidArgumentException $thr)
        {
            try {
                return $this->openDirectory($location);
            }

            catch (\InvalidArgumentException $e)
            {
                return null;
            }
        }

        return null;
    }

    /**
     * @return StreamFactoryInterface
     */
    public function getStreamFactory(): StreamFactoryInterface
    {
        return $this->streamFactory;
    }

    /**
     * @param string $filenameOrContent
     * @param bool $isContent
     * @return string
     */
    public function extension(string $filenameOrContent, bool $isContent = false): string
    {
        return $this->finfoBuffer(FILEINFO_EXTENSION, $filenameOrContent, $isContent);
    }

    /**
     * @param string $filenameOrContent
     * @param bool $isContent
     * @return string
     */
    public function mimeType(string $filenameOrContent, bool $isContent = false): string
    {
        return $this->finfoBuffer(FILEINFO_MIME_TYPE, $filenameOrContent, $isContent);
    }

    /**
     * @param string $mode
     * @param string $filenameOrContent
     * @return string
     */
    private function finfoBuffer(string $mode, string $filenameOrContent, bool $isContent = false): string
    {
        $content = $isContent ? $filenameOrContent : $this->operator->read($filenameOrContent);
        return (new \finfo($mode))->buffer($content);
    }

    /**
     * @param string $path
     * @return bool
     */
    public function isDirectory(string $path): bool
    {
        try {
            return strtolower($this->operator->mimeType($path)) === 'directory';
        }
        catch (\League\Flysystem\FilesystemException $e)
        {
            return false;
        }
    }

    /**
     * @param string $filenameOrContent
     * @param bool $isContent
     * @return bool
     */
    public function isImage(string $filenameOrContent, bool $isContent = false): bool
    {
        try {
            $mimeType = $this->mimeType($filenameOrContent, $isContent);
            return str_contains(strtolower($mimeType), 'image');
        }
        catch (\League\Flysystem\FilesystemException $e)
        {
            return false;
        }
    }

    /**
     * @param string $content
     * @param string $type
     * @return bool
     */
    public function is(string $content, string $type): bool
    {
        return $this->mimeTypeComparison($type, $content. true);
    }

    /**
     * @param string|null $filename
     * @param string $content
     * @return File
     * @throws \League\Flysystem\FilesystemException
     */
    public function createFile(?string $filename = null, string $content = ''): File
    {
        return File::create($filename, $content, $this);
    }

    /**
     * @param string $location
     * @return Directory
     * @throws \League\Flysystem\FilesystemException
     */
    public function createDirectory(string $location): Directory
    {
        return Directory::create($location, $this);
    }

    /**
     * @param string|null $filename
     * @param string $content
     * @return File
     * @throws \League\Flysystem\FilesystemException
     */
    public function openFile(?string $filename = null): File
    {
        return File::open($filename, $this);
    }

    /**
     * @param string $location
     * @return Directory
     * @throws \League\Flysystem\FilesystemException
     */
    public function openDirectory(string $location): Directory
    {
        return Directory::open($location, $this);
    }

    /**
     * @param string $path
     * @param callable|null $filter
     * @return array
     * @throws \League\Flysystem\FilesystemException
     */
    public function listContents(string $path = '/', callable $filter = null): array
    {
        $list = [];

        foreach ($this->operator->listContents($path) as $attr)
        {
            $flysystemData = $attr->isFile() ?
                File::open($attr->path(), $this)
                : Directory::open($attr->path(), $this);

            if ($filter === null || $filter($flysystemData))
            {
                $list[] = $flysystemData;
            }
        }

        return $list;
    }

    /**
     * @param string $path
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public function isFile(string $path, ?string $type = null): bool
    {
        if ($type === null)
        {
            return $this->operator->fileExists($path);
        }

        return $this->operator->fileExists($path) && $this->mimeTypeComparison($type, $path);
    }

    /**
     * @param string $path
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public function exists(string $path): bool
    {
        return $this->operator->fileExists($path)
            || $this->isDirectory($path);
    }

    private function mimeTypeComparison(string $type, string $contentOrFile, bool $isContent = false): bool
    {
        return Str::contains($this->mimeType($contentOrFile, $isContent), $type);
    }
}
