<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener;

use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;

/**
 * Contributor saves include validated=false; reserve false for reviewer rejection.
 */
class ContributorStatusListener implements ListenerAggregateInterface
{
    /** @var array<int, callable> */
    protected $listeners = [];

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
        if (!array_key_exists('o-module-contribute:proposal', $data)) {
            return;
        }

        if (!array_key_exists('o-module-contribute:validated', $data)) {
            return;
        }

        if ($data['o-module-contribute:validated'] !== false) {
            return;
        }

        $data['o-module-contribute:validated'] = null;
        $request->setContent($data);
    }
}
