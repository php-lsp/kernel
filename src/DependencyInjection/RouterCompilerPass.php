<?php

declare(strict_types=1);

namespace Lsp\Kernel\DependencyInjection;

use Composer\InstalledVersions;
use Lsp\Contracts\Router\RouterInterface;
use Lsp\Kernel\Attribute\AsController;
use Lsp\Router\Builder\AttributeCollector;
use Lsp\Router\Builder\BuilderInterface;
use Lsp\Router\Builder\CompoundCollector;
use Lsp\Router\Builder\RouteCollector;
use Lsp\Router\MemoizedRouter;
use Lsp\Router\Router;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class RouterCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!self::isPackageInstalled() || $container->has(RouterInterface::class)) {
            return;
        }

        $this->registerRouterAutoconfigureTags($container);
        $this->registerRouterBuilder($container);
        $this->registerRouter($container);
    }

    private static function isPackageInstalled(): bool
    {
        return \class_exists(InstalledVersions::class)
            && InstalledVersions::isInstalled('php-lsp/router');
    }

    private function registerRouterAutoconfigureTags(ContainerBuilder $container): void
    {
        $container->registerAttributeForAutoconfiguration(
            attributeClass: AsController::class,
            configurator: static function (ChildDefinition $definition, AsController $attr): void {
                $definition->addTag($attr->lazy ? 'lsp.controller.lazy' : 'lsp.controller.instance');
                $definition->addTag('lsp.controller');
            },
        );
    }

    private function registerRouterBuilder(ContainerBuilder $container): void
    {
        $container->register(AttributeCollector::class, AttributeCollector::class)
            ->addTag('lsp.router.collector');

        $container->register(RouteCollector::class, RouteCollector::class)
            ->addTag('lsp.router.collector');

        $container->register(BuilderInterface::class, CompoundCollector::class)
            ->addArgument(new TaggedIteratorArgument('lsp.router.collector'));
    }

    private function registerRouter(ContainerBuilder $container): void
    {
        $container->register(RouterInterface::class, Router::class)
            ->setFactory([new Reference(BuilderInterface::class), 'build']);

        $container->register(MemoizedRouter::class, MemoizedRouter::class)
            ->setDecoratedService(RouterInterface::class)
            ->addArgument(new Reference('.inner'));
    }
}
