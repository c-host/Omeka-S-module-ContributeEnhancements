<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper;

use Contribute\Api\Representation\ContributionRepresentation;
use ContributeEnhancements\Service\ProposalDedupeService;
use ContributeEnhancements\Service\ProposalDiffService;
use ContributeEnhancements\Service\ProposalLanguageService;
use ContributeEnhancements\Service\ProposalRemovalApprovalService;
use ContributeEnhancements\Service\ProposalRevisionService;
use Laminas\View\Helper\AbstractHelper;

class ContributionProposalDiff extends AbstractHelper
{
    protected ProposalDiffService $diffService;

    protected ProposalDedupeService $dedupeService;

    protected ProposalLanguageService $languageService;

    public function __construct(
        ProposalDiffService $diffService,
        ProposalDedupeService $dedupeService,
        ProposalLanguageService $languageService
    ) {
        $this->diffService = $diffService;
        $this->dedupeService = $dedupeService;
        $this->languageService = $languageService;
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

        return $this->diffService->analyze(
            $contribution->resource(),
            $contribution->proposal(),
            $editableTerms
        );
    }

    public function statusForValue(
        ContributionRepresentation $contribution,
        string $term,
        int $rowIndex
    ): ?string {
        $analysis = $this->analyze($contribution);
        return $analysis[$term][$rowIndex]['status'] ?? null;
    }

    public function afterLabel(?string $status): string
    {
        $translate = $this->getView()->plugin('translate');

        switch ($status) {
            case ProposalDiffService::STATUS_REMOVED:
            case ProposalDiffService::STATUS_DROPPED:
                return (string) $translate('(proposed removal)');
            case ProposalDiffService::STATUS_ADDED:
                return (string) $translate('(new value)');
            default:
                return (string) $translate('(no change proposed)');
        }
    }

    /**
     * @return array<string, int>
     */
    public function droppedCounts(ContributionRepresentation $contribution): array
    {
        if (!$contribution->isPatch()) {
            return [];
        }

        $editableTerms = $this->diffService->editableTermsFromTemplate($contribution->resourceTemplate());

        return $this->diffService->droppedCounts(
            $contribution->resource(),
            $contribution->proposal(),
            $editableTerms
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function droppedRowsForTerm(ContributionRepresentation $contribution, string $term): array
    {
        $analysis = $this->analyze($contribution);
        $rows = $analysis[$term] ?? [];

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $row['status'] === ProposalDiffService::STATUS_DROPPED
                && !$this->isApprovedRemovalForRow($contribution, $term, $row)
                && !$this->isRestoredRemovalForRow($contribution, $term, $row)
                && $this->shouldRenderDroppedRow($contribution, $term, $row)
        ));
    }

