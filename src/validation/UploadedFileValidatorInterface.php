<?php

namespace Bermuda\Flysystem\Validation;

use Psr\Http\Message\UploadedFileInterface;

interface UploadedFileValidatorInterface
{
    /**
     * @param UploadedFileInterface $file
     * @throws UploadedFileValidationExtension
     */
    public function validate(UploadedFileInterface $file): void ;
}
