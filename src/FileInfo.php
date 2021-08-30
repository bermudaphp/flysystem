<?php

namespace Bermuda\Files;

use League\Flysystem\FilesystemOperator;

final class FileInfo
{
    /**
     * @param string $path
     * @param FilesystemOperator|null $system
     * @throws \League\Flysystem\FilesystemException
     */
    public function __construct(private string $path, private ?FilesystemOperator $system = null)
    {
        $this->system = FileSystemFactory::makeSystem();

        if ($this->system->fileExists($this->path))
        {
            throw new \InvalidArgumentException(
                sprintf('Argument [path] for %s must be valid path to file or directory',
                    __METHOD__
                )
            );
        }
    }

    /**
     * @return FilesystemOperator
     */
    public function getSystem(): FilesystemOperator
    {
        return $this->system;
    }

    /**
     * @param string $filename
     * @param bool $strict
     * @return string
     */
    public static function extension(string $filename, bool $strict): string
    {
        return !$strict ? pathinfo($filename, PATHINFO_EXTENSION)
            : (new \finfo(FILEINFO_EXTENSION))->file($filename);
    }

    /**
     * @param string $filename
     * @param bool $strict
     * @return string
     */
    public static function filesize(string $filename): int
    {
        return filesize($filename);
    }

    /**
     * @param string $filename
     * @return string
     */
    public static function mimeType(string $filename): string
    {
        $type = @(new \finfo(FILEINFO_MIME_TYPE))->file($filename);

        if ($type === false)
        {
            throw new \RuntimeException(error_get_last()['message']);
        }

        return $type;
    }

    /**
     * @param bool $strict
     * @return string
     */
    public function getFileExtension(bool $strict = true): string
    {
        return self::extension($this->path, $strict);
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function isFile(string $path): bool
    {
        return is_file($path);
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function isDir(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function isImg(string $path): bool
    {
        return self::isFile($path) && self::isImageMimeType(self::mimeType($path));
    }

    private static function isImageMimeType(string $type): bool
    {
        return str_contains(strtolower($type), 'image');
    }

    /**
     * @param string $path
     * @return bool
     */
    public static function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public function isDirectory(): bool
    {
        return $this->system->mimeType($this->path) == 'directory';
    }

    /**
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public function isImage(): bool
    {
        return self::isImageMimeType($this->getMimeType());
    }

    /**
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
    public function getMimeType(): string
    {
        return $this->system->mimeType($this->path);
    }
}
