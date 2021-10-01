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
            Flysystem::class => static fn(ContainerInterface $c) => new Flysystem(
                cget($c, FilesystemOperator::class), cget($c, StreamFactoryInterface::class),
                cget($c, ExtensionDetector::class)
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
