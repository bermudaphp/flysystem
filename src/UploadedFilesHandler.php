<?php

namespace Bermuda\Files;

use Bermuda\Utils\Header;
use Bermuda\Utils\ContentType;
use League\Flysystem\FilesystemOperator;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class UploadedFilesHandler implements RequestHandlerInterface
{
    private FilesystemOperator $system;
    private ResponseFactoryInterface $responseFactory;
    private UploadedFileValidatorInterface $validator;

    private static string $tmpDir = '/uploads/tmp_';
    private static string $storageDir = '/uploads';

    public function __construct(
        FilesystemOperator $system,
        ResponseFactoryInterface $responseFactory,
        UploadedFileValidatorInterface $validator = null
    )
    {
        $this->system = $system;
        $this->responseFactory = $responseFactory;
        $this->validator = $validator ??
            UploadedFileValidator::instantiate([
                UploadedFileValidator::mimeType => [
                    MimeType::jpeg,
                    MimeType::png
                ],
                UploadedFileValidator::fileSize =>
                    UploadedFileValidator::MAX_FILESIZE_5MB
            ]);
    }

    /**
     * @param string|null $dir
     * @return string
     */
    public static function tmpDir(?string $dir = null): string
    {
        if ($dir != null)
        {
            self::$tmpDir = rtrim($dir, '\/');
        }

        return self::$tmpDir;
    }

    /**
     * @param string|null $dir
     * @return string
     */
    public static function storageDir(?string $dir = null): string
    {
        if ($dir != null)
        {
            self::$storageDir = rtrim($dir, '\/');
        }

        return self::$storageDir;
    }

    /**
     * @param ContainerInterface $container
     * @return static
     */
    public static function fromContainer(ContainerInterface $container): self
    {
        return new self(
            $container->get(FilesystemOperator::class),
            $container->get(ResponseFactoryInterface::class),
            $container->has(UploadedFileValidatorInterface::class) ?
                $container->get(UploadedFileValidatorInterface::class) : null,
        );
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws \League\Flysystem\FilesystemException
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $files = $request->getUploadedFiles();
        $filesIDs = [];

        try
        {
            foreach ($files as $file)
            {
                if (is_array($file))
                {
                    foreach ($file as $value)
                    {
                        $filesIDs[] = $this->handleFile(self::$tmpDir, $value);
                    }

                    continue;
                }

                $filesIDs[] = $this->handleFile(self::$tmpDir, $file);
            }
        }

        catch (UploadedFileValidationExtension $e)
        {
            return $this->handleValidationException($e);
        }

        if (count($filesIDs) > 1)
        {
            $contentType = MimeType::applicationJson;
            $filesIDs = \json_encode($filesIDs);
        }

        ($response = $this->responseFactory->createResponse(201)
            ->withHeader(Header::contentType, $contentType ?? MimeType::plain))
            ->getBody()->write(is_string($filesIDs)
                ? $filesIDs : $filesIDs[0]);

        return $response;
    }

    /**
     * @param string $path
     * @param UploadedFileInterface $file
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
    private function handleFile(string $path, UploadedFileInterface $file): string
    {
        $this->validator->validate($file);

        $filename = $path . '/' . $file->getClientFilename();
        $this->system->write($filename, (string) $file->getStream());

        return $file->getClientFilename();
    }

    /**
     * @param string[]|string $filesIDs
     * @return File[]
     * @throws \League\Flysystem\FilesystemException
     */
    public function moveUploadedFiles(array|string $filesIDs): array
    {
        $files = [];

        is_array($filesIDs) ?: $filesIDs = [$filesIDs];
        
        if (!$this->system->fileExists(self::$storageDir))
        {
            $this->system->createDirectory(self::$storageDir);
        }

        foreach ($filesIDs as $fileID)
        {
            ($files[] = File::open(self::$tmpDir . '/' . $fileID, $this->system))
                ->move(self::$storageDir);
        }

        return $files;
    }

    private function handleValidationException(UploadedFileValidationExtension $e): ResponseInterface
    {
        ($r = $this->responseFactory->createResponse(400)
            ->withHeader(Header::contentType, MimeType::applicationJson))
                ->getBody()
                    ->write(\json_encode([
                        'errors' => $e->getErrors()
                    ]));
        
        return $r;
    }
}
