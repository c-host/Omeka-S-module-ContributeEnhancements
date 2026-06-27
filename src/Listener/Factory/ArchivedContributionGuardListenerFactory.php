<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener\Factory;

use ContributeEnhancements\Listener\ArchivedContributionGuardListener;
use ContributeEnhancements\Service\ArchiveService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ArchivedContributionGuardListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ArchivedContributionGuardListener($services->get(ArchiveService::class));
    }
}
