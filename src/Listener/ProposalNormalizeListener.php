<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener;

use ContributeEnhancements\Service\ProposalDedupeService;
use ContributeEnhancements\Service\ProposalDiffService;
use ContributeEnhancements\Service\ProposalNormalizer;
use ContributeEnhancements\Service\ProposalResubmitService;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Omeka\Api\Manager as ApiManager;

class ProposalNormalizeListener implements ListenerAggregateInterface
{
    /** @var array<int, callable> */
    protected $listeners = [];

    protected ApiManager $api;

    protected ProposalDiffService $diffService;

    protected ProposalNormalizer $normalizer;

    protected ProposalResubmitService $resubmitService;

    protected ProposalDedupeService $dedupeService;

    public function __construct(
        ApiManager $api,
        ProposalDiffService $diffService,
        ProposalNormalizer $normalizer,
        ProposalResubmitService $resubmitService,
        ProposalDedupeService $dedupeService
    ) {
        $this->api = $api;
        $this->diffService = $diffService;
        $this->normalizer = $normalizer;
        $this->resubmitService = $resubmitService;
        $this->dedupeService = $dedupeService;
    }

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $adapter = \Contribute\Api\Adapter\ContributionAdapter::class;
        $shared = $events->getSharedManager();
        $this->listeners[] = $shared->attach($adapter, 'api.create.pre', [$this, 'onSavePre']);
        $this->listeners[] = $shared->attach($adapter, 'api.update.pre', [$this, 'onSavePre']);
    }

    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function onSavePre(Event $event): void
    {
        $request = $event->getParam('request');
        if (!$request || !method_exists($request, 'getContent')) {
            return;
        }

        $data = $request->getContent();
        if (empty($data['o-module-contribute:proposal'])) {
            return;
        }

        $entity = $event->getParam('entity');
        $isPatch = !empty($data['o-module-contribute:patch'])
            || ($entity && method_exists($entity, 'isPatch') && $entity->isPatch());
        if (!$isPatch) {
            return;
        }

        $resource = $this->loadResource($data, $entity);
        if (!$resource) {
            return;
        }

        $template = $resource->resourceTemplate();
        $editableTerms = $this->diffService->editableTermsFromTemplate($template);
        if (!$editableTerms) {
            return;
        }

        $proposal = $data['o-module-contribute:proposal'];
        $proposal = $this->resubmitService->stripEnhancementMarkers($proposal);
        $proposal = $this->normalizer->normalize($proposal, $resource, $editableTerms);
        $proposal = $this->dedupeService->deduplicate($proposal);
        $data['o-module-contribute:proposal'] = $proposal;
        $request->setContent($data);
    }

    /**
     * @param array<string, mixed> $data
     * @param object|null $entity
     */
    protected function loadResource(array $data, ?object $entity = null): ?\Omeka\Api\Representation\AbstractResourceEntityRepresentation
    {
        $resourceId = $data['o:resource']['o:id'] ?? null;
        if (!$resourceId && $entity && method_exists($entity, 'getResource')) {
            $resourceEntity = $entity->getResource();
            if ($resourceEntity) {
                $resourceId = $resourceEntity->getId();
            }
        }

        if (!$resourceId) {
            return null;
        }

        try {
            return $this->api->read('resources', ['id' => (int) $resourceId])->getContent();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
