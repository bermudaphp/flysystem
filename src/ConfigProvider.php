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
            Flysystem::class => static fn() => new Flysystem,
            FileProcessorInterface::class => 'Bermuda\Flysystem\UploadedFilesHandler::fromContainer'
        ];
    }
    
    /**
     * @inheritDoc
     */
    protected function getAliases(): array
    {
        return [UploadedFilesHandler::class => FileProcessorInterface::class];
    }
}
