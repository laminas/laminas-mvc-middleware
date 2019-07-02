<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 *
 */

namespace ZendTest\Mvc\Middleware\Integration;

use Interop\Http\ServerMiddleware\MiddlewareInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use stdClass;
use Zend\Diactoros\Response;
use Zend\Mvc\Controller\MiddlewareController as DeprecatedMiddlewareController;
use Zend\Mvc\Middleware\MiddlewareController;
use Zend\Mvc\MvcEvent;
use Zend\Router\Http\Literal;
use ZendTest\Mvc\Middleware\TestAsset\Middleware;

/**
 * @group integration
 * @coversNothing
 */
class MiddlewareDispatchTest extends TestCase
{
    use ApplicationTrait;

    protected function setUp()
    {
        parent::setUp();
        $this->extraConfig = [
            'router' => [
                'routes' => [
                    'middleware' => [
                        'type' => Literal::class,
                        'options' => [
                            'route' => '/middleware',
                            'defaults' => [
                                'middleware' => 'MiddlewareMock'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $this->setUpApplication();
    }

    protected function tearDown()
    {
        parent::tearDown();
        $this->tearDownApplication();
    }

    public function testDispatchesMiddleware()
    {
        $services = $this->application->getServiceManager();

        $services->get('Request')->setUri('http://example.local/middleware');

        $middlewareMock = $this->prophesize(MiddlewareInterface::class);
        $middlewareMock->process(Argument::cetera())
            ->willReturn(new Response())
            ->shouldBeCalled();
        $services->setService('MiddlewareMock', $middlewareMock->reveal());

        $this->application->run();
    }

    public function testMiddlewareDispatchTriggersSharedEventOnMiddlewareController()
    {
        $sharedEm = $this->application->getEventManager()->getSharedManager();
        $services = $this->application->getServiceManager();
        $services->get('Request')->setUri('http://example.local/middleware');
        $services->setService('MiddlewareMock', new Middleware());

        $listener = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $listener->expects(self::atLeastOnce())->method('__invoke');
        /** @var callable $listener */
        $sharedEm->attach(MiddlewareController::class, MvcEvent::EVENT_DISPATCH, $listener);

        $this->application->run();
    }

    public function testMiddlewareDispatchTriggersSharedEventOnOldMiddlewareController()
    {
        $sharedEm = $this->application->getEventManager()->getSharedManager();
        $services = $this->application->getServiceManager();
        $services->get('Request')->setUri('http://example.local/middleware');
        $services->setService('MiddlewareMock', new Middleware());

        $listener = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__invoke'])
            ->getMock();
        $listener->expects(self::atLeastOnce())->method('__invoke');
        /** @var callable $listener */
        $sharedEm->attach(DeprecatedMiddlewareController::class, MvcEvent::EVENT_DISPATCH, $listener);

        $this->application->run();
    }
}
