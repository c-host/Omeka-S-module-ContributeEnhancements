<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper;

use Contribute\Api\Representation\ContributionRepresentation;
use ContributeEnhancements\Service\ContributionDeletePolicy;
use Laminas\View\Helper\AbstractHelper;

class ContributionCanDelete extends AbstractHelper
{
    protected ContributionDeletePolicy $deletePolicy;

    public function __construct(ContributionDeletePolicy $deletePolicy)
    {
        $this->deletePolicy = $deletePolicy;
    }

    public function __invoke(?ContributionRepresentation $contribution = null): bool
    {
        if (!$contribution) {
            return false;
        }

        return $this->deletePolicy->canContributorDelete($contribution);
    }
}
