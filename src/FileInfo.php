<?php

namespace Bermuda\Files;

use League\Flysystem\FilesystemOperator;

final class FileInfo
{
    /**
     * @param string $filename
     * @param FilesystemOperator $system
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
    public static function extension(string $filename, FilesystemOperator $system): string
    {
        $content = $system->read($filename);
        return (new \finfo(FILEINFO_EXTENSION))->buffer($content);
    }

    /**
     * @param string $path
     * @param FilesystemOperator $system
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public static function isDirectory(string $path, FilesystemOperator $system): bool
    {
        return strtolower($system->mimeType($path)) == 'directory';
    }

    /**
     * @param string $filename
     * @param FilesystemOperator $system
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public static function isImage(string $filename, FilesystemOperator $system): bool
    {
        $mimeType = $system->mimeType($filename);
        return str_contains(strtolower($mimeType), 'image');
    }

    /**
     * @param string $path
     * @param FilesystemOperator $system
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public static function exists(string $path, FilesystemOperator $system): bool
    {
        return $system->fileExists($path) || self::isDirectory($path, $system);
    }
}
