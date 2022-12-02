<?php

declare(strict_types=1);

namespace Laminas\Mvc\Middleware;

use Closure;
use Laminas\Mvc\Exception\InvalidMiddlewareException as DeprecatedMiddlewareException;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function gettype;
use function is_object;
use function sprintf;

/**
 * @psalm-suppress DeprecatedClass
 */
final class InvalidMiddlewareException extends DeprecatedMiddlewareException
{
    /** @var string */
    private $middlewareName = '';

    /**
     * @param string      $middlewareName
     * @psalm-param mixed $middlewareName
     */
    public static function fromMiddlewareName($middlewareName): self
    {
        $middlewareName = (string) $middlewareName;
        $instance       = new self(sprintf('Cannot dispatch middleware %s', $middlewareName));

        $instance->middlewareName = $middlewareName;
        return $instance;
    }

    /**
     * @param mixed $invalidMiddleware
     */
    public static function fromInvalidType($invalidMiddleware, ?string $name = ''): self
    {
        $actual   = is_object($invalidMiddleware) ? $invalidMiddleware::class : gettype($invalidMiddleware);
        $instance = new self(sprintf(
            'Middleware in pipe spec can be one of: string container id, %s %s, or %s; %s given',
            MiddlewareInterface::class,
            RequestHandlerInterface::class,
            Closure::class,
            $actual
        ));

        $instance->middlewareName = (string) $name;
        return $instance;
    }

    public static function fromMissingInContainer(string $id): self
    {
        $instance = new self(sprintf('Middleware with id %s could not be found in container', $id));

        $instance->middlewareName = $id;
        return $instance;
    }

    public function toMiddlewareName(): string
    {
        return $this->middlewareName;
    }
}
