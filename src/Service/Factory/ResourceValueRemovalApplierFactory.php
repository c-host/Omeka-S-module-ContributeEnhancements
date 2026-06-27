<?php declare(strict_types=1);

namespace ContributeEnhancements\Service\Factory;

use ContributeEnhancements\Service\ProposalDiffService;
use ContributeEnhancements\Service\ResourceValueRemovalApplier;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ResourceValueRemovalApplierFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ResourceValueRemovalApplier(
            $services->get('Omeka\ApiManager'),
            $services->get(ProposalDiffService::class)
        );
    }
}
