<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener;

use ContributeEnhancements\Service\ArchiveService;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;

class ArchiveSearchListener implements ListenerAggregateInterface
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
        $this->listeners[] = $shared->attach($adapter, 'api.search.query', [$this, 'onSearchQuery']);
    }

    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function onSearchQuery(Event $event): void
    {
        $request = $event->getParam('request');
        if (!$request || !method_exists($request, 'getContent')) {
            return;
        }

        $query = $request->getContent();
        $archived = $query['contribute_enhancements_archived'] ?? '0';
        unset($query['contribute_enhancements_archived']);
        $request->setContent($query);

        $adapter = $event->getTarget();
        $qb = $event->getParam('queryBuilder');
        $expr = $qb->expr();
        $archivedIds = $this->archiveService->archivedIds();

        if ((string) $archived === '1') {
            if (!$archivedIds) {
                $qb->andWhere($expr->eq('omeka_root.id', $adapter->createNamedParameter($qb, 0)));

                return;
            }

            $qb->andWhere($expr->in(
                'omeka_root.id',
                $adapter->createNamedParameter($qb, $archivedIds)
            ));

            return;
        }

        if ($archivedIds) {
            $qb->andWhere($expr->notIn(
                'omeka_root.id',
                $adapter->createNamedParameter($qb, $archivedIds)
            ));
        }
    }
}
