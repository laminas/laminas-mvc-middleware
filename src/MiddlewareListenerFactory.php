<?php

declare(strict_types=1);

namespace Laminas\Mvc\Middleware;

class MiddlewareListenerFactory
{
    public function __invoke(): MiddlewareListener
    {
        return new MiddlewareListener(new HandlerFromPipeSpecFactory());
    }
}
