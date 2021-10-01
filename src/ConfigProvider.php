<?php

namespace Bermuda\Flysystem;

use League\Flysystem\FilesystemOperator;
use Bermuda\Detector\ExtensionDetector;
use Psr\Http\Message\StreamFactoryInterface;
use function Bermuda\Config\cget;

final class ConfigProvider extends \Bermuda\Config\ConfigProvider
{
    /**
     * @inheritDoc
     */
    protected function getFactories(): array
    {
        return [
            Flysystem::class => static fn(ContainerInterface $container) => new Flysystem(
                cget($container, FilesystemOperator::class), cget($container, StreamFactoryInterface::class),
                cget($container, ExtensionDetector::class)
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
