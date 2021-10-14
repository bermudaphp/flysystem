<?php

namespace Bermuda\Flysystem;

use Bermuda\Detector\FinfoDetector;
use Bermuda\Detector\ExtensionDetector;
use League\Flysystem\FilesystemOperator;
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
                cget($container, FilesystemOperator::class, 'Bermuda\Flysystem\OperatorFactory::makeLocal', true),
                cget($container, ExtensionDetector::class, static fn() => new FinfoDetector(), true)
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
