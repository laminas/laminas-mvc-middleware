<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace Laminas\Mvc\Middleware;

use Closure;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webmozart\Assert\Assert;

final class PipeSpec
{
    /**
     * @var array
     */
    private $spec;

    /**
     * @param string|RequestHandlerInterface|MiddlewareInterface|Closure ...$spec
     */
    public function __construct(...$spec)
    {
        $this->spec = $spec;
    }

    /**
     * @psalm-return list<string|RequestHandlerInterface|MiddlewareInterface|Closure>
     */
    public function getSpec(): array
    {
        return $this->spec;
    }

    /**
     * Support serialization via var_export
     *
     * @internal
     */
    public static function __set_state(array $state): self
    {
        $spec = $state['spec'] ?? null;
        Assert::isArray($spec, 'Failed to restore state. Config cache is invalid');
        return new self(...$spec);
    }
}
