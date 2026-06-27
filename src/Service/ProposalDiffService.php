<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Common\Stdlib\EasyMeta;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ResourceTemplateRepresentation;
use Omeka\Api\Representation\ValueRepresentation;

/**
 * Compares a patch contribution proposal against the target resource.
 */
class ProposalDiffService
{
    public const STATUS_UNCHANGED = 'unchanged';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_ADDED = 'added';
    public const STATUS_REMOVED = 'removed';
    public const STATUS_DROPPED = 'dropped';

    protected EasyMeta $easyMeta;

    public function __construct(EasyMeta $easyMeta)
    {
        $this->easyMeta = $easyMeta;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function analyze(
        ?AbstractResourceEntityRepresentation $resource,
        array $proposal,
        array $editableTerms
    ): array {
        if (!$resource) {
            return [];
        }

        $proposal = $this->stripSpecialProposalKeys($proposal);

        foreach ($editableTerms as $term => $_) {
            if ($term === 'file') {
                continue;
            }

            $resourceValues = $resource->value($term, ['all' => true]) ?: [];
            $termProposal = $proposal[$term] ?? [];
            $unmatched = $termProposal;
            $rows = [];

            foreach ($resourceValues as $valueIndex => $value) {
                $matchKey = $this->findMatchingProposalKey($unmatched, $value);
                if ($matchKey === null) {
                    $rows[] = $this->buildRow(self::STATUS_DROPPED, $value, null, (int) $valueIndex);
                    continue;
                }

                $entry = $unmatched[$matchKey];
                unset($unmatched[$matchKey]);
                $rows[] = $this->buildRow($this->statusFromEntry($entry, $value), $value, $entry, (int) $valueIndex);
            }

            foreach ($unmatched as $entry) {
                if ($this->isNewEntry($entry)) {
                    $rows[] = $this->buildRow(self::STATUS_ADDED, null, $entry);
                }
            }

            if ($rows) {
                $result[$term] = $rows;
            }
        }

        return $result;
    }

    /**
     * Audit diff from frozen proposal/baseline data (not live item metadata).
     *
     * @param array<string, mixed> $proposal
     * @param array<string, true> $editableTerms
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function analyzeAudit(array $proposal, array $editableTerms): array
    {
        $baseline = $proposal[ProposalBaselineService::BASELINE_KEY] ?? [];
        if (!is_array($baseline)) {
            $baseline = [];
        }

        $proposal = $this->stripSpecialProposalKeys($proposal);
        $result = [];

        foreach ($editableTerms as $term => $_) {
            if ($term === 'file') {
                continue;
            }

            $rows = [];
            $termProposal = $proposal[$term] ?? [];
            $matchedBaseline = [];

            foreach ($termProposal as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $rows[] = $this->buildRow($this->entryAuditStatus($entry), null, $entry);
                $fingerprint = $this->payloadFingerprint($entry['original'] ?? []);
                if ($fingerprint !== '') {
                    $matchedBaseline[$fingerprint] = true;
                }
            }

            foreach ($baseline[$term] ?? [] as $baselinePayload) {
                if (!is_array($baselinePayload)) {
                    continue;
                }
                $fingerprint = $this->payloadFingerprint($baselinePayload);
                if ($fingerprint === '' || isset($matchedBaseline[$fingerprint])) {
                    continue;
                }

                $rows[] = $this->buildRow(self::STATUS_DROPPED, null, [
                    'original' => $baselinePayload,
                    'proposed' => $this->emptyPayloadFor($baselinePayload),
                ]);
            }

            if ($rows) {
                $result[$term] = $rows;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function entryAuditStatus(array $entry): string
    {
        if ($this->isExplicitRemoval($entry)) {
            return self::STATUS_REMOVED;
        }
        if ($this->isNewEntry($entry)) {
            return self::STATUS_ADDED;
        }
        if ($this->payloadsEqual($entry['original'] ?? [], $entry['proposed'] ?? [])) {
            return self::STATUS_UNCHANGED;
        }

        return self::STATUS_UPDATED;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    protected function payloadsEqual(array $left, array $right): bool
    {
        return $this->payloadFingerprint($left) === $this->payloadFingerprint($right);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function payloadFingerprint(array $payload): string
    {
        if (array_key_exists('@uri', $payload)) {
            if (($payload['@uri'] ?? '') === '' && ($payload['@label'] ?? '') === '') {
                return '';
            }
        } elseif (array_key_exists('@resource', $payload)) {
            if (!(int) ($payload['@resource'] ?? 0)) {
                return '';
            }
        } elseif (($payload['@value'] ?? '') === '') {
            return '';
        }

        return (new ProposalDedupeService())->fingerprint($payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function emptyPayloadFor(array $payload): array
    {
        if (array_key_exists('@uri', $payload)) {
            return ['@uri' => '', '@label' => ''];
        }
        if (array_key_exists('@resource', $payload)) {
            return ['@resource' => 0];
        }

        $empty = ['@value' => ''];
        if (!empty($payload['@language'])) {
            $empty['@language'] = $payload['@language'];
        }

        return $empty;
    }

    /**
     * @return array<string, int> property term => dropped value count
     */
    public function droppedCounts(
        ?AbstractResourceEntityRepresentation $resource,
        array $proposal,
        array $editableTerms
    ): array {
        $counts = [];
        foreach ($this->analyze($resource, $proposal, $editableTerms) as $term => $rows) {
            $dropped = 0;
            foreach ($rows as $row) {
                if ($row['status'] === self::STATUS_DROPPED) {
                    ++$dropped;
                }
            }
            if ($dropped) {
                $counts[$term] = $dropped;
            }
        }

        return $counts;
    }

    public function hasDroppedValues(
        ?AbstractResourceEntityRepresentation $resource,
        array $proposal,
        array $editableTerms
    ): bool {
        return (bool) $this->droppedCounts($resource, $proposal, $editableTerms);
    }

    /**
     * @return array<string, true>
     */
    public function editableTermsFromTemplate(?ResourceTemplateRepresentation $template): array
    {
        if (!$template || !method_exists($template, 'resourceTemplateProperties')) {
            return [];
        }

        $editable = [];
        foreach ($template->resourceTemplateProperties() as $templateProperty) {
            if (!method_exists($templateProperty, 'mainData')) {
                continue;
            }
            $mainData = $templateProperty->mainData();
            if ($mainData && $mainData->dataValue('editable')) {
                $editable[$templateProperty->property()->term()] = true;
            }
        }

        return $editable;
    }

    /**
     * @param array<int|string, array<string, mixed>> $proposals
     */
    public function findMatchingProposalKey(array $proposals, ValueRepresentation $value): ?int
    {
        $baseType = $this->easyMeta->dataTypeMain($value->type()) ?? 'literal';

        foreach ($proposals as $key => $proposal) {
            if ($this->originalMatchesValue($proposal['original'] ?? [], $value, $baseType)) {
                return (int) $key;
            }
        }

        // After a validated update the resource reflects the proposed value.
        // Pure additions may reuse an existing item value and must stay distinct.
        foreach ($proposals as $key => $proposal) {
            if ($this->isNewEntry($proposal)) {
                continue;
            }
            if ($this->proposedMatchesValue($proposal['proposed'] ?? [], $value, $baseType)) {
                return (int) $key;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $proposed
     */
    public function proposedMatchesValue(array $proposed, ValueRepresentation $value, ?string $baseType = null): bool
    {
        $baseType ??= $this->easyMeta->dataTypeMain($value->type()) ?? 'literal';

        if ($baseType === 'uri') {
            return ($proposed['@uri'] ?? '') === ($value->uri() ?? '')
                && ($proposed['@label'] ?? '') === ($value->value() ?? '');
        }

        if ($baseType === 'resource') {
            $resource = $value->valueResource();

            return (int) ($proposed['@resource'] ?? 0) === (int) ($resource ? $resource->id() : 0);
        }

        return (string) ($proposed['@value'] ?? '') === (string) $value->value();
    }

    /**
     * @param array<string, mixed> $original
     */
    public function originalMatchesValue(array $original, ValueRepresentation $value, ?string $baseType = null): bool
    {
        $baseType ??= $this->easyMeta->dataTypeMain($value->type()) ?? 'literal';

        if ($baseType === 'uri') {
            return ($original['@uri'] ?? '') === ($value->uri() ?? '')
                && ($original['@label'] ?? '') === ($value->value() ?? '');
        }

        if ($baseType === 'resource') {
            $resource = $value->valueResource();

            return (int) ($original['@resource'] ?? 0) === (int) ($resource ? $resource->id() : 0);
        }

        return (string) ($original['@value'] ?? '') === (string) $value->value();
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function isExplicitRemoval(array $entry): bool
    {
        if (array_key_exists('@uri', $entry['original'] ?? [])) {
            return ($entry['proposed']['@uri'] ?? '') === ''
                && ($entry['original']['@uri'] ?? '') !== '';
        }

        if (array_key_exists('@resource', $entry['original'] ?? [])) {
            return !(int) ($entry['proposed']['@resource'] ?? 0)
                && (int) ($entry['original']['@resource'] ?? 0);
        }

        return ($entry['original']['@value'] ?? '') !== ''
            && ($entry['proposed']['@value'] ?? '') === '';
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function isNewEntry(array $entry): bool
    {
        $original = $entry['original'] ?? [];
        if (array_key_exists('@uri', $original)) {
            return ($original['@uri'] ?? '') === '';
        }
        if (array_key_exists('@resource', $original)) {
            return !(int) ($original['@resource'] ?? 0);
        }

        return ($original['@value'] ?? '') === '';
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function statusFromEntry(array $entry, ValueRepresentation $value): string
    {
        if ($this->isExplicitRemoval($entry)) {
            return self::STATUS_REMOVED;
        }

        $baseType = $this->easyMeta->dataTypeMain($value->type()) ?? 'literal';
        if ($baseType === 'uri') {
            $same = ($entry['original']['@uri'] ?? '') === ($entry['proposed']['@uri'] ?? '')
                && ($entry['original']['@label'] ?? '') === ($entry['proposed']['@label'] ?? '');

            return $same ? self::STATUS_UNCHANGED : self::STATUS_UPDATED;
        }

        if ($baseType === 'resource') {
            return (int) ($entry['original']['@resource'] ?? 0) === (int) ($entry['proposed']['@resource'] ?? 0)
                ? self::STATUS_UNCHANGED
                : self::STATUS_UPDATED;
        }

        return (string) ($entry['original']['@value'] ?? '') === (string) ($entry['proposed']['@value'] ?? '')
            ? self::STATUS_UNCHANGED
            : self::STATUS_UPDATED;
    }

    /**
     * @param array<string, mixed>|null $entry
     * @return array<string, mixed>
     */
    protected function buildRow(
        string $status,
        ?ValueRepresentation $value,
        ?array $entry,
        ?int $valueIndex = null
    ): array {
        return [
            'status' => $status,
            'value' => $value,
            'entry' => $entry,
            'value_index' => $valueIndex,
        ];
    }

    /**
     * @param array<string, mixed> $proposal
     * @return array<string, mixed>
     */
    protected function stripSpecialProposalKeys(array $proposal): array
    {
        unset($proposal['template'], $proposal['media'], $proposal[ProposalBaselineService::BASELINE_KEY]);

        return $proposal;
    }
}
