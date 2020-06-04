<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc\Middleware;

use Exception;
use Laminas\Diactoros\Response as DiactorosResponse;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\SharedEventManager;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Application;
use Laminas\Mvc\Exception\InvalidMiddlewareException;
use Laminas\Mvc\Middleware\MiddlewareListener;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stdlib\DispatchableInterface;
use Laminas\Stratigility\Exception\EmptyPipelineException;
use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Laminas\View\Model\ModelInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;

use function sprintf;
use function uniqid;

/**
 * @covers \Laminas\Mvc\Middleware\MiddlewareListener
 */
class MiddlewareListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var RouteMatch
     */
    private $routeMatch;

    /**
     * Create an MvcEvent, populated with everything it needs.
     *
     * @param string $middlewareMatched Middleware service matched by routing
     * @param mixed $middleware Value to return for middleware service
     */
    public function createMvcEvent($middlewareMatched, $middleware = null): MvcEvent
    {
        $response = new Response();
        $this->routeMatch = new RouteMatch(['middleware' => $middlewareMatched]);

        $eventManager   = new EventManager();
        $serviceManager = new ServiceManager([
            'factories' => [
                'EventManager' => function () {
                    return new EventManager();
                },
            ],
            'services' => null !== $middleware ? [$middlewareMatched => $middleware] : [],
        ]);

        $application = $this->prophesize(Application::class);
        $application->getEventManager()->willReturn($eventManager);
        $application->getServiceManager()->willReturn($serviceManager);
        $application->getResponse()->willReturn($response);

        $event = new MvcEvent();
        $event->setRequest(new Request());
        $event->setResponse($response);
        $event->setApplication($application->reveal());
        $event->setRouteMatch($this->routeMatch);

        return $event;
    }

    public function testSuccessfullyDispatchesMiddlewareAndReturnsResponse()
    {
        $event = $this->createMvcEvent('path', new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $response = new DiactorosResponse();
                $response->getBody()->write('Test!');
                return $response;
            }
        });

        $listener = new MiddlewareListener();
        $return = $listener->onDispatch($event);

        $this->assertInstanceOf(Response::class, $return, 'Middleware dispatch failed');
        $this->assertSame(200, $return->getStatusCode());
        $this->assertEquals('Test!', $return->getBody());
    }

    public function testSuccessfullyDispatchesRequestHandlerAndReturnsResponse()
    {
        $event = $this->createMvcEvent('path', new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = new DiactorosResponse();
                $response->getBody()->write('Test!');
                return $response;
            }
        });

        $listener = new MiddlewareListener();
        $return = $listener->onDispatch($event);

        $this->assertInstanceOf(Response::class, $return, 'Middleware dispatch failed');
        $this->assertSame(200, $return->getStatusCode());
        $this->assertEquals('Test!', $return->getBody());
    }

    /**
     * @group integration
     */
    public function testRouteMatchIsInjectedToRequestAsAttribute()
    {
        $routeMatch = null;

        $event = $this->createMvcEvent('foo', new CallableMiddlewareDecorator(
            function (ServerRequestInterface $request, RequestHandlerInterface $handler) use (&$routeMatch) {
                $routeMatch = $request->getAttribute(RouteMatch::class);
                return new DiactorosResponse();
            }
        ));

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);
        $this->assertInstanceOf(Response::class, $return, 'Middleware dispatch failed');
        $this->assertInstanceOf(RouteMatch::class, $routeMatch);
    }

    /**
     * @group integration
     */
    public function testRouteMatchParametersAreNotInjectedAsAttributes()
    {
        $passedRequest = null;
        $event = $this->createMvcEvent('foo', new CallableMiddlewareDecorator(
            function (ServerRequestInterface $request, RequestHandlerInterface $handler) use (&$passedRequest) {
                $passedRequest = $request;
                return new DiactorosResponse();
            }
        ));
        $matchedRouteParam = uniqid('matched param', true);
        $this->routeMatch->setParam('myParam', $matchedRouteParam);

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);
        $this->assertInstanceOf(Response::class, $return, 'Middleware dispatch failed');
        $this->assertInstanceOf(ServerRequestInterface::class, $passedRequest);
        /** @var ServerRequestInterface $passedRequest */
        $this->assertArrayNotHasKey('myParam', $passedRequest->getAttributes());
    }

    public function testTriggersErrorForUncallableMiddleware()
    {
        $event       = $this->createMvcEvent('path');
        $application = $event->getApplication();

        $application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, function (MvcEvent $e) {
            $this->assertEquals(Application::ERROR_MIDDLEWARE_CANNOT_DISPATCH, $e->getError());
            $this->assertEquals('path', $e->getController());
            return 'FAILED';
        });

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);
        $this->assertEquals('FAILED', $return);
    }

    public function testTriggersErrorForExceptionRaisedInMiddleware()
    {
        $exception   = new Exception();
        $event       = $this->createMvcEvent('path', new CallableMiddlewareDecorator(function () use ($exception) {
            throw $exception;
        }));

        $application = $event->getApplication();
        $application->getEventManager()
            ->attach(MvcEvent::EVENT_DISPATCH_ERROR, function (MvcEvent $e) use ($exception) {
                $this->assertEquals(Application::ERROR_EXCEPTION, $e->getError());
                $this->assertSame($exception, $e->getParam('exception'));
                return 'FAILED';
            });

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);
        $this->assertEquals('FAILED', $return);
    }

    /**
     * Ensure that the listener tests for services in abstract factories.
     */
    public function testCanLoadFromAbstractFactory()
    {
        $response   = new Response();
        $routeMatch = $this->prophesize(RouteMatch::class);
        $routeMatch->getParam('middleware', false)->willReturn('test');
        $routeMatch->getParams()->willReturn([]);

        $eventManager = new EventManager();

        $serviceManager = new ServiceManager();
        $serviceManager->addAbstractFactory(TestAsset\MiddlewareAbstractFactory::class);
        $serviceManager->setFactory(
            'EventManager',
            function () {
                return new EventManager();
            }
        );

        $application = $this->prophesize(Application::class);
        $application->getEventManager()->willReturn($eventManager);
        $application->getServiceManager()->willReturn($serviceManager);
        $application->getResponse()->willReturn($response);

        $event = new MvcEvent();
        $event->setRequest(new Request());
        $event->setResponse($response);
        $event->setApplication($application->reveal());
        $event->setRouteMatch($routeMatch->reveal());

        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, function (MvcEvent $e) {
            $this->fail(sprintf('dispatch.error triggered when it should not be: %s', var_export($e->getError(), 1)));
        });

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);

        $this->assertInstanceOf(Response::class, $return);
        $this->assertSame(200, $return->getStatusCode());
        $this->assertEquals(TestAsset\Middleware::class, $return->getBody());
    }

    public function testMiddlewareWithNothingPipedReachesFinalHandlerException()
    {
        $response   = new Response();
        $routeMatch = $this->prophesize(RouteMatch::class);
        $routeMatch->getParams()->willReturn([]);
        $routeMatch->getParam('middleware', false)->willReturn([]);

        $eventManager = new EventManager();

        $serviceManager = $this->prophesize(ContainerInterface::class);
        $application = $this->prophesize(Application::class);
        $application->getEventManager()->willReturn($eventManager);
        $application->getServiceManager()->will(function () use ($serviceManager) {
            return $serviceManager->reveal();
        });
        $application->getResponse()->willReturn($response);

        $serviceManager->get('EventManager')->willReturn($eventManager);

        $event = new MvcEvent();
        $event->setRequest(new Request());
        $event->setResponse($response);
        $event->setApplication($application->reveal());
        $event->setRouteMatch($routeMatch->reveal());

        $event->getApplication()->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, function (MvcEvent $e) {
            $this->assertEquals(Application::ERROR_EXCEPTION, $e->getError());
            $this->assertInstanceOf(EmptyPipelineException::class, $e->getParam('exception'));
            return 'FAILED';
        });

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);
        $this->assertEquals('FAILED', $return);
    }

    public function testNullMiddlewareThrowsInvalidMiddlewareException()
    {
        $response   = new Response();
        $routeMatch = $this->prophesize(RouteMatch::class);
        $routeMatch->getParams()->willReturn([]);
        $routeMatch->getParam('middleware', false)->willReturn([null]);

        $eventManager = new EventManager();

        $serviceManager = $this->prophesize(ContainerInterface::class);
        $application = $this->prophesize(Application::class);
        $application->getEventManager()->willReturn($eventManager);
        $application->getServiceManager()->will(function () use ($serviceManager) {
            return $serviceManager->reveal();
        });
        $application->getResponse()->willReturn($response);

        $event = new MvcEvent();
        $event->setRequest(new Request());
        $event->setResponse($response);
        $event->setApplication($application->reveal());
        $event->setRouteMatch($routeMatch->reveal());

        $event->getApplication()->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, function (MvcEvent $e) {
            $this->assertEquals(Application::ERROR_MIDDLEWARE_CANNOT_DISPATCH, $e->getError());
            $this->assertInstanceOf(InvalidMiddlewareException::class, $e->getParam('exception'));
            return 'FAILED';
        });

        $listener = new MiddlewareListener();

        $return = $listener->onDispatch($event);
        $this->assertEquals('FAILED', $return);
    }

    public function testValidMiddlewareDispatchCancelsPreviousDispatchFailures()
    {
        $middlewareName = uniqid('middleware', true);
        $routeMatch     = new RouteMatch(['middleware' => $middlewareName]);
        $response       = new DiactorosResponse();
        /* @var Application|MockObject $application */
        $application    = $this->createMock(Application::class);
        $eventManager   = new EventManager();
        $middleware     = $this->getMockBuilder(MiddlewareInterface::class)->setMethods(['process'])->getMock();
        $serviceManager = new ServiceManager([
            'factories' => [
                'EventManager' => function () {
                    return new EventManager();
                },
            ],
            'services' => [
                $middlewareName => $middleware,
            ],
        ]);

        $application->expects(self::any())->method('getRequest')->willReturn(new Request());
        $application->expects(self::any())->method('getEventManager')->willReturn($eventManager);
        $application->expects(self::any())->method('getServiceManager')->willReturn($serviceManager);
        $application->expects(self::any())->method('getResponse')->willReturn(new Response());
        $middleware->expects(self::once())->method('process')->willReturn($response);

        $event = new MvcEvent();

        $event->setRequest(new Request());
        $event->setApplication($application);
        $event->setError(Application::ERROR_CONTROLLER_CANNOT_DISPATCH);
        $event->setRouteMatch($routeMatch);

        $listener = new MiddlewareListener();
        $result   = $listener->onDispatch($event);

        self::assertInstanceOf(Response::class, $result);
        self::assertInstanceOf(Response::class, $event->getResult());
        self::assertEmpty($event->getError(), 'Previously set MVC errors are canceled by a successful dispatch');
    }

    public function testValidMiddlewareFiresDispatchableInterfaceEventListeners()
    {
        $middlewareName = uniqid('middleware', true);
        $routeMatch     = new RouteMatch(['middleware' => $middlewareName]);
        $response       = new DiactorosResponse();
        /* @var Application|MockObject $application */
        $application    = $this->createMock(Application::class);
        $sharedManager  = new SharedEventManager();
        /* @var callable|MockObject $sharedListener */
        $sharedListener = $this->getMockBuilder(stdClass::class)->setMethods(['__invoke'])->getMock();
        $eventManager   = new EventManager();
        $middleware     = $this->getMockBuilder(MiddlewareInterface::class)->setMethods(['process'])->getMock();
        $serviceManager = new ServiceManager([
            'factories' => [
                'EventManager' => function () use ($sharedManager) {
                    return new EventManager($sharedManager);
                },
            ],
            'services' => [
                $middlewareName => $middleware,
            ],
        ]);

        $application->expects(self::any())->method('getRequest')->willReturn(new Request());
        $application->expects(self::any())->method('getEventManager')->willReturn($eventManager);
        $application->expects(self::any())->method('getServiceManager')->willReturn($serviceManager);
        $application->expects(self::any())->method('getResponse')->willReturn(new Response());
        $middleware->expects(self::once())->method('process')->willReturn($response);

        $event = new MvcEvent();

        $event->setRequest(new Request());
        $event->setApplication($application);
        $event->setError(Application::ERROR_CONTROLLER_CANNOT_DISPATCH);
        $event->setRouteMatch($routeMatch);

        $listener = new MiddlewareListener();

        $sharedManager->attach(DispatchableInterface::class, MvcEvent::EVENT_DISPATCH, $sharedListener);
        $sharedListener->expects(self::once())->method('__invoke')->with($event);

        $listener->onDispatch($event);
    }

    /**
     * @dataProvider alreadySetMvcEventResultProvider
     *
     * @param mixed $alreadySetResult
     */
    public function testWillNotDispatchWhenAnMvcEventResultIsAlreadySet($alreadySetResult)
    {
        $middlewareName = uniqid('middleware', true);
        $routeMatch     = new RouteMatch(['middleware' => $middlewareName]);
        /* @var Application|MockObject $application */
        $application    = $this->createMock(Application::class);
        $eventManager   = new EventManager();
        $middleware     = $this->getMockBuilder(stdClass::class)->setMethods(['__invoke'])->getMock();
        $serviceManager = new ServiceManager([
            'factories' => [
                'EventManager' => function () {
                    return new EventManager();
                },
            ],
            'services' => [
                $middlewareName => $middleware,
            ],
        ]);

        $application->expects(self::any())->method('getRequest')->willReturn(new Request());
        $application->expects(self::any())->method('getEventManager')->willReturn($eventManager);
        $application->expects(self::any())->method('getServiceManager')->willReturn($serviceManager);
        $application->expects(self::any())->method('getResponse')->willReturn(new Response());
        $middleware->expects(self::never())->method('__invoke');

        $event = new MvcEvent();

        $event->setResult($alreadySetResult); // a result is already there - listener should bail out early
        $event->setRequest(new Request());
        $event->setApplication($application);
        $event->setError(Application::ERROR_CONTROLLER_CANNOT_DISPATCH);
        $event->setRouteMatch($routeMatch);

        $listener = new MiddlewareListener();

        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, function () {
            self::fail('No dispatch failures should be raised - dispatch should be skipped');
        });

        $listener->onDispatch($event);

        self::assertSame($alreadySetResult, $event->getResult(), 'The event result was not replaced');
    }

    /**
     * @return mixed[][]
     */
    public function alreadySetMvcEventResultProvider()
    {
        return [
            [123],
            [true],
            [false],
            [[]],
            [new stdClass()],
            [$this],
            [$this->createMock(ModelInterface::class)],
            [$this->createMock(Response::class)],
            [['view model data' => 'as an array']],
            [['foo' => new stdClass()]],
            ['a response string'],
        ];
    }
}
