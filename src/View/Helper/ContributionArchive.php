<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper;

use ContributeEnhancements\Service\ArchiveService;
use Laminas\View\Helper\AbstractHelper;

class ContributionArchive extends AbstractHelper
{
    protected ArchiveService $archiveService;

    public function __construct(ArchiveService $archiveService)
    {
        $this->archiveService = $archiveService;
    }

    public function isArchived(int $contributionId): bool
    {
        return $this->archiveService->isArchived($contributionId);
    }

    public function archiveUrl(int $contributionId): string
    {
        return $this->actionUrl($contributionId, 'archive');
    }

    public function unarchiveUrl(int $contributionId): string
    {
        return $this->actionUrl($contributionId, 'unarchive');
    }

    protected function actionUrl(int $contributionId, string $action): string
    {
        $url = $this->getView()->plugin('url');

        return $url(
            'admin/contribute-enhancements/id',
            [
                'action' => $action,
                'id' => $contributionId,
            ]
        );
    }
}
