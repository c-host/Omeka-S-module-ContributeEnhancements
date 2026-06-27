<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ValueRepresentation;

class ProposalRemovalApprovalService
{
    public const ENTRY_APPROVED = 'o-module-contribute-enhancements:approved_removal';

    public const ENTRY_VALUE_INDEX = 'o-module-contribute-enhancements:value_index';

    public const ENTRY_RESTORED = 'o-module-contribute-enhancements:restored_removal';

    protected ApiManager $api;

    protected ResourceValueRemovalApplier $removalApplier;

    protected ProposalDedupeService $dedupeService;

    protected ProposalApplicationService $applicationService;

    public function __construct(
        ApiManager $api,
        ResourceValueRemovalApplier $removalApplier,
        ProposalDedupeService $dedupeService,
        ProposalApplicationService $applicationService
    ) {
        $this->api = $api;
        $this->removalApplier = $removalApplier;
        $this->dedupeService = $dedupeService;
        $this->applicationService = $applicationService;
    }

    public function approve(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key = null,
        ?int $valueIndex = null
    ): void {
        if (!$contribution->isPatch()) {
            throw new \InvalidArgumentException('Only patch contributions support removal approval.');
        }

        $resource = $contribution->resource();
        if (!$resource) {
            throw new \InvalidArgumentException('Contribution resource not found.');
        }

        $original = $this->resolveOriginal($contribution, $term, $key, $valueIndex);
        if (!$original) {
            throw new \InvalidArgumentException('Removal target not found.');
        }

        $proposal = $contribution->proposal();
        $proposal = $this->markMatchingProposalEntryApproved($proposal, $term, $original, $key, $valueIndex);
        $proposal = $this->dedupeService->deduplicate($proposal);

        $this->api->update('contributions', $contribution->id(), [
            'o-module-contribute:proposal' => $proposal,
        ], [], ['isPartial' => true]);

        if ($contribution->isValidated() === true) {
            $contribution = $this->api->read('contributions', $contribution->id())->getContent();
            $this->applicationService->applyAcceptedDecisions($contribution);
        }
    }

    public function reproposeRemoval(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key = null,
        ?int $valueIndex = null
    ): void {
        if (!$contribution->isPatch()) {
            throw new \InvalidArgumentException('Only patch contributions support reproposing removal.');
        }

        $proposal = $contribution->proposal();
        $entryKey = $this->resolveEntryKey($proposal, $term, $key, $valueIndex);
        if ($entryKey === null || !isset($proposal[$term][$entryKey]['original'])) {
            throw new \InvalidArgumentException('Proposal entry not found.');
        }

        $original = $proposal[$term][$entryKey]['original'];
        $proposal[$term][$entryKey]['proposed'] = $this->emptyProposed($original);
        unset($proposal[$term][$entryKey][self::ENTRY_RESTORED]);

        $proposal = $this->dedupeService->deduplicate($proposal);

        $this->api->update('contributions', $contribution->id(), [
            'o-module-contribute:proposal' => $proposal,
        ], [], ['isPartial' => true]);
    }

    /**
     * @param array<string, mixed> $proposal
     * @return int|string|null
     */
    protected function resolveEntryKey(array $proposal, string $term, ?int $key, ?int $valueIndex): int|string|null
    {
        if ($key !== null && isset($proposal[$term][$key])) {
            return $key;
        }

        if ($key !== null && isset($proposal[$term][(string) $key])) {
            return (string) $key;
        }

        if ($valueIndex === null) {
            return null;
        }

        foreach ($proposal[$term] ?? [] as $entryKey => $entry) {
            if (!empty($entry[self::ENTRY_VALUE_INDEX]) && (int) $entry[self::ENTRY_VALUE_INDEX] === $valueIndex) {
                return $entryKey;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $proposal
     * @param array<string, mixed> $original
     * @return array<string, mixed>
     */
    protected function markMatchingProposalEntryApproved(
        array $proposal,
        string $term,
        array $original,
        ?int $key,
        ?int $valueIndex
    ): array {
        if ($key !== null && isset($proposal[$term][$key])) {
            $proposal[$term][$key][self::ENTRY_APPROVED] = true;
            if ($valueIndex !== null) {
                $proposal[$term][$key][self::ENTRY_VALUE_INDEX] = $valueIndex;
            }

            return $proposal;
        }

        if ($key !== null && isset($proposal[$term][(string) $key])) {
            $proposal[$term][(string) $key][self::ENTRY_APPROVED] = true;
            if ($valueIndex !== null) {
                $proposal[$term][(string) $key][self::ENTRY_VALUE_INDEX] = $valueIndex;
            }

            return $proposal;
        }

        foreach ($proposal[$term] ?? [] as $entryKey => $entry) {
            if ($this->originalMatches($entry['original'] ?? [], $original)) {
                $proposal[$term][$entryKey][self::ENTRY_APPROVED] = true;
                if ($valueIndex !== null) {
                    $proposal[$term][$entryKey][self::ENTRY_VALUE_INDEX] = $valueIndex;
                }

                return $proposal;
            }
        }

        $proposal[$term] ??= [];
        $proposal[$term][] = $this->buildApprovedRemovalEntry($original, $valueIndex);

        return $proposal;
    }

    /**
     * @param array<string, mixed> $original
     * @return array<string, mixed>
     */
    protected function buildApprovedRemovalEntry(array $original, ?int $valueIndex): array
    {
        $entry = [
            'original' => $original,
            'proposed' => $this->emptyProposed($original),
            self::ENTRY_APPROVED => true,
        ];

        if ($valueIndex !== null) {
            $entry[self::ENTRY_VALUE_INDEX] = $valueIndex;
        }

        return $entry;
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

    /**
     * @param array<string, mixed> $entryOriginal
     * @param array<string, mixed> $original
     */
    protected function originalMatches(array $entryOriginal, array $original): bool
    {
        if (array_key_exists('@uri', $original)) {
            return ($entryOriginal['@uri'] ?? '') === ($original['@uri'] ?? '')
                && ($entryOriginal['@label'] ?? '') === ($original['@label'] ?? '');
        }

        if (array_key_exists('@resource', $original)) {
            return (int) ($entryOriginal['@resource'] ?? 0) === (int) ($original['@resource'] ?? 0);
        }

        $entryLang = $entryOriginal['@language'] ?? null;
        $originalLang = $original['@language'] ?? null;

        return (string) ($entryOriginal['@value'] ?? '') === (string) ($original['@value'] ?? '')
            && $entryLang === $originalLang;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function resolveOriginal(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key,
        ?int $valueIndex
    ): ?array {
        $proposal = $contribution->proposal();
        if ($key !== null && isset($proposal[$term][$key]['original'])) {
            return $proposal[$term][$key]['original'];
        }

        $resource = $contribution->resource();
        if (!$resource || $valueIndex === null) {
            return null;
        }

        $values = $resource->value($term, ['all' => true]) ?: [];
        if (!isset($values[$valueIndex])) {
            return null;
        }

        return $this->originalFromValue($values[$valueIndex]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function originalFromValue(ValueRepresentation $value): array
    {
        if ($value->uri()) {
            return [
                '@uri' => $value->uri() ?? '',
                '@label' => $value->value() ?? '',
            ];
        }

        if ($value->valueResource()) {
            return [
                '@resource' => $value->valueResource()->id(),
            ];
        }

        $original = [
            '@value' => $value->value(),
        ];

        if ($value->lang()) {
            $original['@language'] = $value->lang();
        }

        return $original;
    }
}
