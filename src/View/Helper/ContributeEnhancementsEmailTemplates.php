<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper;

use ContributeEnhancements\Service\ContributionEmailTemplateService;
use Laminas\View\Helper\AbstractHelper;

class ContributeEnhancementsEmailTemplates extends AbstractHelper
{
    protected ContributionEmailTemplateService $emailTemplateService;

    public function __construct(ContributionEmailTemplateService $emailTemplateService)
    {
        $this->emailTemplateService = $emailTemplateService;
    }

    /**
     * @return array<string, string>
     */
    public function __invoke(): array
    {
        return $this->emailTemplateService->templateTypes();
    }
}
