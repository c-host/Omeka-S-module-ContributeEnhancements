<?php declare(strict_types=1);

namespace ContributeEnhancements\Service\Factory;

use Common\Stdlib\EasyMeta;
use ContributeEnhancements\Service\ProposalAuditViewService;
use ContributeEnhancements\Service\ProposalBaselineService;
use ContributeEnhancements\Service\ProposalDiffService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ProposalAuditViewServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ProposalAuditViewService(
            $services->get(ProposalBaselineService::class),
            $services->get(ProposalDiffService::class),
            $services->get(EasyMeta::class)
        );
    }
}
