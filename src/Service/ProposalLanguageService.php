<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;
use Omeka\Api\Manager as ApiManager;

class ProposalLanguageService
{
    protected ApiManager $api;

    protected ProposalDedupeService $dedupeService;

    protected ?ProposalApplicationService $applicationService;

    public function __construct(
        ApiManager $api,
        ProposalDedupeService $dedupeService,
        ?ProposalApplicationService $applicationService = null
    ) {
        $this->api = $api;
        $this->dedupeService = $dedupeService;
        $this->applicationService = $applicationService;
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

    /**
     * @param array<string, mixed> $entry
     */
    public function proposedLanguage(array $entry): ?string
    {
        $language = $entry['proposed']['@language'] ?? $entry['original']['@language'] ?? null;
        if ($language === null || $language === '') {
            return null;
        }

        return (string) $language;
    }

    public function normalizeLanguage(?string $language): ?string
    {
        if ($language === null) {
            return null;
        }

        $language = trim($language);
        if ($language === '') {
            return null;
        }

        if (str_contains($language, ',')) {
            throw new \InvalidArgumentException('Enter a single language tag only. Multiple tags separated by commas are not allowed.');
        }

        if (!preg_match('/^[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*$/', $language)) {
            throw new \InvalidArgumentException('Invalid language tag.');
        }

        return $language;
    }

    /**
     * Accepted literal revisions with no language tag on the proposed value.
     *
     * @return array<int, array<string, mixed>>
     */
    public function missingLanguageWarnings(ContributionRepresentation $contribution): array
    {
        if (!$contribution->isPatch() || $contribution->isValidated() === true) {
            return [];
        }

        $warnings = [];
        foreach ($contribution->proposal() as $term => $entries) {
            if ($term === 'template' || $term === 'media' || !is_array($entries)) {
                continue;
            }

            foreach ($entries as $key => $entry) {
                if (!is_array($entry) || !$this->shouldWarnMissingLanguage($entry)) {
                    continue;
                }

                $warnings[] = [
                    'term' => $term,
                    'key' => (int) $key,
                    'label' => $this->propertyLabel($term),
                    'value' => $this->formatLiteralPreview($entry),
                ];
            }
        }

        return $warnings;
    }

    public function hasMissingLanguageWarnings(ContributionRepresentation $contribution): bool
    {
        return (bool) $this->missingLanguageWarnings($contribution);
    }

    public function setProposedLanguage(
        ContributionRepresentation $contribution,
        string $term,
        int $key,
        ?string $language
    ): void {
        $entry = $this->requireLiteralEntry($contribution, $term, $key);
        $language = $this->normalizeLanguage($language);

        $proposal = $contribution->proposal();
        if ($language === null) {
            unset($proposal[$term][$key]['proposed']['@language']);
        } else {
            $proposal[$term][$key]['proposed']['@language'] = $language;
        }

        $this->saveProposal($contribution, $proposal);

        if ($this->applicationService
            && $contribution->isValidated() === true
            && ($entry[ProposalRevisionService::ENTRY_REVISION_STATUS] ?? null) === ProposalRevisionService::STATUS_ACCEPTED
            && !empty($entry[ProposalApplicationService::ENTRY_APPLIED])
        ) {
            $contribution = $this->readContribution($contribution->id());
            $this->applicationService->revertSingleEntry($contribution, $term, $key);
            $contribution = $this->readContribution($contribution->id());
            $this->applicationService->applyAcceptedDecisions($contribution);
        }
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function shouldWarnMissingLanguage(array $entry): bool
    {
        if (!$this->isLiteralEntry($entry)) {
            return false;
        }

        if (($entry[ProposalRevisionService::ENTRY_REVISION_STATUS] ?? null) !== ProposalRevisionService::STATUS_ACCEPTED) {
            return false;
        }

        if ($this->proposedLanguage($entry) !== null) {
            return false;
        }

        $proposed = (string) ($entry['proposed']['@value'] ?? '');

        return $proposed !== '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireLiteralEntry(
        ContributionRepresentation $contribution,
        string $term,
        int $key
    ): array {
        if (!$contribution->isPatch()) {
            throw new \InvalidArgumentException('Only patch contributions support language tags.');
        }

        $entry = $contribution->proposal()[$term][$key]
            ?? $contribution->proposal()[$term][(string) $key]
            ?? null;
        if (!$entry || !is_array($entry) || !$this->isLiteralEntry($entry)) {
            throw new \InvalidArgumentException('Literal proposal entry not found.');
        }

        return $entry;
    }

    /**
     * @param array<string, mixed> $entry
     */
    protected function formatLiteralPreview(array $entry): string
    {
        $value = (string) ($entry['proposed']['@value'] ?? '');
        if (strlen($value) > 80) {
            return substr($value, 0, 77) . '...';
        }

        return $value;
    }

    public function propertyLabel(string $term): string
    {
        $properties = $this->api->search('properties', ['term' => $term, 'limit' => 1])->getContent();
        $property = $properties ? reset($properties) : null;

        return $property ? (string) $property->label() : $term;
    }

    protected function readContribution(int $id): ContributionRepresentation
    {
        return $this->api->read('contributions', $id)->getContent();
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
