<?php

namespace Bermuda\Flysystem;

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

    /**
     * @param string $mode
     * @param string $filenameOrContent
     * @param FilesystemOperator|null $system
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
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
    public static function isDirectory(string $path, ?FilesystemOperator $system = null): bool
    {
        return $system === null ? is_dir($path) : strtolower($system->mimeType($path)) == 'directory';
    }

    /**
     * @param string $filenameOrContent
     * @param FilesystemOperator|null $system
     * @throws \League\Flysystem\FilesystemException
     * @return bool
     */
    public static function isImage(string $filenameOrContent, ?FilesystemOperator $system = null): bool
    {
        $mimeType = self::mimeType($filenameOrContent, $system);
        return str_contains(strtolower($mimeType), 'image');
    }

    /**
     * @param string $path
     * @param FilesystemOperator $system
     * @return bool
     * @throws \League\Flysystem\FilesystemException
     */
    public static function exists(string $path, ?FilesystemOperator $system = null): bool
    {
        return $system === null ? file_exists($path) :
            $system->fileExists($path) || self::isDirectory($path, $system);
    }
}
