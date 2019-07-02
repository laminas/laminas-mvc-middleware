<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 *
 */

namespace ZendTest\Mvc\Middleware\Integration;

use PHPUnit\Framework\TestCase;

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
        parent::tearDown();
        $this->tearDownApplication();
    }

    public function testModuleReplacesDefaultMiddlewareListener()
    {
        $this->application->bootstrap();
        $container = $this->application->getServiceManager();
        $middlewareListener = $container->get('Zend\Mvc\MiddlewareListener');
        $this->assertSame($middlewareListener, $container->get('MiddlewareListener'));

    }

}