    /**
     * @param array<string, mixed> $row
     */
    public function shouldRenderDroppedRow(
        ContributionRepresentation $contribution,
        string $term,
        array $row
    ): bool {
        $value = $row['value'] ?? null;
        if (!$value) {
            return true;
        }

        $proposal = $contribution->proposal()[$term] ?? [];
        foreach ($proposal as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if ($this->proposalEntryMatchesValue($entry, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function proposalEntryMatchesValue(array $entry, \Omeka\Api\Representation\ValueRepresentation $value): bool
    {
        if ($this->diffService->originalMatchesValue($entry['original'] ?? [], $value)) {
            return true;
        }

        return $this->diffService->proposedMatchesValue($entry['proposed'] ?? [], $value);
    }

    /**
     * @param array<string, mixed> $original
     */
    public function originalFingerprint(array $original): string
    {
        return $this->dedupeService->fingerprint($original);
    }

    /**
     * @param array<string, bool> $seenFingerprints
     * @param array<string, mixed> $proposed
     */
    public function shouldRenderPropositionEntry(
        array $original,
        array &$seenFingerprints,
        array $proposed = []
    ): bool {
        $fingerprint = $this->propositionDisplayFingerprint($original, $proposed);
        if ($fingerprint === '') {
            return true;
        }

        if (isset($seenFingerprints[$fingerprint])) {
            return false;
        }

        $seenFingerprints[$fingerprint] = true;

        return true;
    }

    /**
     * @param array<string, mixed> $original
     * @param array<string, mixed> $proposed
     */
    public function propositionDisplayFingerprint(array $original, array $proposed): string
    {
        if ($this->diffService->isNewEntry(['original' => $original, 'proposed' => $proposed])) {
            return 'proposed:' . $this->dedupeService->fingerprint($proposed);
        }

        return $this->originalFingerprint($original);
    }

    public function validateActionLabel(string $process): string
    {
        $translate = $this->getView()->plugin('translate');

        return match ($process) {
            'update' => (string) $translate('Accept revision'),
            'add', 'append' => (string) $translate('Accept addition'),
            'remove' => (string) $translate('Approve removal'),
            default => (string) $translate($process),
        };
    }

    public function rejectActionLabel(string $process): string
    {
        $translate = $this->getView()->plugin('translate');

        return match ($process) {
            'update' => (string) $translate('Reject revision'),
            'add', 'append' => (string) $translate('Reject addition'),
            default => (string) $translate('Reject'),
        };
    }

    public function revisionStatus(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key
    ): ?string {
        if ($key === null) {
            return null;
        }

        $entry = $contribution->proposal()[$term][$key] ?? $contribution->proposal()[$term][(string) $key] ?? null;
        if (!$entry || !is_array($entry)) {
            return null;
        }

        $status = $entry[ProposalRevisionService::ENTRY_REVISION_STATUS] ?? null;

        return in_array($status, [ProposalRevisionService::STATUS_ACCEPTED, ProposalRevisionService::STATUS_REJECTED], true)
            ? $status
            : null;
    }

    public function shouldStrikeInValidatedView(
        ContributionRepresentation $contribution,
        string $term,
        ?int $proposalKey,
        string $process,
        bool $isApprovedRemoval = false
    ): bool {
        if ($contribution->isValidated() !== true) {
            return false;
        }

        if ($isApprovedRemoval || ($process === 'remove' && $proposalKey !== null
            && $this->isApprovedRemoval($contribution, $term, $proposalKey, null))
        ) {
            return true;
        }

        if ($proposalKey === null) {
            return false;
        }

        return $this->revisionStatus($contribution, $term, $proposalKey) === ProposalRevisionService::STATUS_REJECTED;
    }

    public function rejectedValueClass(
        ContributionRepresentation $contribution,
        string $term,
        ?int $proposalKey,
        string $process,
        bool $isApprovedRemoval = false
    ): string {
        return $this->shouldStrikeInValidatedView($contribution, $term, $proposalKey, $process, $isApprovedRemoval)
            ? ' contribute-enhancements-rejected-value'
            : '';
    }

    /**
     * @return null|'accepted'|'rejected'
     */
    public function additionReviewOutcome(
        ContributionRepresentation $contribution,
        string $term,
        ?int $proposalKey
    ): ?string {
        if ($contribution->isValidated() !== true || $proposalKey === null) {
            return null;
        }

        $status = $this->revisionStatus($contribution, $term, $proposalKey);
        if ($status === ProposalRevisionService::STATUS_ACCEPTED) {
            return 'accepted';
        }

        if ($status === ProposalRevisionService::STATUS_REJECTED) {
            return 'rejected';
        }

        return null;
    }

    /**
     * @return null|'removed'
     */
    public function removalReviewOutcome(
        ContributionRepresentation $contribution,
        bool $isApprovedRemoval
    ): ?string {
        if ($contribution->isValidated() !== true || !$isApprovedRemoval) {
            return null;
        }

        return 'removed';
    }

    /**
     * @param array<string, mixed> $proposition
     */
    public function resolveProposalKey(
        ContributionRepresentation $contribution,
        string $term,
        int|string|null $displayKey,
        array $proposition = []
    ): ?int {
        if ($displayKey !== null && $this->revisionStatus($contribution, $term, (int) $displayKey) !== null) {
            return (int) $displayKey;
        }

        if ($displayKey !== null) {
            $entry = $contribution->proposal()[$term][$displayKey]
                ?? $contribution->proposal()[$term][(string) $displayKey]
                ?? null;
            if (is_array($entry) && (
                !empty($entry[ProposalRevisionService::ENTRY_REVISION_STATUS])
                || !empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED])
                || !empty($entry[ProposalRemovalApprovalService::ENTRY_RESTORED])
            )) {
                return (int) $displayKey;
            }
        }

        $fingerprint = $this->propositionDisplayFingerprint(
            $proposition['original'] ?? [],
            $proposition['proposed'] ?? []
        );
        if ($fingerprint === '') {
            return $displayKey !== null ? (int) $displayKey : null;
        }

        foreach ($contribution->proposal()[$term] ?? [] as $rawKey => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $entryFingerprint = $this->propositionDisplayFingerprint(
                $entry['original'] ?? [],
                $entry['proposed'] ?? []
            );
            if ($entryFingerprint === $fingerprint) {
                return (int) $rawKey;
            }
        }

        return $displayKey !== null ? (int) $displayKey : null;
    }

    public function isRevisionDecided(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key,
        bool $isValidated,
        string $process
    ): bool {
        if ($key === null || !in_array($process, ['update', 'add', 'append'], true)) {
            return false;
        }

        return $this->revisionStatus($contribution, $term, $key) !== null;
    }

    public function setStatusUrl(ContributionRepresentation $contribution): string
    {
        return '/admin/contribute-enhancements/contribution/' . (int) $contribution->id() . '/set-status';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function missingLanguageWarnings(ContributionRepresentation $contribution): array
    {
        return $this->languageService->missingLanguageWarnings($contribution);
    }

    public function languageWarningsUrl(ContributionRepresentation $contribution): string
    {
        $url = $this->getView()->plugin('url');

        return $url(
            'admin/contribute-enhancements/id',
            [
                'action' => 'language-warnings',
                'id' => $contribution->id(),
            ]
        );
    }

    public function acceptRevisionUrl(
        ContributionRepresentation $contribution,
        string $term,
        int $key
    ): string {
        return $this->contributionActionUrl($contribution, 'accept-revision', $term, $key, null);
    }

    public function rejectRevisionUrl(
        ContributionRepresentation $contribution,
        string $term,
        int $key
    ): string {
        return $this->contributionActionUrl($contribution, 'reject-revision', $term, $key, null);
    }

    public function setRevisionLanguageUrl(
        ContributionRepresentation $contribution,
        string $term,
        int $key
    ): string {
        return $this->contributionActionUrl($contribution, 'set-revision-language', $term, $key, null);
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function proposedLanguageFromEntry(array $entry): ?string
    {
        $language = $entry['proposed']['@language'] ?? $entry['original']['@language'] ?? null;
        if ($language === null || $language === '') {
            return null;
        }

        return (string) $language;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function isLiteralEntry(array $entry): bool
    {
        $proposed = $entry['proposed'] ?? [];
        $original = $entry['original'] ?? [];

        return array_key_exists('@value', $proposed) || array_key_exists('@value', $original);
    }

    public function reviewRevisionUrl(
        ContributionRepresentation $contribution,
        string $term,
        int $key
    ): string {
        return $this->contributionActionUrl($contribution, 'review-revision', $term, $key, null);
    }

    public function isApprovedRemoval(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key = null,
        ?int $valueIndex = null
    ): bool {
        $proposal = $contribution->proposal();

        if ($key !== null && isset($proposal[$term][$key])) {
            return !empty($proposal[$term][$key][ProposalRemovalApprovalService::ENTRY_APPROVED]);
        }

        foreach ($proposal[$term] ?? [] as $entry) {
            if (empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED])) {
                continue;
            }

            $storedIndex = $entry[ProposalRemovalApprovalService::ENTRY_VALUE_INDEX] ?? null;
            if ($valueIndex !== null && $storedIndex !== null && (int) $storedIndex === $valueIndex) {
                return true;
            }
        }

        return false;
    }

    /**
     * Restored removals stored only in the raw proposal (not shown as pending removals).
     *
     * @param array<int|string> $excludeKeys
     * @return array<int, array{key: int|string, entry: array<string, mixed>}>
     */
    public function restoredRemovalDisplayRows(
        ContributionRepresentation $contribution,
        string $term,
        array $excludeKeys = []
    ): array {
        $exclude = [];
        foreach ($excludeKeys as $key) {
            $exclude[(string) $key] = true;
        }

        $rows = [];
        foreach ($contribution->proposal()[$term] ?? [] as $key => $entry) {
            if (!$this->isRestoredRemovalEntry($entry)) {
                continue;
            }

            if (isset($exclude[(string) $key])) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'entry' => $entry,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function isRestoredRemovalEntry(array $entry): bool
    {
        return !empty($entry[ProposalRemovalApprovalService::ENTRY_RESTORED])
            && empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED]);
    }

    /**
     * Approved removals stored only in the raw proposal (not shown in normalized propositions).
     *
     * @param array<int|string> $excludeKeys
     * @return array<int, array{key: int|string, entry: array<string, mixed>}>
     */
    public function approvedRemovalDisplayRows(
        ContributionRepresentation $contribution,
        string $term,
        array $excludeKeys = []
    ): array {
        $exclude = [];
        foreach ($excludeKeys as $key) {
            $exclude[(string) $key] = true;
        }

        $rows = [];
        foreach ($contribution->proposal()[$term] ?? [] as $key => $entry) {
            if (empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED])) {
                continue;
            }

            if (isset($exclude[(string) $key])) {
                continue;
            }

            $rows[] = [
                'key' => $key,
                'entry' => $entry,
            ];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $original
     */
    public function formatApprovedOriginal(array $original): string
    {
        if (array_key_exists('@uri', $original)) {
            $label = (string) ($original['@label'] ?? '');
            $uri = (string) ($original['@uri'] ?? '');

            return $label !== '' ? $label . ': ' . $uri : $uri;
        }

        if (array_key_exists('@resource', $original)) {
            return (string) ($original['@resource'] ?? '');
        }

        $value = (string) ($original['@value'] ?? '');
        if (!empty($original['@language'])) {
            return $original['@language'] . ': ' . $value;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $row
     */
    protected function isApprovedRemovalForRow(
        ContributionRepresentation $contribution,
        string $term,
        array $row
    ): bool {
        $valueIndex = isset($row['value_index']) ? (int) $row['value_index'] : null;
        if ($this->isApprovedRemoval($contribution, $term, null, $valueIndex)) {
            return true;
        }

        $entry = $row['entry'] ?? null;
        if (!$entry) {
            return false;
        }

        foreach ($contribution->proposal()[$term] ?? [] as $proposalEntry) {
            if (empty($proposalEntry[ProposalRemovalApprovalService::ENTRY_APPROVED])) {
                continue;
            }

            if ($this->proposalEntryMatchesOriginal($proposalEntry, $entry['original'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array<string, mixed>> $values
     * @param array<string, mixed> $normalizedProposal
     * @return array<int, array{term: string, label: string, values: array<int, string>}>
     */
    public function removalSummary(
        ContributionRepresentation $contribution,
        array $values,
        array $normalizedProposal = []
    ): array {
        if (!$contribution->isPatch()) {
            return [];
        }

        $grouped = [];

        foreach ($normalizedProposal as $term => $propositions) {
            if ($term === 'template' || $term === 'media' || !is_array($propositions)) {
                continue;
            }

            foreach ($propositions as $key => $proposition) {
                if (($proposition['process'] ?? '') !== 'remove') {
                    continue;
                }

                $proposalKey = is_numeric($key) ? (int) $key : null;
                if ($this->isApprovedRemoval($contribution, $term, $proposalKey, null)) {
                    continue;
                }

                $rawEntry = $contribution->proposal()[$term][$key]
                    ?? $contribution->proposal()[$term][(string) $key]
                    ?? null;
                if (is_array($rawEntry) && $this->isRestoredRemovalEntry($rawEntry)) {
                    continue;
                }

                $label = $this->formatPropositionValue($proposition);
                if ($label === '') {
                    continue;
                }

                $grouped[$term][] = $label;
            }
        }

        foreach ($this->analyze($contribution) as $term => $rows) {
            foreach ($rows as $row) {
                if ($row['status'] !== ProposalDiffService::STATUS_DROPPED) {
                    continue;
                }

                if ($this->isApprovedRemovalForRow($contribution, $term, $row)) {
                    continue;
                }

                if ($this->isRestoredRemovalForRow($contribution, $term, $row)) {
                    continue;
                }

                $label = $this->formatValueLabel($row['value'] ?? null);
                if ($label === '') {
                    continue;
                }

                $grouped[$term] ??= [];
                if (!in_array($label, $grouped[$term], true)) {
                    $grouped[$term][] = $label;
                }
            }
        }

        $summary = [];
        foreach ($grouped as $term => $labels) {
            $summary[] = [
                'term' => $term,
                'label' => $this->propertyLabel($term, $values),
                'values' => $labels,
            ];
        }

        return $summary;
    }

    /**
     * @param array<string, mixed> $proposition
     */
    public function formatPropositionValue(array $proposition): string
    {
        if (array_key_exists('@uri', $proposition['original'] ?? [])) {
            $label = (string) ($proposition['original']['@label'] ?? '');
            $uri = (string) ($proposition['original']['@uri'] ?? '');

            return $label !== '' ? $label . ': ' . $uri : $uri;
        }

        if (array_key_exists('@resource', $proposition['original'] ?? [])) {
            $value = $proposition['value'] ?? null;

            return $this->formatValueLabel($value);
        }

        $value = (string) ($proposition['original']['@value'] ?? '');
        $language = $proposition['value'] ? $proposition['value']->lang() : null;
        if ($language) {
            return $language . ': ' . $value;
        }

        return $value;
    }

    public function formatValue(?\Omeka\Api\Representation\ValueRepresentation $value): string
    {
        return $this->formatValueLabel($value);
    }

    public function formatValueLabel(?\Omeka\Api\Representation\ValueRepresentation $value): string
    {
        if (!$value) {
            return '';
        }

        if ($value->type() === 'resource' || $value->valueResource()) {
            $resource = $value->valueResource();

            return $resource ? (string) $resource->displayTitle() : '';
        }

        if ($value->uri()) {
            $label = (string) $value->value();
            $uri = (string) $value->uri();

            return $label !== '' ? $label . ': ' . $uri : $uri;
        }

        $text = (string) $value->value();
        if ($value->lang()) {
            return $value->lang() . ': ' . $text;
        }

        return $text;
    }

    public function reproposeRemovalUrl(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key = null,
        ?int $valueIndex = null
    ): string {
        return $this->contributionActionUrl(
            $contribution,
            'repropose-removal',
            $term,
            $key,
            $this->resolveValueIndex($contribution, $term, $key, $valueIndex)
        );
    }

    public function isRestoredRemoval(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key = null,
        ?int $valueIndex = null
    ): bool {
        $proposal = $contribution->proposal()[$term] ?? [];

        if ($key !== null) {
            $entry = $proposal[$key] ?? $proposal[(string) $key] ?? null;

            return is_array($entry) && $this->isRestoredRemovalEntry($entry);
        }

        if ($valueIndex === null) {
            return false;
        }

        foreach ($proposal as $entry) {
            if (!is_array($entry) || !$this->isRestoredRemovalEntry($entry)) {
                continue;
            }

            $storedIndex = $entry[ProposalRemovalApprovalService::ENTRY_VALUE_INDEX] ?? null;
            if ($storedIndex !== null && (int) $storedIndex === $valueIndex) {
                return true;
            }

            $resource = $contribution->resource();
            if (!$resource) {
                continue;
            }

            $values = $resource->value($term, ['all' => true]) ?: [];
            if (isset($values[$valueIndex]) && $this->proposalEntryMatchesValue($entry, $values[$valueIndex])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function isRestoredRemovalForRow(
        ContributionRepresentation $contribution,
        string $term,
        array $row
    ): bool {
        $valueIndex = isset($row['value_index']) ? (int) $row['value_index'] : null;
        if ($valueIndex !== null && $this->isRestoredRemoval($contribution, $term, null, $valueIndex)) {
            return true;
        }

        $entry = $row['entry'] ?? null;
        if (!$entry) {
            return false;
        }

        foreach ($contribution->proposal()[$term] ?? [] as $proposalEntry) {
            if (!$this->isRestoredRemovalEntry($proposalEntry)) {
                continue;
            }

            if ($this->proposalEntryMatchesOriginal($proposalEntry, $entry['original'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    public function restoreValueUrl(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key = null,
        ?int $valueIndex = null
    ): string {
        return $this->contributionActionUrl($contribution, 'restore-value', $term, $key, $valueIndex);
    }

    public function approveRemovalUrl(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key = null,
        ?int $valueIndex = null
    ): string {
        return $this->contributionActionUrl(
            $contribution,
            'approve-removal',
            $term,
            $key,
            $this->resolveValueIndex($contribution, $term, $key, $valueIndex)
        );
    }

    public function resolveValueIndex(
        ContributionRepresentation $contribution,
        string $term,
        ?int $key = null,
        ?int $valueIndex = null
    ): ?int {
        if ($valueIndex !== null) {
            return $valueIndex;
        }

        if ($key === null) {
            return null;
        }

        $proposal = $contribution->proposal();
        $original = $proposal[$term][$key]['original'] ?? null;
        if (!$original) {
            return null;
        }

        foreach ($this->analyze($contribution)[$term] ?? [] as $row) {
            if (!in_array($row['status'], [ProposalDiffService::STATUS_REMOVED, ProposalDiffService::STATUS_DROPPED], true)) {
                continue;
            }

            $entry = $row['entry'] ?? null;
            if ($entry && $this->proposalEntryMatchesOriginal($entry, $original)) {
                return isset($row['value_index']) ? (int) $row['value_index'] : null;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $original
     */
    protected function proposalEntryMatchesOriginal(array $entry, array $original): bool
    {
        $entryOriginal = $entry['original'] ?? [];

        if (array_key_exists('@uri', $original)) {
            return ($entryOriginal['@uri'] ?? '') === ($original['@uri'] ?? '')
                && ($entryOriginal['@label'] ?? '') === ($original['@label'] ?? '');
        }

        if (array_key_exists('@resource', $original)) {
            return (int) ($entryOriginal['@resource'] ?? 0) === (int) ($original['@resource'] ?? 0);
        }

        return (string) ($entryOriginal['@value'] ?? '') === (string) ($original['@value'] ?? '');
    }

    protected function contributionActionUrl(
        ContributionRepresentation $contribution,
        string $action,
        string $term,
        ?int $key = null,
        ?int $valueIndex = null
    ): string {
        $query = ['term' => $term];
        if ($key !== null) {
            $query['key'] = $key;
        }
        if ($valueIndex !== null) {
            $query['value_index'] = $valueIndex;
        }

        $url = $this->getView()->plugin('url');

        return $url(
            'admin/contribute-enhancements/id',
            [
                'action' => $action,
                'id' => $contribution->id(),
            ],
            ['query' => $query]
        );
    }

    /**
     * @param array<string, array<string, mixed>> $values
     */
    protected function propertyLabel(string $term, array $values): string
    {
        $translate = $this->getView()->plugin('translate');

        if (isset($values[$term]['alternate_label']) && $values[$term]['alternate_label']) {
            return (string) $values[$term]['alternate_label'];
        }

        if (isset($values[$term]['property'])) {
            return (string) $translate($values[$term]['property']->label());
        }

        return $term;
    }
}
