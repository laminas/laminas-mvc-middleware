<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

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

    protected function setUp()
    {
        parent::setUp();
        $this->setUpApplication();
    }

    protected function tearDown()
    {
        $this->tearDownApplication();
        parent::tearDown();
    }

    public function testModuleReplacesDefaultMiddlewareListener()
    {
        $container = $this->application->getServiceManager();
        $middlewareListener = $container->get(DeprecatedMiddlewareListener::class);

        $this->assertInstanceOf(MiddlewareListener::class, $middlewareListener);
        $this->assertSame($middlewareListener, $container->get('MiddlewareListener'));
    }
}
