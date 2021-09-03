<?php

namespace Bermuda\Flysystem;

use Bermuda\Flysystem\Validation\UploadedFileValidationExtension;
use Bermuda\Utils\Header;
use Bermuda\Utils\MimeType;
use Bermuda\String\Json;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Bermuda\Flysystem\Validation\UploadedFileValidator;
use Bermuda\Flysystem\Validation\UploadedFileValidatorInterface;

final class UploadedFilesHandler implements RequestHandlerInterface, FileProcessorInterface
{
    private string $tmpDir = '/uploads/tmp_';

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private ?Flysystem $flysystem = null,
        private ?UploadedFileValidatorInterface $validator = null
    )
    {
        $this->flysystem = $flysystem ?? new Flysystem();
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
    public function tmpDir(?string $dir = null): string
    {
        if ($dir != null)
        {
            $this->tmpDir = rtrim($dir, '\/');
        }

        return $this->tmpDir;
    }

    /**
     * @param ContainerInterface $container
     * @return static
     */
    public static function fromContainer(ContainerInterface $container): self
    {
        return new self(
            $container->get(ResponseFactoryInterface::class),
            $container->get(Flysystem::class),
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
                        $filesIDs[] = $this->processFile($this->tmpDir, $value);
                    }

                    continue;
                }

                $filesIDs[] = $this->processFile($this->tmpDir, $file);
            }
        }

        catch (UploadedFileValidationExtension $e)
        {
            return $this->handleValidationException($e);
        }

        if (count($filesIDs) > 1)
        {
            $contentType = MimeType::applicationJson;
            $filesIDs = Json::encode($filesIDs);
        }

        ($response = $this->responseFactory->createResponse(201)
            ->withHeader(Header::contentType, $contentType ?? MimeType::plain))
            ->getBody()->write(is_string($filesIDs)
                ? $filesIDs : $filesIDs[0]);

        return $response;
    }

    /**
     * @param string $path
     * @param UploadedFileInterface $uploadedFile
     * @return string
     * @throws \League\Flysystem\FilesystemException
     */
    public function processFile(string $location, UploadedFileInterface $uploadedFile): string
    {
        $this->validator->validate($uploadedFile);

        $filename = $location . '/' . $uploadedFile->getClientFilename();
        $this->flysystem->write($filename, (string) $uploadedFile->getStream());

        return $uploadedFile->getClientFilename();
    }

    /**
     * @param string $location
     * @param string[]|string $filesIDs
     * @return File[]
     * @throws \League\Flysystem\FilesystemException
     */
    public function moveUploadedFiles(string $location, array|string $filesIDs): array
    {
        $files = [];

        is_array($filesIDs) ?: $filesIDs = [$filesIDs];

        $this->flysystem->exists($location) ?: $this->flysystem->createDirectory($location);

        foreach ($filesIDs as $fileID)
        {
            ($files[] =$this->flysystem->openFile($this->tmpDir . '/' . $fileID))
                ->move($location);
        }

        return $files;
    }

    private function handleValidationException(UploadedFileValidationExtension $e): ResponseInterface
    {
        ($r = $this->responseFactory->createResponse(400)
            ->withHeader(Header::contentType, MimeType::applicationJson))
        ->getBody()
            ->write(json_encode(['errors' => $e->getErrors()]));

        return $r;
    }
}
