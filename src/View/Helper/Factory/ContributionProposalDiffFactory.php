<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper\Factory;

use ContributeEnhancements\Service\ProposalDedupeService;
use ContributeEnhancements\Service\ProposalDiffService;
use ContributeEnhancements\Service\ProposalLanguageService;
use ContributeEnhancements\View\Helper\ContributionProposalDiff;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributionProposalDiffFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ContributionProposalDiff(
            $services->get(ProposalDiffService::class),
            $services->get(ProposalDedupeService::class),
            $services->get(ProposalLanguageService::class)
        );
    }
}
