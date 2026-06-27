<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper\Factory;

use ContributeEnhancements\Service\ProposalAuditViewService;
use ContributeEnhancements\Service\ProposalDiffService;
use ContributeEnhancements\View\Helper\ContributionAuditValues;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributionAuditValuesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ContributionAuditValues(
            $services->get(ProposalAuditViewService::class),
            $services->get(ProposalDiffService::class)
        );
    }
}
