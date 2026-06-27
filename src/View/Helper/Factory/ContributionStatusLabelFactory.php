<?php declare(strict_types=1);

namespace ContributeEnhancements\View\Helper\Factory;

use ContributeEnhancements\View\Helper\ContributionStatusLabel;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ContributionStatusLabelFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new ContributionStatusLabel();
    }
}
