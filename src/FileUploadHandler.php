<?php

namespace Bermuda\Flysystem;

use Bermuda\Utils\Header;
use Bermuda\String\Json;
use Bermuda\Utils\Types\Application;
use Bermuda\Utils\Types\Text;
use Bermuda\Utils\Types\Image as ImageType;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Bermuda\Flysystem\Validation\UploadedFileValidator;
use Bermuda\Flysystem\Validation\UploadedFileValidatorInterface;
use Bermuda\Flysystem\Validation\UploadedFileValidationExtension;

final class FileUploadHandler implements RequestHandlerInterface, FileProcessorInterface
{
    private Location $location;

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
                    ImageType::jpeg, ImageType::png
                ],
                UploadedFileValidator::fileSize =>
                    UploadedFileValidator::MAX_FILESIZE_5MB
            ]);

        $this->location = new Location('/tmp_');
    }

    /**
     * @param string|null $dir
     * @return string
     */
    public function tmpDir(?string $dir = null): string
    {
        if ($dir != null)
        {
            $this->location = new Location($dir);
        }

        return $this->location;
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
                        $filesIDs[] = $this->processFile($this->location, $value);
                    }

                    continue;
                }

                $filesIDs[] = $this->processFile($this->location, $file);
            }
        }

        catch (UploadedFileValidationExtension $e)
        {
            return $this->handleValidationException($e);
        }

        if (count($filesIDs) > 1)
        {
            $contentType = Application::json;
            $filesIDs = Json::encode($filesIDs);
        }

        ($response = $this->responseFactory->createResponse(201)
            ->withHeader(Header::contentType, $contentType ?? Text::plain))
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

        $filename = (new Location($location))->append($uploadedFile->getClientFilename());
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
            ($files[] =$this->flysystem->openFile($this->location->append($fileID)))
                ->move($location);
        }

        return $files;
    }

    private function handleValidationException(UploadedFileValidationExtension $e): ResponseInterface
    {
        ($response = $this->responseFactory->createResponse(400)
            ->withHeader(Header::contentType, Application::json))
        ->getBody()
            ->write(json_encode(['errors' => $e->getErrors()]));

        return $response;
    }
}
