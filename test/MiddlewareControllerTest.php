<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace LaminasTest\Mvc\Middleware;

use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\Controller\MiddlewareController as DeprecatedMiddlewareController;
use Laminas\Mvc\Exception\RuntimeException;
use Laminas\Mvc\Middleware\MiddlewareController;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use Laminas\Stdlib\DispatchableInterface;
use Laminas\Stdlib\RequestInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;

/**
 * @covers \Laminas\Mvc\Middleware\MiddlewareController
 */
class MiddlewareControllerTest extends TestCase
{
    /**
     * @var MiddlewareController
     */
    private $controller;
    /**
     * @var MvcEvent
     */
    private $event;
    /**
     * @var EventManagerInterface
     */
    private $eventManager;
    /**
     * @var MockObject&RequestHandlerInterface
     */
    private $requestHandler;

    /**
     * {@inheritDoc}
     */
    protected function setUp() : void
    {
        $this->requestHandler = $this->createMock(RequestHandlerInterface::class);
        $this->event = new MvcEvent();
        $this->eventManager = new EventManager();

        $this->controller = new MiddlewareController(
            $this->requestHandler,
            $this->eventManager,
            $this->event
        );
    }

    public function testEMHasDeprecatedControllerIdentifier()
    {
        $identifiers = $this->controller->getEventManager()->getIdentifiers();
        $this->assertContains(DeprecatedMiddlewareController::class, $identifiers);
    }

    public function testWillAssignCorrectEventManagerIdentifiers()
    {
        $identifiers = $this->controller->getEventManager()->getIdentifiers();

        self::assertContains(MiddlewareController::class, $identifiers);
        self::assertContains(AbstractController::class, $identifiers);
        self::assertContains(DispatchableInterface::class, $identifiers);
    }

    public function testWillDispatchARequestAndSetResponseFromAGivenRequestHandler()
    {
        $request          = new Request();
        $result           = $this->createMock(ResponseInterface::class);
        /* @var callable&MockObject $dispatchListener */
        $dispatchListener = $this->getMockBuilder(stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();

        $this->eventManager->attach(MvcEvent::EVENT_DISPATCH, $dispatchListener, 100);
        $this->eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, function () {
            self::fail('No dispatch error expected');
        }, 100);

        $dispatchListener
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::callback(function (MvcEvent $event) use ($request) {
                self::assertSame($this->event, $event);
                self::assertSame(MvcEvent::EVENT_DISPATCH, $event->getName());
                self::assertSame($this->controller, $event->getTarget());
                self::assertSame($request, $event->getRequest());

                return true;
            }));

        $this->requestHandler
            ->expects(self::once())
            ->method('handle')
            ->willReturn($result);

        $controllerResult = $this->controller->dispatch($request);

        self::assertSame($result, $controllerResult);
        self::assertSame($result, $this->event->getResult());
    }

    public function testWillRefuseDispatchingInvalidRequestTypes()
    {
        /* @var RequestInterface $request */
        $request          = $this->createMock(RequestInterface::class);
        /* @var callable|MockObject $dispatchListener */
        $dispatchListener = $this->getMockBuilder(stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();

        $this->eventManager->attach(MvcEvent::EVENT_DISPATCH, $dispatchListener, 100);

        $dispatchListener
            ->expects(self::once())
            ->method('__invoke')
            ->with(self::callback(function (MvcEvent $event) use ($request) {
                self::assertSame($this->event, $event);
                self::assertSame(MvcEvent::EVENT_DISPATCH, $event->getName());
                self::assertSame($this->controller, $event->getTarget());
                self::assertSame($request, $event->getRequest());

                return true;
            }));

        $this->requestHandler->expects(self::never())->method('handle');

        $this->expectException(RuntimeException::class);

        $this->controller->dispatch($request);
    }

    public function testWillSetRouteMatchAsARequestAttribute()
    {
        $request = new Request();
        $routeMatch = new RouteMatch(['middleware' => 'foo']);
        $this->event->setRouteMatch($routeMatch);

        $this->requestHandler
            ->expects(self::once())
            ->method('handle')
            ->with(self::callback(function (ServerRequestInterface $request) {
                $routeMatch = $request->getAttribute(RouteMatch::class);
                self::assertInstanceOf(RouteMatch::class, $routeMatch);
                /** @var RouteMatch $routeMatch */
                self::assertSame('foo', $routeMatch->getParam('middleware'));

                return true;
            }))
            ->willReturn(self::createMock(ResponseInterface::class));

        $this->controller->dispatch($request);
    }

    public function testWillNotSetRouteMatchParamsAsRequestAttributes()
    {
        $request = new Request();
        $routeMatch = new RouteMatch(['middleware' => 'foo']);
        $this->event->setRouteMatch($routeMatch);

        $this->requestHandler
            ->expects(self::once())
            ->method('handle')
            ->with(self::callback(function (ServerRequestInterface $request) {
                self::assertInstanceOf(RouteMatch::class, $request->getAttribute(RouteMatch::class));
                self::assertNull($request->getAttribute('middleware'));

                return true;
            }))
            ->willReturn(self::createMock(ResponseInterface::class));

        $this->controller->dispatch($request);
    }
}
