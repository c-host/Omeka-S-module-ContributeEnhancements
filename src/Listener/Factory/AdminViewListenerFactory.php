<?php declare(strict_types=1);

namespace ContributeEnhancements\Listener\Factory;

use ContributeEnhancements\Listener\AdminViewListener;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class AdminViewListenerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new AdminViewListener($services->get('ViewRenderer'));
    }
}
