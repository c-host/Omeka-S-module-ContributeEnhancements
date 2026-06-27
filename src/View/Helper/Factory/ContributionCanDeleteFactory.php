<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper\Factory;

use ContributeEnhancements\Service\ContributionDeletePolicy;
use ContributeEnhancements\View\Helper\ContributionCanDelete;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributionCanDeleteFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ContributionCanDelete($services->get(ContributionDeletePolicy::class));
    }
}
