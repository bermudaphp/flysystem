<?php

namespace Bermuda\Flysystem;

use League\Flysystem\FilesystemOperator;
use Bermuda\Detector\ExtensionDetector;
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
                cget($container, FilesystemOperator::class), cget($container, ExtensionDetector::class)
            )
        ];
    }
    
    /**
     * @inheritDoc
     */
    protected function getAliases(): array
    {
        return ['fs' => Flysystem::class];
    }
}
