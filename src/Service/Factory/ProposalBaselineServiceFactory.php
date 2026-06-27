<?php declare(strict_types=1);

namespace ContributeEnhancements\Service\Factory;

use ContributeEnhancements\Service\ProposalBaselineService;
use ContributeEnhancements\Service\ProposalDiffService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ProposalBaselineServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ProposalBaselineService($services->get(ProposalDiffService::class));
    }
}
