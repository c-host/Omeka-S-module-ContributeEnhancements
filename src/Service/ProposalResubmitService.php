<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

/**
 * Reconcile proposal on contributor re-submit: drop reviewer-only markers.
 */
class ProposalResubmitService
{
    public const ENHANCEMENT_KEYS = [
        ProposalRemovalApprovalService::ENTRY_APPROVED,
        ProposalRemovalApprovalService::ENTRY_VALUE_INDEX,
        ProposalRemovalApprovalService::ENTRY_RESTORED,
        ProposalRevisionService::ENTRY_REVISION_STATUS,
        ProposalApplicationService::ENTRY_APPLIED,
    ];

    /**
     * @param array<string, mixed> $proposal
     * @return array<string, mixed>
     */
    public function stripEnhancementMarkers(array $proposal): array
    {
        foreach ($proposal as $term => $entries) {
            if ($term === 'template' || $term === 'media' || !is_array($entries)) {
                continue;
            }

            foreach ($entries as $key => $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                foreach (self::ENHANCEMENT_KEYS as $marker) {
                    unset($proposal[$term][$key][$marker]);
                }
            }
        }

        return $proposal;
    }
}
