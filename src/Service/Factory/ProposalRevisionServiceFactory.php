<?php declare(strict_types=1);

namespace ContributeEnhancements\Service\Factory;

use ContributeEnhancements\Service\ProposalApplicationService;
use ContributeEnhancements\Service\ProposalDedupeService;
use ContributeEnhancements\Service\ProposalRevisionService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ProposalRevisionServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ProposalRevisionService(
            $services->get('Omeka\ApiManager'),
            $services->get(ProposalDedupeService::class),
            $services->get(ProposalApplicationService::class)
        );
    }
}
