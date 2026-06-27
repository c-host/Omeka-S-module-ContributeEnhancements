<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener\Factory;

use ContributeEnhancements\Listener\ArchiveSearchListener;
use ContributeEnhancements\Service\ArchiveService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ArchiveSearchListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ArchiveSearchListener($services->get(ArchiveService::class));
    }
}
