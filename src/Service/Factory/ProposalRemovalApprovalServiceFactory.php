<?php declare(strict_types=1);

namespace ContributeEnhancements\Service\Factory;

use ContributeEnhancements\Service\ProposalDedupeService;
use ContributeEnhancements\Service\ProposalRemovalApprovalService;
use ContributeEnhancements\Service\ResourceValueRemovalApplier;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ProposalRemovalApprovalServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ProposalRemovalApprovalService(
            $services->get('Omeka\ApiManager'),
            $services->get(ResourceValueRemovalApplier::class),
            $services->get(ProposalDedupeService::class),
            $services->get(\ContributeEnhancements\Service\ProposalApplicationService::class)
        );
    }
}
