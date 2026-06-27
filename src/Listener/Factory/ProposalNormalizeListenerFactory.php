<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener\Factory;

use ContributeEnhancements\Listener\ProposalNormalizeListener;
use ContributeEnhancements\Service\ProposalDedupeService;
use ContributeEnhancements\Service\ProposalDiffService;
use ContributeEnhancements\Service\ProposalNormalizer;
use ContributeEnhancements\Service\ProposalResubmitService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ProposalNormalizeListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ProposalNormalizeListener(
            $services->get('Omeka\ApiManager'),
            $services->get(ProposalDiffService::class),
            $services->get(ProposalNormalizer::class),
            $services->get(ProposalResubmitService::class),
            $services->get(ProposalDedupeService::class)
        );
    }
}
