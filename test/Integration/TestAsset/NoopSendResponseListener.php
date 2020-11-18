<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace LaminasTest\Mvc\Middleware\Integration\TestAsset;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;

class NoopSendResponseListener extends AbstractListenerAggregate
{
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        // noop
    }
}
