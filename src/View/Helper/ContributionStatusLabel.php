<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper;

use Contribute\Api\Representation\ContributionRepresentation;
use Laminas\View\Helper\AbstractHelper;

class ContributionStatusLabel extends AbstractHelper
{
    public function __invoke(ContributionRepresentation $contribution): string
    {
        $translate = $this->getView()->plugin('translate');
        $validated = $contribution->isValidated();

        if ($validated === null) {
            return (string) $translate('Pending review');
        }

        if ($validated) {
            return (string) $translate('Accepted');
        }

        return (string) $translate('Rejected');
    }

    public function guestValidationLabel(ContributionRepresentation $contribution): string
    {
        $translate = $this->getView()->plugin('translate');
        $validated = $contribution->isValidated();

        if ($validated === true) {
            return (string) $translate('Accepted and validated');
        }

        if ($validated === false) {
            return (string) $translate('Rejected');
        }

        if ($contribution->isUndertaken()) {
            return (string) $translate('Under review');
        }

        return (string) $translate('Pending review');
    }

    public function guestUndertakingLabel(ContributionRepresentation $contribution): string
    {
        $translate = $this->getView()->plugin('translate');

        return (string) $translate($contribution->isUndertaken() ? 'Undertaken' : 'Not undertaken');
    }
}
