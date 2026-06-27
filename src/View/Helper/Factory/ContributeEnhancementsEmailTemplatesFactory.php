<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper\Factory;

use ContributeEnhancements\Service\ContributionEmailTemplateService;
use ContributeEnhancements\View\Helper\ContributeEnhancementsEmailTemplates;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributeEnhancementsEmailTemplatesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ContributeEnhancementsEmailTemplates(
            $services->get(ContributionEmailTemplateService::class)
        );
    }
}
