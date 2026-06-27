<?php declare(strict_types=1);

namespace ContributeEnhancements\Controller\Admin;

use ContributeEnhancements\Service\ArchiveService;
use ContributeEnhancements\Service\ContributionEmailTemplateService;
use ContributeEnhancements\Service\ProposalApplicationService;
use ContributeEnhancements\Service\ProposalLanguageService;
use ContributeEnhancements\Service\ProposalRemovalApprovalService;
use ContributeEnhancements\Service\ProposalRestoreService;
use ContributeEnhancements\Service\ProposalRevisionService;
use ContributeEnhancements\Service\UndertakingService;
use Contribute\Api\Representation\ContributionRepresentation;
use Laminas\Http\Response as HttpResponse;
use Laminas\Mvc\Controller\AbstractActionController;

class ContributionController extends AbstractActionController
{
    protected ProposalRestoreService $restoreService;

    protected ProposalRemovalApprovalService $approvalService;

    protected ArchiveService $archiveService;

    protected ContributionEmailTemplateService $emailTemplateService;

    protected ProposalRevisionService $revisionService;

    protected ProposalApplicationService $applicationService;

    protected UndertakingService $undertakingService;

    protected ProposalLanguageService $languageService;

    public function __construct(
        ProposalRestoreService $restoreService,
        ProposalRemovalApprovalService $approvalService,
        ArchiveService $archiveService,
        ContributionEmailTemplateService $emailTemplateService,
        ProposalRevisionService $revisionService,
        ProposalApplicationService $applicationService,
        UndertakingService $undertakingService,
        ProposalLanguageService $languageService
    ) {
        $this->restoreService = $restoreService;
        $this->approvalService = $approvalService;
        $this->archiveService = $archiveService;
        $this->emailTemplateService = $emailTemplateService;
        $this->revisionService = $revisionService;
        $this->applicationService = $applicationService;
        $this->undertakingService = $undertakingService;
        $this->languageService = $languageService;
    }

    public function restoreValueAction()
    {
        return $this->handleValueAction(function ($contribution, $term, $proposalKey, $resourceValueIndex) {
            $this->restoreService->restore($contribution, $term, $proposalKey, $resourceValueIndex);

            return [
                'status' => 'restored-value',
                'statusLabel' => $this->translate('Restored value'), // @translate
                'message' => $this->translate('The proposed removal was rejected and the original value is kept. Upon validation of this contribution, the item metadata will not be changed.'), // @translate
            ];
        }, 'removal-restored');
    }

    public function approveRemovalAction()
    {
        return $this->handleValueAction(function ($contribution, $term, $proposalKey, $resourceValueIndex) {
            $this->approvalService->approve($contribution, $term, $proposalKey, $resourceValueIndex);

            return [
                'status' => 'approved-removal',
                'statusLabel' => $this->translate('Removal approved'), // @translate
                'message' => $this->translate('Removal approved. Upon validation of this contribution, this value will be removed from the item metadata.'), // @translate
            ];
        }, 'approved');
    }

    public function reproposeRemovalAction()
    {
        return $this->handleValueAction(function ($contribution, $term, $proposalKey, $resourceValueIndex) {
            $this->approvalService->reproposeRemoval($contribution, $term, $proposalKey, $resourceValueIndex);

            return [
                'status' => 'reproposed-removal',
                'statusLabel' => $this->translate('Removal proposed'), // @translate
                'message' => $this->translate('You may review this removal decision again.'), // @translate
            ];
        }, 'removal-review');
    }

    public function acceptRevisionAction()
    {
        return $this->handleProposalKeyAction(function ($contribution, $term, $key) {
            $language = $this->params()->fromQuery('language');
            if ($language !== null) {
                $language = $this->languageService->normalizeLanguage((string) $language);
            }

            $this->revisionService->accept($contribution, $term, $key, $language);

            return [
                'status' => 'accepted-revision',
                'statusLabel' => $this->translate('Revision accepted'), // @translate
                'message' => $this->translate('The metadata revision was accepted. Upon validation of this contribution, the change will be reflected in the item metadata. You may review this decision before final validation.'), // @translate
            ];
        }, 'revision-accepted');
    }

