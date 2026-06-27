<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Common\Stdlib\EasyMeta;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ValueRepresentation;

/**
 * Adds explicit removal entries for editable values missing from a proposal.
 */
class ProposalNormalizer
{
    protected ProposalDiffService $diffService;

    protected EasyMeta $easyMeta;

    public function __construct(ProposalDiffService $diffService, EasyMeta $easyMeta)
    {
        $this->diffService = $diffService;
        $this->easyMeta = $easyMeta;
    }

    /**
     * @param array<string, mixed> $proposal
     * @return array<string, mixed>
     */
    public function normalize(
        array $proposal,
        ?AbstractResourceEntityRepresentation $resource,
        array $editableTerms
    ): array {
        if (!$resource) {
            return $proposal;
        }

        foreach ($editableTerms as $term => $_) {
            if ($term === 'file') {
                continue;
            }

            $resourceValues = $resource->value($term, ['all' => true]) ?: [];
            if (!$resourceValues) {
                continue;
            }

            $termProposal = $proposal[$term] ?? [];
            $unmatched = $termProposal;

            foreach ($resourceValues as $value) {
                $matchKey = $this->diffService->findMatchingProposalKey($unmatched, $value);
                if ($matchKey === null) {
                    $proposal[$term][] = $this->buildRemovalEntry($value);
                    continue;
                }

                unset($unmatched[$matchKey]);
            }
        }

        return $proposal;
    }

    public function buildRemovalEntry(ValueRepresentation $value): array
    {
        return $this->buildEntryFromValue($value, false);
    }

    public function buildKeepEntry(ValueRepresentation $value): array
    {
        return $this->buildEntryFromValue($value, true);
    }

    protected function buildEntryFromValue(ValueRepresentation $value, bool $keep): array
    {
        $baseType = $this->easyMeta->dataTypeMain($value->type()) ?? 'literal';

        if ($baseType === 'uri') {
            $payload = [
                '@uri' => $value->uri() ?? '',
                '@label' => $value->value() ?? '',
            ];

            return [
                'original' => $payload,
                'proposed' => $keep ? $payload : ['@uri' => '', '@label' => ''],
            ];
        }

        if ($baseType === 'resource') {
            $resource = $value->valueResource();
            $resourceId = $resource ? $resource->id() : null;

            return [
                'original' => [
                    '@resource' => $resourceId,
                ],
                'proposed' => [
                    '@resource' => $keep ? $resourceId : null,
                ],
            ];
        }

        $entry = [
            'original' => [
                '@value' => $value->value(),
            ],
            'proposed' => [
                '@value' => $keep ? $value->value() : '',
            ],
        ];

        if ($value->lang()) {
            $entry['original']['@language'] = $value->lang();
            if ($keep) {
                $entry['proposed']['@language'] = $value->lang();
            }
        }

        return $entry;
    }
}
