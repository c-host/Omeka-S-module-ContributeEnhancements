<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;
use Omeka\Api\Manager as ApiManager;

class ProposalRevisionService
{
    public const ENTRY_REVISION_STATUS = 'o-module-contribute-enhancements:revision_status';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    protected ApiManager $api;

    protected ProposalDedupeService $dedupeService;

    protected ProposalApplicationService $applicationService;

    public function __construct(
        ApiManager $api,
        ProposalDedupeService $dedupeService,
        ProposalApplicationService $applicationService
    ) {
        $this->api = $api;
        $this->dedupeService = $dedupeService;
        $this->applicationService = $applicationService;
    }

    public function accept(
        ContributionRepresentation $contribution,
        string $term,
        int $key,
        ?string $language = null
    ): void {
        $this->requireEntry($contribution, $term, $key);

        $proposal = $contribution->proposal();
        if ($language !== null) {
            $language = trim($language);
            if ($language === '') {
                unset($proposal[$term][$key]['proposed']['@language']);
            } else {
                $proposal[$term][$key]['proposed']['@language'] = $language;
            }
        }

        $proposal[$term][$key][self::ENTRY_REVISION_STATUS] = self::STATUS_ACCEPTED;
        unset($proposal[$term][$key][ProposalApplicationService::ENTRY_APPLIED]);
        $this->saveProposal($contribution, $proposal);

        if ($contribution->isValidated() === true) {
            $contribution = $this->readContribution($contribution->id());
            $this->applicationService->applyAcceptedDecisions($contribution);
        }
    }

    public function reject(
        ContributionRepresentation $contribution,
        string $term,
        int $key
    ): void {
        $entry = $this->requireEntry($contribution, $term, $key);

        if (!empty($entry[ProposalApplicationService::ENTRY_APPLIED])) {
            $this->applicationService->revertSingleEntry($contribution, $term, $key);
        }

        $proposal = $contribution->proposal();
        $proposal[$term][$key][self::ENTRY_REVISION_STATUS] = self::STATUS_REJECTED;
        unset($proposal[$term][$key][ProposalApplicationService::ENTRY_APPLIED]);
        $this->saveProposal($contribution, $proposal);
    }

    public function review(
        ContributionRepresentation $contribution,
        string $term,
        int $key
    ): void {
        $entry = $this->requireEntry($contribution, $term, $key);
        $status = $entry[self::ENTRY_REVISION_STATUS] ?? null;

        if ($status === self::STATUS_ACCEPTED && !empty($entry[ProposalApplicationService::ENTRY_APPLIED])) {
            $this->applicationService->revertSingleEntry($contribution, $term, $key);
        }

        $proposal = $contribution->proposal();
        unset($proposal[$term][$key][self::ENTRY_REVISION_STATUS]);
        unset($proposal[$term][$key][ProposalApplicationService::ENTRY_APPLIED]);
        $this->saveProposal($contribution, $proposal);
    }

    /**
     * @param array<string, mixed> $entry
     */
    public function revisionStatus(array $entry): ?string
    {
        $status = $entry[self::ENTRY_REVISION_STATUS] ?? null;

        return in_array($status, [self::STATUS_ACCEPTED, self::STATUS_REJECTED], true)
            ? $status
            : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function requireEntry(
        ContributionRepresentation $contribution,
        string $term,
        int $key
    ): array {
        if (!$contribution->isPatch()) {
            throw new \InvalidArgumentException('Only patch contributions support revision review.');
        }

        $entry = $contribution->proposal()[$term][$key]
            ?? $contribution->proposal()[$term][(string) $key]
            ?? null;
        if (!$entry || !is_array($entry)) {
            throw new \InvalidArgumentException('Proposal entry not found.');
        }

        return $entry;
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
