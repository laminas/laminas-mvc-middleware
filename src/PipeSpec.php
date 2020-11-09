<?php

declare(strict_types=1);

namespace Laminas\Mvc\Middleware;

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
     * @param string|RequestHandlerInterface|MiddlewareInterface ...$spec
     */
    public function __construct(...$spec)
    {
        $this->spec = $spec;
    }

    public function getSpec(): array
    {
        return $this->spec;
    }

    /**
     * Support serialization via var_export
     */
    public static function __set_state(array $state): self
    {
        Assert::keyExists($state, 'spec', 'Failed to restore state. Config cache is invalid');
        $spec = $state['spec'];
        Assert::isArray($spec, 'Failed to restore state. Config cache is invalid');
        return new self(...$spec);
    }
}
