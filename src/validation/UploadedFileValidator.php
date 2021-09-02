<?php

namespace Bermuda\Flysystem\Validation;

use Bermuda\Flysystem\Flysystem;
use Psr\Http\Message\UploadedFileInterface;

final class UploadedFileValidator implements UploadedFileValidatorInterface
{
    public const mimeType  = 'mimeType';
    public const fileSize  = 'fileSize';
    public const extension = 'extension';

    public const MAX_FILESIZE_1MB = 8e+6;
    public const MAX_FILESIZE_3MB = 2.4e+7;
    public const MAX_FILESIZE_5MB = 4e+7;
    public const MAX_FILESIZE_10MB = 8e+7;
    public const MAX_FILESIZE_15MB = 1.2e+8;
    public const MAX_FILESIZE_20MB = 1.6e+8;
    public const MAX_FILESIZE_25MB = 2e+8;

    /**
     * @param array $config
     * @return UploadedFileValidator
     */
    public static function instantiate(array $config): self
    {
        return new self(
            $config[self::mimeType] ?? [],
            $config[self::extension] ?? [],
            $config[self::fileSize] ?? null,
        );
    }

    public function __construct(
        private string|array $mimeType,
        private string|array $extension,
        private ?int         $filesize,
    )
    {
        $this->mimeType = is_array($mimeType) ? $mimeType : [$mimeType];
        $this->extension = is_array($extension) ? $extension : [$extension];
    }

   /**
     * @param UploadedFileInterface $uploadedFile
     * @throws \League\Flysystem\FilesystemException
     */
    public function validate(UploadedFileInterface $uploadedFile): void
    {
        $uri = $uploadedFile->getStream()->getMetadata('uri');

        $pathInfo = pathinfo($uri);
        
        $flysystem = new Flysystem(FileSystemFactory::makeSystem($pathInfo['dirname']));
        $fileInfo = $flysystem->openFile($pathInfo['basename'])->toArray();                       

        $errors = [];

        if ($this->mimeType != [] && !in_array($mimeType = $fileInfo['mimeType'], $this->mimeType))
        {
            $errors[] = sprintf('Invalid mimeType: %s, allowed types: %s; File: %s',
                $mimeType, implode(',', $this->mimeType), $uploadedFile->getClientFilename()
            );
        }

        if ($this->extension != [] && in_array($ext = $fileInfo['extension'], $this->extension))
        {
            $errors[] = sprintf('Invalid file extension: %s, allowed extensions: %s; File: %s',
                $ext, implode(',', $this->extension), $uploadedFile->getClientFilename()
            );
        }

        if ($this->filesize != null && $fileInfo['size'] > $this->filesize)
        {
            $errors[] = 'Maximum file size exceeded; File: ' . $uploadedFile->getClientFilename();
        }

        $errors === [] ?: throw new UploadedFileValidationExtension($errors, $uploadedFile);
    }
}
