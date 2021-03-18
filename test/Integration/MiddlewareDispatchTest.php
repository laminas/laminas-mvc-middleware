<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace LaminasTest\Mvc\Middleware\Integration;

use Closure;
use Laminas\Diactoros\Response;
use Laminas\Http\Request;
use Laminas\Mvc\Controller\MiddlewareController as DeprecatedMiddlewareController;
use Laminas\Mvc\Middleware\MiddlewareController;
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\Http\Literal;
use LaminasTest\Mvc\Middleware\TestAsset\Middleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Server\MiddlewareInterface;
use stdClass;

use function assert;

/**
 * @group integration
 * @coversNothing
 */
class MiddlewareDispatchTest extends TestCase
{
    use ApplicationTrait;
    use ProphecyTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpApplication([
            'router' => [
                'routes' => [
                    'middleware' => [
                        'type'    => Literal::class,
                        'options' => [
                            'route'    => '/middleware',
                            'defaults' => [
                                'controller' => PipeSpec::class,
                                'middleware' => 'MiddlewareMock',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownApplication();
        parent::tearDown();
    }

    public function testDispatchesMiddleware(): void
    {
        $services = $this->application->getServiceManager();
        /** @var Request $request */
        $request = $services->get('Request');
        $request->setUri('http://example.local/middleware');

        $middlewareMock = $this->prophesize(MiddlewareInterface::class);
        $middlewareMock->process(Argument::cetera())
            ->willReturn(new Response())
            ->shouldBeCalled();
        $services->setService('MiddlewareMock', $middlewareMock->reveal());

        $this->application->run();
    }

    public function testMiddlewareDispatchTriggersSharedEventOnMiddlewareController(): void
    {
        $sharedEm = $this->application->getEventManager()->getSharedManager();
        assert($sharedEm !== null);
        $services = $this->application->getServiceManager();
        /** @var Request $request */
        $request = $services->get('Request');
        $request->setUri('http://example.local/middleware');
        $services->setService('MiddlewareMock', new Middleware());

        /** @var Closure&MockObject $listener */
        $listener = $this->getMockBuilder(stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();
        $listener->expects(self::atLeastOnce())->method('__invoke');
        $sharedEm->attach(MiddlewareController::class, MvcEvent::EVENT_DISPATCH, $listener);

        $this->application->run();
    }

    public function testMiddlewareDispatchTriggersSharedEventOnOldMiddlewareController(): void
    {
        $sharedEm = $this->application->getEventManager()->getSharedManager();
        assert($sharedEm !== null);
        $services = $this->application->getServiceManager();
        /** @var Request $request */
        $request = $services->get('Request');
        $request->setUri('http://example.local/middleware');
        $services->setService('MiddlewareMock', new Middleware());

        /** @var Closure&MockObject $listener */
        $listener = $this->getMockBuilder(stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();
        $listener->expects(self::atLeastOnce())->method('__invoke');
        $sharedEm->attach(DeprecatedMiddlewareController::class, MvcEvent::EVENT_DISPATCH, $listener);

        $this->application->run();
    }
}
