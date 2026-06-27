<?php declare(strict_types=1);

namespace ContributeEnhancements;

use ContributeEnhancements\Listener\AdminViewListener;
use ContributeEnhancements\Listener\ArchivedContributionGuardListener;
use ContributeEnhancements\Listener\ArchiveSearchListener;
use ContributeEnhancements\Listener\ContributionValidatedListener;
use ContributeEnhancements\Listener\ContributorDeleteGuardListener;
use ContributeEnhancements\Listener\ContributorStatusListener;
use ContributeEnhancements\Listener\ProposalNormalizeListener;
use ContributeEnhancements\Listener\ProposalSnapshotListener;
use ContributeEnhancements\Listener\SiteViewListener;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    protected function registerModuleAutoloader(): void
    {
        if (class_exists(ProposalNormalizeListener::class, false)) {
            return;
        }
        $loader = new \Laminas\Loader\StandardAutoloader([
            'namespaces' => [
                'ContributeEnhancements' => __DIR__ . '/src',
            ],
        ]);
        $loader->register();
    }

    public function getConfig()
    {
        $this->registerModuleAutoloader();

        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $services): void
    {
        $this->installArchiveTable($services);
    }

    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $services): void
    {
        $this->installArchiveTable($services);
    }

    public function uninstall(ServiceLocatorInterface $services): void
    {
        $services->get('Omeka\Connection')->exec('DROP TABLE IF EXISTS contribute_enhancements_archive');
    }

    protected function installArchiveTable(ServiceLocatorInterface $services): void
    {
        $conn = $services->get('Omeka\Connection');
        $conn->exec('CREATE TABLE IF NOT EXISTS contribute_enhancements_archive (
            contribution_id INT NOT NULL,
            archived_at DATETIME NOT NULL,
            archived_by_id INT DEFAULT NULL,
            PRIMARY KEY (contribution_id),
            CONSTRAINT FK_contribute_enhancements_archive_contribution
                FOREIGN KEY (contribution_id) REFERENCES contribution (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $this->registerModuleAutoloader();

        $services = $event->getApplication()->getServiceManager();
        $services->get('ViewHelperManager')->setFactory(
            'contributionFields',
            \ContributeEnhancements\View\Helper\Factory\ContributionFieldsFactory::class
        );

        if (!class_exists(\Contribute\Api\Adapter\ContributionAdapter::class)) {
            return;
        }

        $this->attachEnhancementListeners($event->getApplication()->getEventManager());
    }

    protected function attachEnhancementListeners(EventManagerInterface $eventManager): void
    {
        $services = $this->getServiceLocator();

        $services->get(ContributorDeleteGuardListener::class)->attach($eventManager);
        $services->get(ContributorStatusListener::class)->attach($eventManager);
        $services->get(ProposalNormalizeListener::class)->attach($eventManager);
        $services->get(ProposalSnapshotListener::class)->attach($eventManager);
        $services->get(ContributionValidatedListener::class)->attach($eventManager);
        $services->get(ArchiveSearchListener::class)->attach($eventManager);
        $services->get(ArchivedContributionGuardListener::class)->attach($eventManager);
        $services->get(AdminViewListener::class)->attach($eventManager);
        $services->get(SiteViewListener::class)->attach($eventManager);
    }
}
