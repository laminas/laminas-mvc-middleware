<?php

declare(strict_types=1);

namespace Laminas\Mvc\Middleware;

use Closure;
use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function gettype;
use function is_object;
use function is_string;
use function sprintf;

/**
 * @internal
 */
class HandlerFromPipeSpecFactory
{
    /**
     * @param string|MiddlewareInterface|RequestHandlerInterface|Closure|PipeSpec $middleware
     * @psalm-param mixed                                                         $middleware
     */
    public function createFromMiddlewareParam(ContainerInterface $container, $middleware): RequestHandlerInterface
    {
        if (is_string($middleware)) {
            $middleware = $this->middlewareFromContainer($container, $middleware);
        }
        if ($middleware instanceof RequestHandlerInterface) {
            return $middleware;
        }
        if ($middleware instanceof Closure) {
            $middleware = new CallableMiddlewareDecorator($middleware);
        }
        if ($middleware instanceof MiddlewareInterface) {
            $middleware = new PipeSpec($middleware);
        }
        if ($middleware instanceof PipeSpec) {
            return $this->createPipeFromSpec($container, $middleware);
        }

        throw new InvalidMiddlewareException(sprintf(
            'Middleware can be one of: string container id, %s, %s, %s or %s; %s given',
            MiddlewareInterface::class,
            RequestHandlerInterface::class,
            Closure::class,
            PipeSpec::class,
            is_object($middleware) ? $middleware::class : gettype($middleware)
        ));
    }

    public function createPipeFromSpec(
        ContainerInterface $container,
        PipeSpec $middlewarePipeSpec
    ): RequestHandlerInterface {
        // Pipe has implicit empty pipeline handler
        $pipe = new MiddlewarePipe();
        /** @var mixed $middlewareToBePiped */
        foreach ($middlewarePipeSpec->getSpec() as $middlewareToBePiped) {
            $pipe->pipe($this->resolveMiddlewareFromSpec($container, $middlewareToBePiped));
        }
        return $pipe;
    }

    /**
     * @param string|MiddlewareInterface|RequestHandlerInterface|Closure $middleware
     * @psalm-param mixed                                                $middleware
     */
    private function resolveMiddlewareFromSpec(ContainerInterface $container, $middleware): MiddlewareInterface
    {
        if ($middleware instanceof Closure) {
            return new CallableMiddlewareDecorator($middleware);
        }
        if (is_string($middleware)) {
            $middleware = $this->middlewareFromContainer($container, $middleware);
        }
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }
        if ($middleware instanceof RequestHandlerInterface) {
            return new RequestHandlerMiddleware($middleware);
        }
        throw InvalidMiddlewareException::fromInvalidType($middleware);
    }

    /**
     * @return MiddlewareInterface|RequestHandlerInterface
     */
    private function middlewareFromContainer(ContainerInterface $container, string $id)
    {
        if (! $container->has($id)) {
            throw InvalidMiddlewareException::fromMissingInContainer($id);
        }
        $middleware = $container->get($id);
        if (! $middleware instanceof MiddlewareInterface && ! $middleware instanceof RequestHandlerInterface) {
            throw InvalidMiddlewareException::fromInvalidType($middleware, $id);
        }
        return $middleware;
    }
}
