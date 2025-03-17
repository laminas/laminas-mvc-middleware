<?php

declare(strict_types=1);

namespace Laminas\Mvc\Middleware;

use Closure;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Webmozart\Assert\Assert;

use function array_values;

final class PipeSpec
{
    /**
     * @var list<string|RequestHandlerInterface|MiddlewareInterface|Closure>
     * @psalm-var list<mixed>
     */
    private $spec;

    /**
     * @param string|RequestHandlerInterface|MiddlewareInterface|Closure ...$spec
     * @psalm-param mixed                                                ...$spec
     * @no-named-arguments
     */
    public function __construct(...$spec)
    {
        $this->spec = $spec;
    }

    /**
     * @psalm-return list<mixed>
     * @return list<string|RequestHandlerInterface|MiddlewareInterface|Closure>
     */
    public function getSpec(): array
    {
        return $this->spec;
    }

    /**
     * Support serialization via var_export
     *
     * @internal
     *
     * @param array<string, mixed> $state
     */
    public static function __set_state(array $state): self
    {
        $spec = $state['spec'] ?? null;
        Assert::isArray($spec, 'Failed to restore state. Config cache is invalid');
        $spec = array_values($spec);
        return new self(...$spec);
    }
}
