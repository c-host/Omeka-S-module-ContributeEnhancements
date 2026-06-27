<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener\Factory;

use ContributeEnhancements\Listener\ContributionValidatedListener;
use ContributeEnhancements\Service\ResourceValueRemovalApplier;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributionValidatedListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ContributionValidatedListener(
            $services->get(ResourceValueRemovalApplier::class),
            $services->get(\ContributeEnhancements\Service\ProposalApplicationService::class)
        );
    }
}
