<?php declare(strict_types=1);

namespace ContributeEnhancements\Service\Factory;

use ContributeEnhancements\Service\ArchiveService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ArchiveServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ArchiveService($services->get('Omeka\Connection'));
    }
}