    public function rejectRevisionAction()
    {
        return $this->handleProposalKeyAction(function ($contribution, $term, $key) {
            $this->revisionService->reject($contribution, $term, $key);

            return [
                'status' => 'rejected-revision',
                'statusLabel' => $this->translate('Revision rejected'), // @translate
                'message' => $this->translate('The metadata revision was rejected. The item metadata was not changed. You may review this decision before final validation.'), // @translate
            ];
        }, 'revision-rejected');
    }

    public function reviewRevisionAction()
    {
        return $this->handleProposalKeyAction(function ($contribution, $term, $key) {
            $this->revisionService->review($contribution, $term, $key);

            return [
                'status' => 'review-revision',
                'statusLabel' => $this->translate('Revision review'), // @translate
                'message' => $this->translate('You may accept or reject this metadata revision again.'), // @translate
            ];
        }, 'revision-review');
    }

    public function setRevisionLanguageAction()
    {
        return $this->handleProposalKeyAction(function ($contribution, $term, $key) {
            $language = $this->params()->fromQuery('language');
            $this->languageService->setProposedLanguage(
                $contribution,
                $term,
                $key,
                $language !== null ? (string) $language : null
            );

            $contribution = $this->api()->read('contributions', $contribution->id())->getContent();
            $entry = $contribution->proposal()[$term][$key]
                ?? $contribution->proposal()[$term][(string) $key]
                ?? [];
            $savedLanguage = is_array($entry)
                ? $this->languageService->proposedLanguage($entry)
                : null;
            $propertyLabel = $this->languageService->propertyLabel($term);

            if ($savedLanguage) {
                $message = sprintf(
                    $this->translate('Language tag for %1$s set to %2$s.'), // @translate
                    $propertyLabel,
                    $savedLanguage
                );
            } else {
                $message = sprintf(
                    $this->translate('Language tag removed for %s.'), // @translate
                    $propertyLabel
                );
            }

            return [
                'status' => 'revision-language',
                'statusLabel' => $this->translate('Language tag saved'), // @translate
                'message' => $message,
                'language' => $savedLanguage,
                'propertyLabel' => $propertyLabel,
                'term' => $term,
            ];
        });
    }

