<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper\Factory;

use Contribute\View\Helper\ContributionFields as ContributeContributionFields;
use ContributeEnhancements\Service\ProposalEditViewService;
use ContributeEnhancements\View\Helper\ContributionFields;
use Contribute\Service\ViewHelper\ContributionFieldsFactory as ContributeContributionFieldsFactory;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributionFieldsFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $innerFactory = new ContributeContributionFieldsFactory();
        $inner = $innerFactory($services, ContributeContributionFields::class);

        return new ContributionFields(
            $inner,
            $services->get(ProposalEditViewService::class)
        );
    }
}
