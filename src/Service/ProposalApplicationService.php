<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;
use Omeka\Api\Manager as ApiManager;

/**
 * Applies or reverts accepted per-value decisions when contribution validation changes.
 */
class ProposalApplicationService
{
    public const ENTRY_APPLIED = 'o-module-contribute-enhancements:applied';

    protected ApiManager $api;

    protected ResourceValueRemovalApplier $valueApplier;

    protected ProposalDiffService $diffService;

    protected ProposalDedupeService $dedupeService;

    public function __construct(
        ApiManager $api,
        ResourceValueRemovalApplier $valueApplier,
        ProposalDiffService $diffService,
        ProposalDedupeService $dedupeService
    ) {
        $this->api = $api;
        $this->valueApplier = $valueApplier;
        $this->diffService = $diffService;
        $this->dedupeService = $dedupeService;
    }

    public function applyAcceptedDecisions(ContributionRepresentation $contribution): void
    {
        if (!$contribution->isPatch() || $contribution->isValidated() !== true) {
            return;
        }

        $resource = $contribution->resource();
        if (!$resource) {
            return;
        }

        $proposal = $contribution->proposal();
        $changed = false;

        foreach ($proposal as $term => $entries) {
            if ($term === 'template' || $term === 'media' || !is_array($entries)) {
                continue;
            }

            foreach ($entries as $key => $entry) {
                if (!is_array($entry) || !empty($entry[self::ENTRY_APPLIED])) {
                    continue;
                }

                if (!empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED])) {
                    $this->applyApprovedRemoval($resource, $term, $entry);
                    $proposal[$term][$key][self::ENTRY_APPLIED] = true;
                    $changed = true;
                    continue;
                }

                $revisionStatus = $entry[ProposalRevisionService::ENTRY_REVISION_STATUS] ?? null;
                if ($revisionStatus === ProposalRevisionService::STATUS_ACCEPTED) {
                    $this->applyAcceptedRevision($resource, $term, $entry);
                    $proposal[$term][$key][self::ENTRY_APPLIED] = true;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $this->saveProposal($contribution, $proposal);
        }
    }

    public function revertAppliedDecisions(ContributionRepresentation $contribution): void
    {
        if (!$contribution->isPatch()) {
            return;
        }

        $resource = $contribution->resource();
        if (!$resource) {
            return;
        }

        $proposal = $contribution->proposal();
        $changed = false;

        foreach ($proposal as $term => $entries) {
            if ($term === 'template' || $term === 'media' || !is_array($entries)) {
                continue;
            }

            foreach ($entries as $key => $entry) {
                if (!is_array($entry) || empty($entry[self::ENTRY_APPLIED])) {
                    continue;
                }

                if (!empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED])) {
                    $this->revertApprovedRemoval($resource, $term, $entry);
                } elseif (($entry[ProposalRevisionService::ENTRY_REVISION_STATUS] ?? null) === ProposalRevisionService::STATUS_ACCEPTED) {
                    $this->revertAcceptedRevision($resource, $term, $entry);
                }

                unset($proposal[$term][$key][self::ENTRY_APPLIED]);
                $changed = true;
            }
        }

        if ($changed) {
            $this->saveProposal($contribution, $proposal);
        }
    }

    public function revertSingleEntry(
        ContributionRepresentation $contribution,
        string $term,
        int|string $key
    ): void {
        $resource = $contribution->resource();
        if (!$resource) {
            return;
        }

        $proposal = $contribution->proposal();
        $entry = $proposal[$term][$key] ?? $proposal[$term][(string) $key] ?? null;
        if (!$entry || empty($entry[self::ENTRY_APPLIED])) {
            return;
        }

        if (!empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED])) {
            $this->revertApprovedRemoval($resource, $term, $entry);
        } elseif (($entry[ProposalRevisionService::ENTRY_REVISION_STATUS] ?? null) === ProposalRevisionService::STATUS_ACCEPTED) {
            $this->revertAcceptedRevision($resource, $term, $entry);
        }

        $resolvedKey = isset($proposal[$term][$key]) ? $key : (string) $key;
        unset($proposal[$term][$resolvedKey][self::ENTRY_APPLIED]);
        $this->saveProposal($contribution, $proposal);
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function applyAcceptedRevision(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource,
        string $term,
        array $entry
    ): void {
        if ($this->diffService->isNewEntry($entry)) {
            $this->valueApplier->addSingleValue($resource, $term, $entry['proposed'] ?? []);

            return;
        }

        $valueIndex = $this->resolveValueIndex($resource, $term, $entry['original'] ?? []);
        $this->valueApplier->updateSingleValue(
            $resource,
            $term,
            $entry['original'] ?? [],
            $entry['proposed'] ?? [],
            $valueIndex
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function revertAcceptedRevision(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource,
        string $term,
        array $entry
    ): void {
        if ($this->diffService->isNewEntry($entry)) {
            $this->valueApplier->removeSingleValue($resource, $term, $entry['proposed'] ?? []);

            return;
        }

        $valueIndex = $this->resolveValueIndex($resource, $term, $entry['proposed'] ?? [], true);
        $this->valueApplier->updateSingleValue(
            $resource,
            $term,
            $entry['proposed'] ?? [],
            $entry['original'] ?? [],
            $valueIndex
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function applyApprovedRemoval(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource,
        string $term,
        array $entry
    ): void {
        $valueIndex = isset($entry[ProposalRemovalApprovalService::ENTRY_VALUE_INDEX])
            ? (int) $entry[ProposalRemovalApprovalService::ENTRY_VALUE_INDEX]
            : null;

        $this->valueApplier->removeSingleValue(
            $resource,
            $term,
            $entry['original'] ?? [],
            $valueIndex
        );
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function revertApprovedRemoval(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource,
        string $term,
        array $entry
    ): void {
        $this->valueApplier->addSingleValue($resource, $term, $entry['original'] ?? []);
    }

    /**
     * @param array<string, mixed> $source
     */
    protected function resolveValueIndex(
        \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource,
        string $term,
        array $source,
        bool $matchProposed = false
    ): ?int {
        $values = $resource->value($term, ['all' => true]) ?: [];

        foreach ($values as $index => $value) {
            if ($matchProposed) {
                if ($this->diffService->proposedMatchesValue($source, $value)) {
                    return (int) $index;
                }
            } elseif ($this->diffService->originalMatchesValue($source, $value)) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $proposal
     */
    protected function saveProposal(ContributionRepresentation $contribution, array $proposal): void
    {
        $proposal = $this->dedupeService->deduplicate($proposal);

        $this->api->update('contributions', $contribution->id(), [
            'o-module-contribute:proposal' => $proposal,
        ], [], ['isPartial' => true]);
    }
}
