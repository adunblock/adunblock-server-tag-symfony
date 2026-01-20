<?php

namespace AdUnblock\ServerTag\Symfony;

use AdUnblock\ServerTag\Symfony\DependencyInjection\AdunblockServerTagExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AdunblockServerTagBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new AdunblockServerTagExtension();
    }
}