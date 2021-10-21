<?php

declare(strict_types=1);

namespace LaminasTest\Mvc\Middleware;

use Laminas\Mvc\Middleware\Module;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Laminas\Mvc\Middleware\Module
 */
class ModuleTest extends TestCase
{
    /** @var Module */
    private $module;

    protected function setUp(): void
    {
        $this->module = new Module();
    }

    public function testGetConfigReturnsArray(): array
    {
        $config = $this->module->getConfig();
        /** @psalm-suppress RedundantCondition */
        self::assertIsArray($config);
        return $config;
    }

    /**
     * @depends testGetConfigReturnsArray
     */
    public function testReturnedArrayContainsDependencies(array $config): void
    {
        self::assertArrayHasKey('service_manager', $config);
        self::assertIsArray($config['service_manager']);
    }
}
