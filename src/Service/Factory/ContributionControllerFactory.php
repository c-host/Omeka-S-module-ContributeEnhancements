<?php declare(strict_types=1);

namespace ContributeEnhancements\Service\Factory;

use ContributeEnhancements\Controller\Admin\ContributionController;
use ContributeEnhancements\Service\ArchiveService;
use ContributeEnhancements\Service\ContributionEmailTemplateService;
use ContributeEnhancements\Service\ProposalLanguageService;
use ContributeEnhancements\Service\ProposalRemovalApprovalService;
use ContributeEnhancements\Service\ProposalRestoreService;
use ContributeEnhancements\Service\ProposalApplicationService;
use ContributeEnhancements\Service\ProposalRevisionService;
use ContributeEnhancements\Service\UndertakingService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributionControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ContributionController(
            $services->get(ProposalRestoreService::class),
            $services->get(ProposalRemovalApprovalService::class),
            $services->get(ArchiveService::class),
            $services->get(ContributionEmailTemplateService::class),
            $services->get(ProposalRevisionService::class),
            $services->get(ProposalApplicationService::class),
            $services->get(UndertakingService::class),
            $services->get(ProposalLanguageService::class)
        );
    }
}
