<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper;

use Contribute\Api\Representation\ContributionRepresentation;
use ContributeEnhancements\Service\ProposalEditViewService;
use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ResourceTemplateRepresentation;

/**
 * Wraps Contribute's contributionFields helper and syncs edit values with review state.
 */
class ContributionFields extends AbstractHelper
{
    protected \Contribute\View\Helper\ContributionFields $inner;

    protected ProposalEditViewService $editViewService;

    public function __construct(
        \Contribute\View\Helper\ContributionFields $inner,
        ProposalEditViewService $editViewService
    ) {
        $this->inner = $inner;
        $this->editViewService = $editViewService;
    }

    public function __invoke(
        ?AbstractResourceEntityRepresentation $resource = null,
        ?ContributionRepresentation $contribution = null,
        ?ResourceTemplateRepresentation $resourceTemplate = null,
        ?ResourceTemplateRepresentation $templateItem = null,
        ?int $indexProposalMedia = null
    ): array {
        $fields = $this->inner->__invoke(
            $resource,
            $contribution,
            $resourceTemplate,
            $templateItem,
            $indexProposalMedia
        );

        if ($indexProposalMedia !== null) {
            return $fields;
        }

        return $this->editViewService->enhanceFields($fields, $contribution);
    }
}
