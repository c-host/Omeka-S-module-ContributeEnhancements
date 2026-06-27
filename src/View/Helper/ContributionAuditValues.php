<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper;

use Contribute\Api\Representation\ContributionRepresentation;
use ContributeEnhancements\Service\ProposalAuditViewService;
use ContributeEnhancements\Service\ProposalDiffService;
use Laminas\View\Helper\AbstractHelper;

class ContributionAuditValues extends AbstractHelper
{
    protected ProposalAuditViewService $auditViewService;

    protected ProposalDiffService $diffService;

    public function __construct(
        ProposalAuditViewService $auditViewService,
        ProposalDiffService $diffService
    ) {
        $this->auditViewService = $auditViewService;
        $this->diffService = $diffService;
    }

    public function __invoke(ContributionRepresentation $contribution): string
    {
        $values = $this->auditViewService->buildDisplayFields($contribution);
        if (!$values) {
            return '';
        }

        $view = $this->getView();
        $template = $contribution->resourceTemplate();

        return (string) $view->partial('contribute-enhancements/guest/contribution-values', [
            'site' => $view->currentSite(),
            'contribution' => $contribution,
            'templateProperties' => $template ? $template->resourceTemplateProperties() : [],
            'values' => $values,
            'valuesMedias' => [],
            'auditDiff' => true,
        ]);
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function analyze(ContributionRepresentation $contribution): array
    {
        if (!$contribution->isPatch()) {
            return [];
        }

        $editableTerms = $this->diffService->editableTermsFromTemplate($contribution->resourceTemplate());

        return $this->diffService->analyzeAudit($contribution->proposal(), $editableTerms);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function changeHistoryRows(ContributionRepresentation $contribution): array
    {
        return $this->auditViewService->buildChangeHistoryRows($contribution);
    }
}
