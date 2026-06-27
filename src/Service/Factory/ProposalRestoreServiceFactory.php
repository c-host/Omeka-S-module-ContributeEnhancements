<?php declare(strict_types=1);

namespace ContributeEnhancements\Service\Factory;

use ContributeEnhancements\Service\ProposalApplicationService;
use ContributeEnhancements\Service\ProposalDedupeService;
use ContributeEnhancements\Service\ProposalDiffService;
use ContributeEnhancements\Service\ProposalNormalizer;
use ContributeEnhancements\Service\ProposalRestoreService;
use ContributeEnhancements\Service\ResourceValueRemovalApplier;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ProposalRestoreServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ProposalRestoreService(
            $services->get('Omeka\ApiManager'),
            $services->get(ProposalNormalizer::class),
            $services->get(ResourceValueRemovalApplier::class),
            $services->get(ProposalDedupeService::class),
            $services->get(ProposalDiffService::class),
            $services->get(ProposalApplicationService::class)
        );
    }
}
