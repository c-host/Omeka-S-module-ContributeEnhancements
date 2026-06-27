<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener;

use Contribute\Api\Representation\ContributionRepresentation;
use ContributeEnhancements\Service\ContributionDeletePolicy;
use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Model\JsonModel;
use Omeka\Api\Manager as ApiManager;
use Omeka\Stdlib\ErrorStore;
use Laminas\Authentication\AuthenticationService;

class ContributorDeleteGuardListener implements ListenerAggregateInterface
{
    /** @var array<int, callable> */
    protected $listeners = [];

    protected ContributionDeletePolicy $deletePolicy;

    protected ApiManager $api;

    protected AuthenticationService $auth;

    public function __construct(
        ContributionDeletePolicy $deletePolicy,
        ApiManager $api,
        AuthenticationService $auth
    ) {
        $this->deletePolicy = $deletePolicy;
        $this->api = $api;
        $this->auth = $auth;
    }

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $shared = $events->getSharedManager();
        $adapter = \Contribute\Api\Adapter\ContributionAdapter::class;
        $this->listeners[] = $shared->attach($adapter, 'api.delete.pre', [$this, 'onDeletePre'], -5);

        foreach (['Contribute\Controller\Site\Contribution', 'Contribute\Controller\Site\Guest'] as $controller) {
            $this->listeners[] = $shared->attach($controller, 'dispatch', [$this, 'onSiteDispatch'], 100);
            $this->listeners[] = $shared->attach($controller, MvcEvent::EVENT_FINISH, [$this, 'onSiteDeleteFinish'], -100);
        }
    }

    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function onDeletePre(Event $event): void
    {
        if (!$this->isContributorContext()) {
            return;
        }

        $entity = $event->getParam('entity');
        if (!$entity || !method_exists($entity, 'getId')) {
            return;
        }

        $representation = $this->loadRepresentation((int) $entity->getId());
        if (!$representation || $this->deletePolicy->canContributorDelete($representation)) {
            return;
        }

        $errorStore = $event->getParam('errorStore') ?: new ErrorStore();
        $errorStore->addError('contribution', 'This contribution cannot be deleted.');
        $event->setParam('errorStore', $errorStore);
        $event->stopPropagation(true);
    }

    public function onSiteDispatch(MvcEvent $event): void
    {
        $routeMatch = $event->getRouteMatch();
        if (!$routeMatch || $routeMatch->getParam('action') !== 'delete') {
            return;
        }

        $controller = $event->getTarget();
        if (!is_object($controller) || !method_exists($controller, 'getEvent')) {
            return;
        }

        if (!$controller->getRequest()->isPost()) {
            return;
        }

        $id = (int) $routeMatch->getParam('id');
        if (!$id) {
            return;
        }

        try {
            $representation = $this->loadRepresentation($id);
        } catch (\Throwable $e) {
            return;
        }

        if (!$representation || $this->deletePolicy->canContributorDelete($representation)) {
            return;
        }

        $controller->plugins()->get('messenger')->addWarning('This contribution cannot be deleted.');
        $space = $routeMatch->getParam('space', 'default');
        $event->setResult($controller->redirect()->toRoute(
            $space === 'guest' ? 'site/guest/contribution' : 'site/contribution',
            ['action' => 'browse'],
            true
        ));
        $event->stopPropagation(true);
    }

    public function onSiteDeleteFinish(MvcEvent $event): void
    {
        $routeMatch = $event->getRouteMatch();
        if (!$routeMatch || $routeMatch->getParam('action') !== 'delete') {
            return;
        }

        $request = $event->getRequest();
        if (!$request->isPost()) {
            return;
        }

        $result = $event->getResult();
        if ($result instanceof JsonModel) {
            return;
        }

        $controller = $event->getTarget();
        if (!is_object($controller) || !method_exists($controller, 'plugin')) {
            return;
        }

        $successMessages = $controller->plugin('messenger')->getMessages('success');
        $errorMessages = $controller->plugin('messenger')->getMessages('error');
        $status = count($successMessages) && !count($errorMessages) ? 'success' : 'error';

        $event->setResult(new JsonModel([
            'status' => $status,
            'messages' => [
                'success' => $successMessages,
                'error' => $errorMessages,
            ],
        ]));
    }

    protected function isContributorContext(): bool
    {
        if (!$this->auth->hasIdentity()) {
            return false;
        }

        $identity = $this->auth->getIdentity();
        if (!method_exists($identity, 'getRole')) {
            return true;
        }

        return $identity->getRole() !== 'admin';
    }

    protected function loadRepresentation(int $id): ?ContributionRepresentation
    {
        try {
            return $this->api->read('contributions', $id)->getContent();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
