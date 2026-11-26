<?php

namespace AdUnblock\ServerTag\Symfony\DependencyInjection;

use AdUnblock\ServerTag\Symfony\Twig\AdunblockExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class AdunblockServerTagExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // Register the Twig extension
        $definition = new Definition(AdunblockExtension::class);
        $definition->setArguments([
            new Reference('http_client'),
            new Reference('cache.app'),
            // The current request's host is the default ?domain=.
            new Reference('request_stack', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);
        $definition->addTag('twig.extension');

        $container->setDefinition('adunblock.server_tag.twig_extension', $definition);
    }
}
