<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper\Factory;

use ContributeEnhancements\Service\ArchiveService;
use ContributeEnhancements\View\Helper\ContributionArchive;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributionArchiveFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ContributionArchive($services->get(ArchiveService::class));
    }
}
