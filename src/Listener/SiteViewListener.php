<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener;

use Laminas\EventManager\Event;
use Laminas\EventManager\EventManagerInterface;
use Laminas\EventManager\ListenerAggregateInterface;
use Laminas\View\Renderer\PhpRenderer;

class SiteViewListener implements ListenerAggregateInterface
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
        $siteControllers = [
            'Contribute\Controller\Site\Contribution',
            'Contribute\Controller\Site\Guest',
        ];

        foreach ($siteControllers as $controller) {
            $this->listeners[] = $shared->attach($controller, 'view.show.before', [$this, 'injectSiteAssets']);
            $this->listeners[] = $shared->attach($controller, 'view.browse.before', [$this, 'injectSiteAssets']);
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

    public function injectSiteAssets(Event $event): void
    {
        $assetUrl = $this->view->plugin('assetUrl');
        $this->view->headLink()->appendStylesheet(
            $assetUrl('css/contribute-enhancements-site.css', 'ContributeEnhancements')
        );
    }
}
