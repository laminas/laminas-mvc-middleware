<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Laminas\Mvc\Middleware;

use Laminas\Mvc\MiddlewareListener as DeprecatedMiddlewareListener;

class Module
{
    public function getConfig(): array
    {
        return [
            'service_manager' => [
                'aliases'   => [
                    DeprecatedMiddlewareListener::class => MiddlewareListener::class,

                    // Legacy Zend Framework aliases
                    \Zend\Mvc\MiddlewareListener::class            => DeprecatedMiddlewareListener::class,
                    \Zend\Mvc\Middleware\MiddlewareListener::class => MiddlewareListener::class,
                ],
                'factories' => [
                    MiddlewareListener::class => MiddlewareListenerFactory::class,
                ],
            ],
        ];
    }
}
