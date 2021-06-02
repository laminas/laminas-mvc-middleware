<?php

declare(strict_types=1);

namespace LaminasTest\Mvc\Middleware\Integration\TestAsset;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;

class NoopSendResponseListener extends AbstractListenerAggregate
{
    /**
     * @param int $priority
     */
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        // noop
    }
}
