<?php

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
                    'Zend\Mvc\MiddlewareListener'            => DeprecatedMiddlewareListener::class,
                    'Zend\Mvc\Middleware\MiddlewareListener' => MiddlewareListener::class,
                ],
                'factories' => [
                    MiddlewareListener::class => MiddlewareListenerFactory::class,
                ],
            ],
        ];
    }
}
