<?php

namespace Bermuda\Flysystem;

final class ConfigProvider extends \Bermuda\Config\ConfigProvider
{
    /**
     * @inheritDoc
     */
    protected function getFactories(): array
    {
        return [
            Flysystem::class => static fn(ContainerInterface $c) => new Flysystem(
                cget(FilesystemOperator::class),
                cget(StreamFactoryInterface::class),
                cget(ExtensionDetector::class)
            ),
            FileProcessorInterface::class => 'Bermuda\Flysystem\FileUploadHandler::fromContainer'
        ];
    }
    
    /**
     * @inheritDoc
     */
    protected function getAliases(): array
    {
        return [
            'fs' => Flysystem::class,
            FileUploadHandler::class => FileProcessorInterface::class
        ];
    }
}
