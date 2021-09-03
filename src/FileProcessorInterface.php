<?php

namespace Bermuda\Flysystem;

use League\Flysystem\FilesystemException;
use Psr\Http\Message\UploadedFileInterface;
use Bermuda\Flysystem\Validation\UploadedFileValidationExtension;

interface FileProcessorInterface
{
    /**
     * @param string $path
     * @param UploadedFileInterface $uploadedFile
     * @return string
     * @throws UploadedFileValidationExtension
     * @throws FilesystemException
     */
    public function processFile(string $path, UploadedFileInterface $uploadedFile): string ;

    /**
     * @param string[]|string $filesIDs
     * @return File[]
     * @throws FilesystemException
     */
    public function moveUploadedFiles(array|string $filesIDs): array ;
}
