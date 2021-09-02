<?php

namespace Bermuda\Files;

use League\Flysystem\FilesystemOperator;

final class FileInfo
{
    /**
     * @param string $filenameOrContent
     * @param FilesystemOperator|null $system
     * @return string
     */
    public static function extension(string $filenameOrContent, ?FilesystemOperator $system = null): string
    {
        return self::finfoBuffer(FILEINFO_EXTENSION, $filenameOrContent, $system);
    }

    /**
     * @param string $filenameOrContent
     * @param FilesystemOperator|null $system
     * @return string
     */
    public static function mimeType(string $filenameOrContent, ?FilesystemOperator $system = null): string
    {
        return self::finfoBuffer(FILEINFO_MIME_TYPE, $filenameOrContent, $system);
    }

    private static function finfoBuffer(
        string $mode, string
        $filenameOrContent,
        ?FilesystemOperator $system = null): string
    {
        $content = $system === null ? $filenameOrContent : $system->read($filenameOrContent);
        return (new \finfo($mode))->buffer($content);
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
