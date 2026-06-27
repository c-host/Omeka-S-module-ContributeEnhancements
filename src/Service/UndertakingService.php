<?php declare(strict_types=1);

namespace ContributeEnhancements\Service;

use Contribute\Api\Representation\ContributionRepresentation;
use Omeka\Api\Manager as ApiManager;

class UndertakingService
{
    protected ApiManager $api;

    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    public function ensureUndertaken(ContributionRepresentation $contribution): void
    {
        if ($contribution->isUndertaken()) {
            return;
        }

        $this->api->update('contributions', $contribution->id(), [
            'o-module-contribute:undertaken' => true,
        ], [], ['isPartial' => true]);
    }

    public function syncWithValidationStatus(ContributionRepresentation $contribution): void
    {
        if ($contribution->isValidated() === null) {
            return;
        }

        $this->ensureUndertaken($contribution);
    }

    public function isToggleLocked(ContributionRepresentation $contribution): bool
    {
        return $contribution->isValidated() !== null;
    }
}
