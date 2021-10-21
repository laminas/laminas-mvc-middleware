<?php

declare(strict_types=1);

namespace LaminasTest\Mvc\Middleware\Integration;

use Laminas\Mvc\Middleware\MiddlewareListener;
use Laminas\Mvc\MiddlewareListener as DeprecatedMiddlewareListener;
use PHPUnit\Framework\TestCase;

/**
 * @group integration
 * @coversNothing
 */
class ApplicationBootstrapTest extends TestCase
{
    use ApplicationTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApplication();
    }

    protected function tearDown(): void
    {
        $this->tearDownApplication();
        parent::tearDown();
    }

    public function testModuleReplacesDefaultMiddlewareListener(): void
    {
        $container          = $this->application->getServiceManager();
        $middlewareListener = $container->get(DeprecatedMiddlewareListener::class);

        self::assertInstanceOf(MiddlewareListener::class, $middlewareListener);
        self::assertSame($middlewareListener, $container->get('MiddlewareListener'));
    }
}
