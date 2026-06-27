<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener;

use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\View\Renderer\PhpRenderer;

class AdminViewListener implements ListenerAggregateInterface
{
    /** @var array<int, callable> */
    protected $listeners = [];

    protected PhpRenderer $view;

    public function __construct(PhpRenderer $view)
    {
        $this->view = $view;
    }

    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $shared = $events->getSharedManager();
        $controllers = [
            'Contribute\Controller\Admin\Contribution',
            \Omeka\Controller\Admin\Item::class,
            \Omeka\Controller\Admin\ItemSet::class,
            \Omeka\Controller\Admin\Media::class,
        ];

        foreach ($controllers as $controller) {
            $this->listeners[] = $shared->attach($controller, 'view.browse.before', [$this, 'injectAdminAssets']);
            $this->listeners[] = $shared->attach($controller, 'view.show.after', [$this, 'injectAdminAssets']);
            $this->listeners[] = $shared->attach($controller, 'view.browse.before', [$this, 'injectWorkflowNotice']);
            $this->listeners[] = $shared->attach($controller, 'view.show.after', [$this, 'injectWorkflowNotice']);
        }

        $this->listeners[] = $shared->attach(
            'Contribute\Controller\Admin\Contribution',
            'view.browse.before',
            [$this, 'injectArchiveFilter']
        );
    }

    public function detach(EventManagerInterface $events): void
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    public function injectAdminAssets(Event $event): void
    {
        $assetUrl = $this->view->plugin('assetUrl');
        $this->view->headLink()->appendStylesheet(
            $assetUrl('css/contribute-enhancements-admin.css', 'ContributeEnhancements')
        );
        $this->view->headScript()->appendFile(
            $assetUrl('js/contribute-enhancements-admin.js', 'ContributeEnhancements')
        );
    }

    public function injectWorkflowNotice(Event $event): void
    {
        $controller = $event->getTarget();
        if (!is_object($controller) || !method_exists($controller, 'getEvent')) {
            return;
        }

        $routeMatch = $controller->getEvent()->getRouteMatch();
        if (!$routeMatch) {
            return;
        }

        $controllerName = $routeMatch->getParam('controller', '');
        $action = $routeMatch->getParam('action', '');

        $isContributionsBrowse = str_contains((string) $controllerName, 'Contribution')
            && $action === 'browse';

        if (!$isContributionsBrowse) {
            return;
        }

        echo $this->view->partial('contribute-enhancements/admin/workflow-notice');
    }

    public function injectArchiveFilter(Event $event): void
    {
        echo $this->view->partial('contribute-enhancements/admin/archive-filter');
    }
}
