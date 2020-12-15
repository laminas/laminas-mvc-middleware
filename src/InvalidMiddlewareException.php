<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Laminas\Mvc\Middleware;

use Laminas\Mvc\Exception\InvalidMiddlewareException as DeprecatedMiddlewareException;

use function sprintf;

final class InvalidMiddlewareException extends DeprecatedMiddlewareException
{
    /** @var string */
    private $middlewareName = '';

    /**
     * @param string $middlewareName
     */
    public static function fromMiddlewareName($middlewareName): self
    {
        $instance                 = new self(sprintf('Cannot dispatch middleware %s', $middlewareName));
        $instance->middlewareName = $middlewareName;
        return $instance;
    }

    public static function fromNull(): self
    {
        return new self('Middleware name cannot be null');
    }

    public function toMiddlewareName(): string
    {
        return $this->middlewareName ?? '';
    }
}
