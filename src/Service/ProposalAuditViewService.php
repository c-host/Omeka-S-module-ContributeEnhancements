<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;
use Common\Stdlib\EasyMeta;

/**
 * Builds guest audit views from frozen proposal data instead of live item metadata.
 */
class ProposalAuditViewService
{
    protected ProposalBaselineService $baselineService;

    protected ProposalDiffService $diffService;

    protected EasyMeta $easyMeta;

    public function __construct(
        ProposalBaselineService $baselineService,
        ProposalDiffService $diffService,
        EasyMeta $easyMeta
    ) {
        $this->baselineService = $baselineService;
        $this->diffService = $diffService;
        $this->easyMeta = $easyMeta;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function buildDisplayFields(ContributionRepresentation $contribution): array
    {
        if (!$contribution->isPatch()) {
            return [];
        }

        $template = $contribution->resourceTemplate();
        if (!$template) {
            return [];
        }

        $proposal = $contribution->proposal();
        $baseline = $this->baselineService->getBaseline($proposal);
        $termProposal = $this->baselineService->stripSpecialKeys($proposal);

        $fields = [];
        foreach ($template->resourceTemplateProperties() as $templateProperty) {
            $property = $templateProperty->property();
            $term = $property->term();
            $dataType = $templateProperty->dataType() ?: 'literal';
            $baseType = $this->easyMeta->dataTypeMain($dataType) ?? 'literal';

            $contributions = [];
            $matchedBaseline = [];

            foreach ($termProposal[$term] ?? [] as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $contributions[] = $this->entryToContributionRow($entry, $dataType, $baseType);
                $baselineKey = $this->baselineFingerprint($entry['original'] ?? []);
                if ($baselineKey !== '') {
                    $matchedBaseline[$baselineKey] = true;
                }
            }

            foreach ($baseline[$term] ?? [] as $baselineValue) {
                $fingerprint = $this->baselineFingerprint($baselineValue);
                if ($fingerprint === '' || isset($matchedBaseline[$fingerprint])) {
                    continue;
                }

                $contributions[] = [
                    'type' => $dataType,
                    'basetype' => $baseType,
                    'new' => false,
                    'empty' => false,
                    'original' => $this->payloadToContributionSide($baselineValue, $baseType),
                    'proposed' => $this->emptyProposed($baseType),
                ];
            }

            if (!$contributions) {
                continue;
            }

            $fields[$term] = [
                'template_property' => $templateProperty,
                'property' => $property,
                'alternate_label' => $templateProperty->alternateLabel(),
                'alternate_comment' => $templateProperty->alternateComment(),
                'contributions' => $contributions,
            ];
        }

        return $fields;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildChangeHistoryRows(ContributionRepresentation $contribution): array
    {
        if (!$contribution->isPatch()) {
            return [];
        }

        $proposal = $this->baselineService->stripSpecialKeys($contribution->proposal());
        $rows = [];

        foreach ($proposal as $term => $entries) {
            if (!is_array($entries)) {
                continue;
            }

            foreach ($entries as $key => $entry) {
                if (!is_array($entry) || !$this->entryHasAuditTrail($entry)) {
                    continue;
                }

                $rawEntry = $contribution->proposal()[$term][$key] ?? $entry;
                $rows[] = [
                    'term' => $term,
                    'entry' => $entry,
                    'raw_entry' => $rawEntry,
                    'process' => $this->entryProcess($entry),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function entryHasAuditTrail(array $entry): bool
    {
        if (!empty($entry[ProposalRevisionService::ENTRY_REVISION_STATUS])) {
            return true;
        }
        if (!empty($entry[ProposalRemovalApprovalService::ENTRY_APPROVED])
            || !empty($entry[ProposalRemovalApprovalService::ENTRY_RESTORED])) {
            return true;
        }
        if ($this->diffService->isNewEntry($entry)) {
            return !$this->isBlankPayload($entry['proposed'] ?? []);
        }
        if ($this->diffService->isExplicitRemoval($entry)) {
            return true;
        }

        return !$this->payloadsEqual($entry['original'] ?? [], $entry['proposed'] ?? []);
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function entryProcess(array $entry): string
    {
        if ($this->diffService->isExplicitRemoval($entry)) {
            return 'remove';
        }
        if ($this->diffService->isNewEntry($entry)) {
            return 'add';
        }

        return 'update';
    }

    /**
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    protected function entryToContributionRow(array $entry, string $dataType, string $baseType): array
    {
        $original = $entry['original'] ?? [];
        $proposed = $entry['proposed'] ?? [];

        return [
            'type' => $dataType,
            'basetype' => $baseType,
            'new' => $this->diffService->isNewEntry($entry),
            'empty' => $this->isBlankPayload($proposed) && !$this->diffService->isExplicitRemoval($entry),
            'original' => $this->payloadToContributionSide($original, $baseType),
            'proposed' => $this->payloadToContributionSide($proposed, $baseType),
            'revision_status' => $entry[ProposalRevisionService::ENTRY_REVISION_STATUS] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function payloadToContributionSide(array $payload, string $baseType): array
    {
        if ($baseType === 'uri') {
            return [
                'value' => null,
                '@value' => null,
                '@resource' => null,
                '@uri' => $payload['@uri'] ?? '',
                '@label' => $payload['@label'] ?? '',
            ];
        }

        if ($baseType === 'resource') {
            return [
                'value' => null,
                '@value' => null,
                '@resource' => (int) ($payload['@resource'] ?? 0),
                '@uri' => null,
                '@label' => null,
            ];
        }

        return [
            'value' => null,
            '@value' => $payload['@value'] ?? '',
            '@resource' => null,
            '@uri' => null,
            '@label' => null,
            '@language' => $payload['@language'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function emptyProposed(string $baseType): array
    {
        if ($baseType === 'uri') {
            return ['@value' => null, '@resource' => null, '@uri' => '', '@label' => ''];
        }
        if ($baseType === 'resource') {
            return ['@value' => null, '@resource' => 0, '@uri' => null, '@label' => null];
        }

        return ['@value' => '', '@resource' => null, '@uri' => null, '@label' => null];
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function isBlankPayload(array $payload): bool
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
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    protected function payloadsEqual(array $left, array $right): bool
    {
        return $this->baselineFingerprint($left) === $this->baselineFingerprint($right);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function baselineFingerprint(array $payload): string
    {
        if ($this->isBlankPayload($payload)) {
            return '';
        }

        return (new ProposalDedupeService())->fingerprint($payload);
    }
}
