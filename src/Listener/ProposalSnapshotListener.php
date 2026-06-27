<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener;

use ContributeEnhancements\Service\ProposalBaselineService;
use ContributeEnhancements\Service\ProposalDiffService;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Omeka\Api\Manager as ApiManager;

class ProposalSnapshotListener implements ListenerAggregateInterface
{
    /** @var array<int, callable> */
    protected $listeners = [];

    protected ApiManager $api;

    protected ProposalBaselineService $baselineService;

    protected ProposalDiffService $diffService;

    public function __construct(
        ApiManager $api,
        ProposalBaselineService $baselineService,
        ProposalDiffService $diffService
    ) {
        $this->api = $api;
        $this->baselineService = $baselineService;
        $this->diffService = $diffService;
    }

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $adapter = \Contribute\Api\Adapter\ContributionAdapter::class;
        $shared = $events->getSharedManager();
        $this->listeners[] = $shared->attach($adapter, 'api.update.pre', [$this, 'onUpdatePre'], -20);
        $this->listeners[] = $shared->attach($adapter, 'api.create.pre', [$this, 'onCreatePre'], -20);
    }

    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function onCreatePre(Event $event): void
    {
        $this->maybeAttachBaseline($event, true);
    }

    public function onUpdatePre(Event $event): void
    {
        $this->maybeAttachBaseline($event, false);
    }

    protected function maybeAttachBaseline(Event $event, bool $isCreate): void
    {
        $request = $event->getParam('request');
        if (!$request || !method_exists($request, 'getContent')) {
            return;
        }

        $data = $request->getContent();
        $entity = $event->getParam('entity');

        if (empty($data['o-module-contribute:submitted'])) {
            return;
        }

        $proposal = $data['o-module-contribute:proposal'] ?? null;
        if ($proposal === null && $entity && method_exists($entity, 'getProposal')) {
            $proposal = $entity->getProposal();
        }

        if (!is_array($proposal) || $this->baselineService->hasBaseline($proposal)) {
            return;
        }

        $resource = $this->loadResource($data, $entity);
        if (!$resource) {
            return;
        }

        $template = $resource->resourceTemplate();
        $proposal = $this->baselineService->attachBaseline($proposal, $resource, $template);
        $data['o-module-contribute:proposal'] = $proposal;
        $request->setContent($data);
    }

    /**
     * @param array<string, mixed> $data
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
