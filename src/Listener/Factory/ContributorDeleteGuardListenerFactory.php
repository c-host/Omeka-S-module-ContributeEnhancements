<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener\Factory;

use ContributeEnhancements\Listener\ContributorDeleteGuardListener;
use ContributeEnhancements\Service\ContributionDeletePolicy;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributorDeleteGuardListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ContributorDeleteGuardListener(
            $services->get(ContributionDeletePolicy::class),
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\AuthenticationService')
        );
    }
}
