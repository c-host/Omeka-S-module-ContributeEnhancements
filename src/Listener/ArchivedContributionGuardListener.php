<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener;

use ContributeEnhancements\Service\ArchiveService;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Omeka\Stdlib\ErrorStore;

class ArchivedContributionGuardListener implements ListenerAggregateInterface
{
    /** @var array<int, callable> */
    protected $listeners = [];

    protected ArchiveService $archiveService;

    public function __construct(ArchiveService $archiveService)
    {
        $this->archiveService = $archiveService;
    }

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $adapter = \Contribute\Api\Adapter\ContributionAdapter::class;
        $shared = $events->getSharedManager();
        $this->listeners[] = $shared->attach($adapter, 'api.update.pre', [$this, 'onUpdatePre'], -10);
        $this->listeners[] = $shared->attach($adapter, 'api.delete.pre', [$this, 'onDeletePre'], -10);
    }

    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function onUpdatePre(Event $event): void
    {
        $request = $event->getParam('request');
        if ($request && method_exists($request, 'getOption')) {
            if ($request->getOption('contribute_enhancements_allow_archived_update')) {
                return;
            }
        }

        $entity = $event->getParam('entity');
        if (!$entity || !method_exists($entity, 'getId')) {
            return;
        }

        if (!$this->archiveService->isArchived((int) $entity->getId())) {
            return;
        }

        $errorStore = $event->getParam('errorStore') ?: new ErrorStore();
        $errorStore->addError('contribution', 'This contribution is archived and cannot be modified.');
        $event->setParam('errorStore', $errorStore);
        $event->stopPropagation(true);
    }

    public function onDeletePre(Event $event): void
    {
        $this->onUpdatePre($event);
    }
}
