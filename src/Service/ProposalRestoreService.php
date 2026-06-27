<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;
use ContributeEnhancements\Service\ProposalApplicationService;
use ContributeEnhancements\Service\ProposalRemovalApprovalService;
use Omeka\Api\Manager as ApiManager;

class ProposalRestoreService
{
    protected ApiManager $api;

    protected ProposalNormalizer $normalizer;

    protected ResourceValueRemovalApplier $removalApplier;

    protected ProposalDedupeService $dedupeService;

    protected ProposalDiffService $diffService;

    protected ProposalApplicationService $applicationService;

    public function __construct(
        ApiManager $api,
        ProposalNormalizer $normalizer,
        ResourceValueRemovalApplier $removalApplier,
        ProposalDedupeService $dedupeService,
        ProposalDiffService $diffService,
        ProposalApplicationService $applicationService
    ) {
        $this->api = $api;
        $this->normalizer = $normalizer;
        $this->removalApplier = $removalApplier;
        $this->dedupeService = $dedupeService;
        $this->diffService = $diffService;
        $this->applicationService = $applicationService;
    }

    /**
     * Restore a proposed removal by proposal key or resource value index.
     */
    public function restore(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key = null,
        ?int $valueIndex = null
    ): void {
        if (!$contribution->isPatch()) {
            throw new \InvalidArgumentException('Only patch contributions support value restoration.');
        }

        $proposal = $contribution->proposal();

        if ($key !== null) {
            $entry = $proposal[$term][$key] ?? null;
            if ($entry && !empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED])) {
                if (!empty($entry[ProposalApplicationService::ENTRY_APPLIED])) {
                    $this->applicationService->revertSingleEntry($contribution, $term, $key);
                }
                $proposal = $this->restoreApprovedEntry($contribution, $proposal, $term, $key);
            } else {
                $proposal = $this->restoreByKey($proposal, $term, $key, $valueIndex);
            }
        } elseif ($valueIndex !== null) {
            $approvedKey = $this->findApprovedEntryKey($proposal, $term, $valueIndex);
            if ($approvedKey !== null) {
                $entry = $proposal[$term][$approvedKey] ?? null;
                if ($entry && !empty($entry[ProposalApplicationService::ENTRY_APPLIED])) {
                    $this->applicationService->revertSingleEntry($contribution, $term, $approvedKey);
                }
                $proposal = $this->restoreApprovedEntry($contribution, $proposal, $term, $approvedKey);
            } else {
                $proposal = $this->restoreByValueIndex($contribution, $proposal, $term, $valueIndex);
            }
        } else {
            throw new \InvalidArgumentException('Missing key or value index.');
        }

        $proposal = $this->dedupeService->deduplicate($proposal);

        $this->api->update('contributions', $contribution->id(), [
            'o-module-contribute:proposal' => $proposal,
        ], [], ['isPartial' => true]);
    }

    /**
     * @param array<string, mixed> $proposal
     * @return array<string, mixed>
     */
    protected function restoreByKey(array $proposal, string $term, int $key, ?int $valueIndex = null): array
    {
        if (!isset($proposal[$term][$key])) {
            throw new \InvalidArgumentException('Proposal entry not found.');
        }

        $entry = $proposal[$term][$key];
        $proposal[$term][$key]['proposed'] = $entry['original'];
        $proposal[$term][$key][ProposalRemovalApprovalService::ENTRY_RESTORED] = true;
        unset($proposal[$term][$key][ProposalRemovalApprovalService::ENTRY_APPROVED]);
        if ($valueIndex !== null) {
            $proposal[$term][$key][ProposalRemovalApprovalService::ENTRY_VALUE_INDEX] = $valueIndex;
        } else {
            unset($proposal[$term][$key][ProposalRemovalApprovalService::ENTRY_VALUE_INDEX]);
        }

        return $proposal;
    }

    /**
     * @param array<string, mixed> $proposal
     * @return array<string, mixed>
     */
    protected function restoreApprovedEntry(
        ContributionRepresentation $contribution,
        array $proposal,
        string $term,
        int|string $key
    ): array {
        if (!isset($proposal[$term][$key])) {
            throw new \InvalidArgumentException('Approved removal entry not found.');
        }

        $entry = $proposal[$term][$key];
        $original = $entry['original'] ?? null;
        if (!$original) {
            throw new \InvalidArgumentException('Approved removal original not found.');
        }

        unset($proposal[$term][$key][ProposalRemovalApprovalService::ENTRY_APPROVED]);
        unset($proposal[$term][$key][ProposalRemovalApprovalService::ENTRY_VALUE_INDEX]);
        unset($proposal[$term][$key][ProposalApplicationService::ENTRY_APPLIED]);
        $proposal[$term][$key]['proposed'] = $this->emptyProposed($original);

        return $proposal;
    }

    /**
     * @param array<string, mixed> $proposal
     * @return array<string, mixed>
     */
    protected function restoreByValueIndex(
        ContributionRepresentation $contribution,
        array $proposal,
        string $term,
        int $valueIndex
    ): array {
        $resource = $contribution->resource();
        if (!$resource) {
            throw new \InvalidArgumentException('Contribution resource not found.');
        }

        $values = $resource->value($term, ['all' => true]) ?: [];
        if (!isset($values[$valueIndex])) {
            throw new \InvalidArgumentException('Resource value not found.');
        }

        $matchKey = $this->diffService->findMatchingProposalKey($proposal[$term] ?? [], $values[$valueIndex]);
        if ($matchKey !== null) {
            return $this->restoreByKey($proposal, $term, $matchKey, $valueIndex);
        }

        $proposal[$term] ??= [];
        $entry = $this->normalizer->buildKeepEntry($values[$valueIndex]);
        $entry[ProposalRemovalApprovalService::ENTRY_RESTORED] = true;
        $entry[ProposalRemovalApprovalService::ENTRY_VALUE_INDEX] = $valueIndex;
        $proposal[$term][] = $entry;

        return $proposal;
    }

    /**
     * @param array<string, mixed> $proposal
     */
    protected function findApprovedEntryKey(array $proposal, string $term, int $valueIndex): int|string|null
    {
        foreach ($proposal[$term] ?? [] as $entryKey => $entry) {
            if (empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED])) {
                continue;
            }

            $storedIndex = $entry[ProposalRemovalApprovalService::ENTRY_VALUE_INDEX] ?? null;
            if ($storedIndex !== null && (int) $storedIndex === $valueIndex) {
                return $entryKey;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $original
     * @return array<string, mixed>
     */
    protected function emptyProposed(array $original): array
    {
        if (array_key_exists('@uri', $original)) {
            return ['@uri' => '', '@label' => ''];
        }

        if (array_key_exists('@resource', $original)) {
            return ['@resource' => null];
        }

        $proposed = ['@value' => ''];
        if (array_key_exists('@language', $original)) {
            $proposed['@language'] = $original['@language'];
        }

        return $proposed;
    }
}
