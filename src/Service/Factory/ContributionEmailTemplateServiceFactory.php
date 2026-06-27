<?php declare(strict_types=1);

namespace ContributeEnhancements\Service\Factory;

use ContributeEnhancements\Service\ContributionEmailTemplateService;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributionEmailTemplateServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $config = $services->get('Config');
        $defaults = $config['contribute_enhancements']['email_templates'] ?? [];

        return new ContributionEmailTemplateService(
            $services->get('Omeka\Settings'),
            $services->get('Omeka\Connection'),
            $defaults
        );
    }
}
