<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener\Factory;

use ContributeEnhancements\Listener\ProposalSnapshotListener;
use ContributeEnhancements\Service\ProposalBaselineService;
use ContributeEnhancements\Service\ProposalDiffService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ProposalSnapshotListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ProposalSnapshotListener(
            $services->get('Omeka\ApiManager'),
            $services->get(ProposalBaselineService::class),
            $services->get(ProposalDiffService::class)
        );
    }
}
