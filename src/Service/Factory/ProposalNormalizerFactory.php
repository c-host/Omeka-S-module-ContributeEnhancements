<?php declare(strict_types=1);

namespace ContributeEnhancements\Service\Factory;

use ContributeEnhancements\Service\ProposalDiffService;
use ContributeEnhancements\Service\ProposalNormalizer;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ProposalNormalizerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ProposalNormalizer(
            $services->get(ProposalDiffService::class),
            $services->get('Common\EasyMeta')
        );
    }
}
