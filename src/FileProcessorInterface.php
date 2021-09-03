<?php

namespace Bermuda\Flysystem;

use League\Flysystem\FilesystemException;
use Psr\Http\Message\UploadedFileInterface;
use Bermuda\Flysystem\Validation\UploadedFileValidationExtension;

interface FileProcessorInterface
{
    /**
     * @param string $location
     * @param UploadedFileInterface $uploadedFile
     * @return string
     * @throws UploadedFileValidationExtension
     * @throws FilesystemException
     */
    public function processFile(string $location, UploadedFileInterface $uploadedFile): string ;

    /**
     * @param string $location
     * @param string[]|string $filesIDs
     * @return File[]
     * @throws FilesystemException
     */
    public function moveUploadedFiles(string $location, array|string $filesIDs): array ;
}
