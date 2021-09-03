<?php

namespace Bermuda\Flysystem\Validation;

use Psr\Http\Message\UploadedFileInterface;

final class UploadedFileValidationExtension extends \RuntimeException
{
    public function __construct(private array $errors,
                                private UploadedFileInterface $uploadedFile
    )
    {
        parent::__construct('Uploaded file validation is failed', 400);
    }

    /**
     * @return UploadedFileInterface
     */
    public function getUploadedFile(): UploadedFileInterface
    {
        return $this->uploadedFile;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
