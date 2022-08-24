<?php

declare(strict_types=1);

use Rector\Utils\RuleDocGenerator\Category\RectorCategoryInferer;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $rectorConfig): void {
    $services = $rectorConfig->services();
    $services->set(RectorCategoryInferer::class);

    $services->set(\Rector\Utils\RuleDocGenerator\PostRectorOutFilter::class);
};