    public function languageWarningsAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend()->fail(null, $this->translate(
                'Method not allowed.' // @translate
            ), HttpResponse::STATUS_CODE_405);
        }

        $id = (int) $this->params('id');

        try {
            $contribution = $this->api()->read('contributions', $id)->getContent();
        } catch (\Throwable $e) {
            return $this->jSend()->fail(null, $this->translate(
                'Resource not found.' // @translate
            ), HttpResponse::STATUS_CODE_404);
        }

        return $this->jSend()->success([
            'warnings' => $this->languageService->missingLanguageWarnings($contribution),
        ]);
    }

    public function archiveAction()
    {
        return $this->handleArchiveAction(true);
    }

    public function setStatusAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend()->fail(null, $this->translate(
                'Method not allowed.' // @translate
            ), HttpResponse::STATUS_CODE_405);
        }

        $id = (int) $this->params('id');
        $status = (string) $this->params()->fromQuery('status', '');

        $statusMap = [
            'undetermined' => null,
            'validated' => true,
            'not-validated' => false,
        ];

        if (!array_key_exists($status, $statusMap)) {
            return $this->jSend()->fail(null, $this->translate(
                'Unknown status.' // @translate
            ));
        }

        try {
            $contribution = $this->api()->read('contributions', $id)->getContent();
        } catch (\Throwable $e) {
            return $this->jSend()->fail(null, $this->translate(
                'Resource not found.' // @translate
            ), HttpResponse::STATUS_CODE_404);
        }

        $resource = $contribution->resource();
        if ($resource && !$resource->userIsAllowed('update')) {
            return $this->jSend()->fail(null, $this->translate(
                'Unauthorized access.' // @translate
            ), HttpResponse::STATUS_CODE_401);
        }

        if ($this->archiveService->isArchived($id)) {
            return $this->jSend()->fail(null, $this->translate(
                'This contribution is archived and cannot be modified.' // @translate
            ), HttpResponse::STATUS_CODE_401);
        }

        $response = $this->api()->update('contributions', $id, [
            'o-module-contribute:validated' => $statusMap[$status],
        ], [], ['isPartial' => true]);

        if (!$response) {
            return $this->jSend()->error(null, $this->translate(
                'An internal error occurred.' // @translate
            ));
        }

        $contribution = $response->getContent();

        if ($statusMap[$status] === true) {
            $this->applicationService->applyAcceptedDecisions($contribution);
        } else {
            $this->applicationService->revertAppliedDecisions($contribution);
        }

        $contribution = $this->api()->read('contributions', $id)->getContent();
        $this->undertakingService->syncWithValidationStatus($contribution);
        $labels = [
            'undetermined' => $this->translate('Pending review'), // @translate
            'validated' => $this->translate('Validated'), // @translate
            'not-validated' => $this->translate('Rejected'), // @translate
        ];

        return $this->jSend()->success([
            'contribution' => [
                'status' => $status,
                'statusLabel' => $labels[$status],
            ],
        ]);
    }

    public function unarchiveAction()
    {
        return $this->handleArchiveAction(false);
    }

    public function emailTemplateAction()
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend()->fail(null, $this->translate(
                'Method not allowed.' // @translate
            ), HttpResponse::STATUS_CODE_405);
        }

        if (!$this->getRequest()->isGet() && !$this->getRequest()->isPost()) {
            return $this->jSend()->fail(null, $this->translate(
                'Method not allowed.' // @translate
            ), HttpResponse::STATUS_CODE_405);
        }

        $id = (int) $this->params('id');
        $type = (string) $this->params()->fromQuery('type', '');

        try {
            $contribution = $this->api()->read('contributions', $id)->getContent();
        } catch (\Throwable $e) {
            return $this->jSend()->fail(null, $this->translate(
                'Resource not found.' // @translate
            ), HttpResponse::STATUS_CODE_404);
        }

        try {
            $template = $this->emailTemplateService->render($contribution, $type);
        } catch (\InvalidArgumentException $e) {
            return $this->jSend()->fail(null, $this->translate(
                'Unknown template type.' // @translate
            ));
        }

        return $this->jSend()->success([
            'template' => $template,
        ]);
    }

    /**
     * @param callable(ContributionRepresentation, string, ?int, ?int): array<string, string> $handler
     */
    protected function handleValueAction(callable $handler, ?string $reloadStatus = null)
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend()->fail(null, $this->translate(
                'Method not allowed.' // @translate
            ), HttpResponse::STATUS_CODE_405);
        }

        $id = $this->params('id');

        try {
            $contribution = $this->api()->read('contributions', $id)->getContent();
        } catch (\Throwable $e) {
            return $this->jSend()->fail(null, $this->translate(
                'Resource not found.' // @translate
            ), HttpResponse::STATUS_CODE_404);
        }

        if ($this->archiveService->isArchived((int) $contribution->id())) {
            return $this->jSend()->fail(null, $this->translate(
                'This contribution is archived and cannot be modified.' // @translate
            ), HttpResponse::STATUS_CODE_401);
        }

        $reviewLockResponse = $this->reviewLockResponse($contribution);
        if ($reviewLockResponse) {
            return $reviewLockResponse;
        }

        $contributionResource = $contribution->resource();
        if (!$contributionResource) {
            return $this->jSend()->fail(null, $this->translate(
                'Resource not found.' // @translate
            ), HttpResponse::STATUS_CODE_404);
        }

        if (!$contributionResource->userIsAllowed('update')) {
            return $this->jSend()->fail(null, $this->translate(
                'Unauthorized access.' // @translate
            ), HttpResponse::STATUS_CODE_401);
        }

        $term = (string) $this->params()->fromQuery('term');
        $key = $this->params()->fromQuery('key');
        $valueIndex = $this->params()->fromQuery('value_index');

        if ($term === '') {
            return $this->jSend()->fail(null, $this->translate(
                'Missing term.' // @translate
            ));
        }

        $proposalKey = is_numeric($key) ? (int) $key : null;
        $resourceValueIndex = is_numeric($valueIndex) ? (int) $valueIndex : null;

        if ($proposalKey === null && $resourceValueIndex === null) {
            return $this->jSend()->fail(null, $this->translate(
                'Missing key or value index.' // @translate
            ));
        }

        try {
            $this->undertakingService->ensureUndertaken($contribution);
            $result = $handler($contribution, $term, $proposalKey, $resourceValueIndex);
        } catch (\InvalidArgumentException $e) {
            return $this->jSend()->fail(null, $e->getMessage() ?: $this->translate(
                'Contribution is not valid.' // @translate
            ));
        } catch (\Throwable $e) {
            return $this->jSend()->error(null, $this->translate(
                'An internal error occurred.' // @translate
            ));
        }

        if ($reloadStatus) {
            $result['reloadStatus'] = $reloadStatus;
        }

        return $this->jSend()->success([
            'contribution' => $result,
        ]);
    }

    /**
     * @param callable(ContributionRepresentation, string, int): array<string, string> $handler
     */
    protected function handleProposalKeyAction(callable $handler, ?string $reloadStatus = null)
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend()->fail(null, $this->translate(
                'Method not allowed.' // @translate
            ), HttpResponse::STATUS_CODE_405);
        }

        $id = $this->params('id');

        try {
            $contribution = $this->api()->read('contributions', $id)->getContent();
        } catch (\Throwable $e) {
            return $this->jSend()->fail(null, $this->translate(
                'Resource not found.' // @translate
            ), HttpResponse::STATUS_CODE_404);
        }

        if ($this->archiveService->isArchived((int) $contribution->id())) {
            return $this->jSend()->fail(null, $this->translate(
                'This contribution is archived and cannot be modified.' // @translate
            ), HttpResponse::STATUS_CODE_401);
        }

        $reviewLockResponse = $this->reviewLockResponse($contribution);
        if ($reviewLockResponse) {
            return $reviewLockResponse;
        }

        $contributionResource = $contribution->resource();
        if (!$contributionResource || !$contributionResource->userIsAllowed('update')) {
            return $this->jSend()->fail(null, $this->translate(
                'Unauthorized access.' // @translate
            ), HttpResponse::STATUS_CODE_401);
        }

        $term = (string) $this->params()->fromQuery('term');
        $key = $this->params()->fromQuery('key');
        if ($term === '' || !is_numeric($key)) {
            return $this->jSend()->fail(null, $this->translate(
                'Missing term or key.' // @translate
            ));
        }

        try {
            $this->undertakingService->ensureUndertaken($contribution);
            $result = $handler($contribution, $term, (int) $key);
        } catch (\InvalidArgumentException $e) {
            return $this->jSend()->fail(null, $e->getMessage() ?: $this->translate(
                'Contribution is not valid.' // @translate
            ));
        } catch (\Throwable $e) {
            return $this->jSend()->error(null, $this->translate(
                'An internal error occurred.' // @translate
            ));
        }

        if ($reloadStatus) {
            $result['reloadStatus'] = $reloadStatus;
        }

        return $this->jSend()->success([
            'contribution' => $result,
        ]);
    }

    protected function reviewLockResponse(ContributionRepresentation $contribution): ?HttpResponse
    {
        if ($contribution->isValidated() !== true) {
            return null;
        }

        return $this->jSend()->fail(null, $this->translate(
            'This contribution is validated. Change the validation status to Pending review to revise per-value decisions.' // @translate
        ), HttpResponse::STATUS_CODE_401);
    }

    protected function handleArchiveAction(bool $archive)
    {
        if (!$this->getRequest()->isXmlHttpRequest()) {
            return $this->jSend()->fail(null, $this->translate(
                'Method not allowed.' // @translate
            ), HttpResponse::STATUS_CODE_405);
        }

        $id = (int) $this->params('id');

        try {
            $contribution = $this->api()->read('contributions', $id)->getContent();
        } catch (\Throwable $e) {
            return $this->jSend()->fail(null, $this->translate(
                'Resource not found.' // @translate
            ), HttpResponse::STATUS_CODE_404);
        }

        $resource = $contribution->resource();
        if ($resource && !$resource->userIsAllowed('update')) {
            return $this->jSend()->fail(null, $this->translate(
                'Unauthorized access.' // @translate
            ), HttpResponse::STATUS_CODE_401);
        }

        if ($archive) {
            $user = $this->identity();
            $this->archiveService->archive($id, $user ? (int) $user->getId() : null);
            $message = $this->translate('Contribution archived.'); // @translate
        } else {
            $this->archiveService->unarchive($id);
            $message = $this->translate('Contribution unarchived.'); // @translate
        }

        return $this->jSend()->success([
            'contribution' => [
                'id' => $id,
                'archived' => $archive,
                'message' => $message,
            ],
        ]);
    }
}
