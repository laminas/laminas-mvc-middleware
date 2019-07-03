<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Mvc\Middleware\Integration;

use PHPUnit\Framework\TestCase;
use Zend\Mvc\Middleware\MiddlewareListener;
use Zend\Mvc\MiddlewareListener as DeprecatedMiddlewareListener;

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
