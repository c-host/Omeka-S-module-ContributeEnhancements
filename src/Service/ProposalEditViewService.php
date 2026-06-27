<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;
use ContributeEnhancements\Service\ProposalRevisionService as Revision;

/**
 * Aligns contributor edit-form values with the live proposal / review state.
 */
class ProposalEditViewService
{
    protected ProposalDiffService $diffService;

    public function __construct(ProposalDiffService $diffService)
    {
        $this->diffService = $diffService;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function enhanceFields(array $fields, ?ContributionRepresentation $contribution): array
    {
        if (!$contribution || !$contribution->isPatch()) {
            return $fields;
        }

        $proposal = $contribution->proposal();
        unset($proposal['template'], $proposal['media']);

        foreach ($fields as $term => &$field) {
            if ($term === 'file' || !isset($proposal[$term]) || !is_array($proposal[$term])) {
                continue;
            }

            $termProposal = $proposal[$term];
            $matchedKeys = [];
            $updateEntries = $this->updateEntries($termProposal);

            foreach ($field['contributions'] as &$fieldContribution) {
                if ($this->isBlankContributionRow($fieldContribution)) {
                    continue;
                }

                $matchKey = $this->findUpdateEntryKey($updateEntries, $fieldContribution, $matchedKeys);
                if ($matchKey !== null) {
                    $entry = $updateEntries[$matchKey];
                    $matchedKeys[$matchKey] = true;
                    $this->applyEntryToContribution($fieldContribution, $entry);
                    continue;
                }

                $matchKey = $this->findAdditionEntryKey($termProposal, $fieldContribution, $matchedKeys);
                if ($matchKey !== null) {
                    $entry = $termProposal[$matchKey];
                    $matchedKeys[$matchKey] = true;
                    $this->applyEntryToContribution($fieldContribution, $entry);
                }
            }
            unset($fieldContribution);

            foreach ($termProposal as $key => $entry) {
                if (!is_array($entry) || isset($matchedKeys[$key])) {
                    continue;
                }
                if (!$this->diffService->isNewEntry($entry) || $this->isBlankProposed($entry['proposed'] ?? [])) {
                    continue;
                }

                $field['contributions'][] = $this->buildAdditionRow($entry);
                $matchedKeys[$key] = true;
            }

            $field['contributions'] = array_values($field['contributions']);
        }
        unset($field);

        return $fields;
    }

    /**
     * @param array<int|string, array<string, mixed>> $termProposal
     * @return array<int|string, array<string, mixed>>
     */
    protected function updateEntries(array $termProposal): array
    {
        $updates = [];
        foreach ($termProposal as $key => $entry) {
            if (is_array($entry) && !$this->diffService->isNewEntry($entry)) {
                $updates[$key] = $entry;
            }
        }

        return $updates;
    }

    /**
     * @param array<int|string, array<string, mixed>> $updateEntries
     * @param array<int|string, true> $matchedKeys
     */
    protected function findUpdateEntryKey(
        array $updateEntries,
        array $fieldContribution,
        array $matchedKeys
    ): int|string|null {
        $original = $fieldContribution['original'] ?? [];
        $baseType = $fieldContribution['basetype'] ?? 'literal';

        foreach ($updateEntries as $key => $entry) {
            if (isset($matchedKeys[$key])) {
                continue;
            }
            if ($this->originalMatchesContribution($entry['original'] ?? [], $original, $baseType)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entryOriginal
     * @param array<string, mixed> $contributionOriginal
     */
    protected function originalMatchesContribution(
        array $entryOriginal,
        array $contributionOriginal,
        string $baseType
    ): bool {
        if ($baseType === 'uri') {
            return ($entryOriginal['@uri'] ?? '') === ($contributionOriginal['@uri'] ?? '')
                && ($entryOriginal['@label'] ?? '') === ($contributionOriginal['@label'] ?? '');
        }

        if ($baseType === 'resource') {
            return (int) ($entryOriginal['@resource'] ?? 0) === (int) ($contributionOriginal['@resource'] ?? 0);
        }

        return (string) ($entryOriginal['@value'] ?? '') === (string) ($contributionOriginal['@value'] ?? '');
    }

    /**
     * @param array<int|string, array<string, mixed>> $termProposal
     * @param array<int|string, true> $matchedKeys
     */
    protected function findAdditionEntryKey(
        array $termProposal,
        array $fieldContribution,
        array $matchedKeys
    ): int|string|null {
        if (empty($fieldContribution['new'])) {
            return null;
        }

        $proposed = $fieldContribution['proposed'] ?? [];
        $baseType = $fieldContribution['basetype'] ?? 'literal';

        foreach ($termProposal as $key => $entry) {
            if (isset($matchedKeys[$key]) || !$this->diffService->isNewEntry($entry)) {
                continue;
            }
            if ($this->proposedMatchesContribution($entry['proposed'] ?? [], $proposed, $baseType)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $entryProposed
     * @param array<string, mixed> $contributionProposed
     */
    protected function proposedMatchesContribution(
        array $entryProposed,
        array $contributionProposed,
        string $baseType
    ): bool {
        if ($baseType === 'uri') {
            return ($entryProposed['@uri'] ?? '') === ($contributionProposed['@uri'] ?? '')
                && ($entryProposed['@label'] ?? '') === ($contributionProposed['@label'] ?? '');
        }

        if ($baseType === 'resource') {
            return (int) ($entryProposed['@resource'] ?? 0) === (int) ($contributionProposed['@resource'] ?? 0);
        }

        return (string) ($entryProposed['@value'] ?? '') === (string) ($contributionProposed['@value'] ?? '');
    }

    /**
     * @param array<string, mixed> $fieldContribution
     * @param array<string, mixed> $entry
     */
    protected function applyEntryToContribution(array &$fieldContribution, array $entry): void
    {
        $status = $entry[Revision::ENTRY_REVISION_STATUS] ?? null;
        $source = ($status === Revision::STATUS_REJECTED)
            ? ($entry['original'] ?? [])
            : ($entry['proposed'] ?? []);

        $fieldContribution['revision_status'] = $status;
        $fieldContribution['empty'] = $this->isBlankProposed($source);
        $fieldContribution['proposed'] = $this->normalizeProposedPayload($source, $fieldContribution['basetype'] ?? 'literal');
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    protected function buildAdditionRow(array $entry): array
    {
        $status = $entry[Revision::ENTRY_REVISION_STATUS] ?? null;
        $proposed = ($status === Revision::STATUS_REJECTED)
            ? []
            : ($entry['proposed'] ?? []);

        $baseType = 'literal';
        if (array_key_exists('@uri', $proposed)) {
            $baseType = 'uri';
        } elseif (array_key_exists('@resource', $proposed)) {
            $baseType = 'resource';
        }

        return [
            'type' => $entry['type'] ?? $baseType,
            'basetype' => $baseType,
            'new' => true,
            'empty' => $this->isBlankProposed($proposed),
            'revision_status' => $status,
            'original' => [
                'value' => null,
                '@value' => null,
                '@resource' => null,
                '@uri' => null,
                '@label' => null,
            ],
            'proposed' => $this->normalizeProposedPayload($proposed, $baseType),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function isBlankProposed(array $payload): bool
    {
        if (array_key_exists('@uri', $payload)) {
            return ($payload['@uri'] ?? '') === '' && ($payload['@label'] ?? '') === '';
        }

        if (array_key_exists('@resource', $payload)) {
            return !(int) ($payload['@resource'] ?? 0);
        }

        return ($payload['@value'] ?? '') === '';
    }

    /**
     * @param array<string, mixed> $fieldContribution
     */
    protected function isBlankContributionRow(array $fieldContribution): bool
    {
        $original = $fieldContribution['original'] ?? [];

        return empty($original['value'])
            && ($original['@value'] ?? '') === ''
            && ($original['@uri'] ?? '') === ''
            && !(int) ($original['@resource'] ?? 0)
            && !empty($fieldContribution['empty'])
            && !empty($fieldContribution['new']);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, mixed>
     */
    protected function normalizeProposedPayload(array $source, string $baseType): array
    {
        if ($baseType === 'uri') {
            return [
                '@value' => null,
                '@resource' => null,
                '@uri' => $source['@uri'] ?? '',
                '@label' => $source['@label'] ?? '',
            ];
        }

        if ($baseType === 'resource') {
            return [
                '@value' => null,
                '@resource' => (int) ($source['@resource'] ?? 0),
                '@uri' => null,
                '@label' => null,
            ];
        }

        return [
            '@value' => $source['@value'] ?? '',
            '@resource' => null,
            '@uri' => null,
            '@label' => null,
        ];
    }
}
