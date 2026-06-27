<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener;

use ContributeEnhancements\Service\ProposalApplicationService;
use ContributeEnhancements\Service\ResourceValueRemovalApplier;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;

class ContributionValidatedListener implements ListenerAggregateInterface
{
    /** @var array<int, callable> */
    protected $listeners = [];

    protected ResourceValueRemovalApplier $removalApplier;

    protected ProposalApplicationService $applicationService;

    public function __construct(
        ResourceValueRemovalApplier $removalApplier,
        ProposalApplicationService $applicationService
    ) {
        $this->removalApplier = $removalApplier;
        $this->applicationService = $applicationService;
    }

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $adapter = \Contribute\Api\Adapter\ContributionAdapter::class;
        $shared = $events->getSharedManager();
        $this->listeners[] = $shared->attach($adapter, 'api.update.post', [$this, 'onUpdatePost'], -100);
    }

    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function onUpdatePost(Event $event): void
    {
        $request = $event->getParam('request');
        $response = $event->getParam('response');
        if (!$request || !$response || !method_exists($response, 'getContent')) {
            return;
        }

        if (!array_key_exists('o-module-contribute:validated', $request->getContent())
            && !array_key_exists('validated', $request->getContent())
        ) {
            return;
        }

        $contribution = $response->getContent();
        if (!$contribution || !method_exists($contribution, 'isValidated')) {
            return;
        }

        $newValidated = $contribution->isValidated();

        if ($newValidated === true) {
            $this->applicationService->applyAcceptedDecisions($contribution);

            return;
        }

        if ($newValidated !== true) {
            $this->applicationService->revertAppliedDecisions($contribution);
        }
    }
}
