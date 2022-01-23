<?php

namespace Bermuda\Flysystem;

use Bermuda\Detector\FinfoDetector;
use Bermuda\Detector\ExtensionDetector;
use League\Flysystem\FilesystemOperator;
use Psr\Container\ContainerInterface;
use Bermuda\Config\ConfigProvider as AbstractProvider;

final class ConfigProvider extends AbstractProvider
{
    /**
     * @inheritDoc
     */
    protected function getFactories(): array
    {
        return [
            Flysystem::class => static fn(ContainerInterface $container) => new Flysystem(
                $container->get(FilesystemOperator::class), $container->get(ExtensionDetector::class)
            ),
            ExtensionDetector::class => static fn() => new FinfoDetector(),
            FilesystemOperator::class => static fn() => OperatorFactory::makeLocal()
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
