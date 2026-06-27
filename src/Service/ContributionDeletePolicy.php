<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;

/**
 * Contributor-facing delete rules for patch contributions.
 */
class ContributionDeletePolicy
{
    protected ArchiveService $archiveService;

    public function __construct(ArchiveService $archiveService)
    {
        $this->archiveService = $archiveService;
    }

    public function canContributorDelete(ContributionRepresentation $contribution): bool
    {
        if ($this->archiveService->isArchived($contribution->id())) {
            return false;
        }

        // Final review decisions (accepted or rejected) are read-only for contributors.
        if ($contribution->isValidated() !== null) {
            return false;
        }

        if (!$contribution->isUpdatable()) {
            return false;
        }

        return $contribution->userIsAllowed('delete');
    }
}
