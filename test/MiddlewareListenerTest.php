<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

declare(strict_types=1);

namespace LaminasTest\Mvc\Middleware;

use Exception;
use Laminas\Diactoros\Response as DiactorosResponse;
use Laminas\EventManager\EventManager;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Mvc\Application;
use Laminas\Mvc\Middleware\InvalidMiddlewareException;
use Laminas\Mvc\Middleware\MiddlewareListener;
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\Mvc\MvcEvent;
use Laminas\Router\RouteMatch;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Stratigility\Exception\EmptyPipelineException;
use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Laminas\View\Model\ModelInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use stdClass;

/**
 * @covers \Laminas\Mvc\Middleware\MiddlewareListener
 */
class MiddlewareListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * Create an MvcEvent, populated with everything it needs.
     *
     * @psalm-param array<string, mixed> $matchedParams
     * @psalm-param array<string, mixed> $services
     */
    public function createMvcEvent(array $matchedParams, array $services = []): MvcEvent
    {
        $response   = new Response();
        $routeMatch = new RouteMatch($matchedParams);

        $eventManager   = new EventManager();
        $serviceManager = new ServiceManager([
            'factories' => [
                'EventManager' => function () {
                    return new EventManager();
                },
            ],
            'services'  => $services,
        ]);

        $application = $this->prophesize(Application::class);
        $application->getEventManager()->willReturn($eventManager);
        $application->getServiceManager()->willReturn($serviceManager);
        $application->getResponse()->willReturn($response);

        $event = new MvcEvent();
        $event->setRequest(new Request());
        $event->setResponse($response);
        $event->setApplication($application->reveal());
        $event->setRouteMatch($routeMatch);

        return $event;
    }

    public function validMiddlewareProvider(): iterable
    {
        // Remember that mutable body writes are bad!
        $middleware             = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $response = $handler->handle($request);
                $response->getBody()->write('Middleware!');
                return $response;
            }
        };
        $directReturnMiddleware = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $response = new DiactorosResponse();
                $response->getBody()->write('Middleware!');
                return $response;
            }
        };
        $handler                = new class implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request
            ): ResponseInterface {
                $response = new DiactorosResponse();
                $response->getBody()->write('Handler!');
                return $response;
            }
        };
        $middlewareWithHandler  = new class implements MiddlewareInterface, RequestHandlerInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $response = new DiactorosResponse();
                $response->getBody()->write('Middleware!');
                return $response;
            }

            public function handle(
                ServerRequestInterface $request
            ): ResponseInterface {
                $response = new DiactorosResponse();
                $response->getBody()->write('Handler!');
                return $response;
            }
        };
        $closureMiddleware      = static function (
            ServerRequestInterface $request,
            RequestHandlerInterface $handler
        ): ResponseInterface {
            $response = $handler->handle($request);
            $response->getBody()->write('Closure Middleware!');
            return $response;
        };
        $containerMiddleware    = new class implements MiddlewareInterface {
            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler
            ): ResponseInterface {
                $response = $handler->handle($request);
                $response->getBody()->write('Container Middleware!');
                return $response;
            }
        };
        $containerHandler       = new class implements RequestHandlerInterface {
            public function handle(
                ServerRequestInterface $request
            ): ResponseInterface {
                $response = new DiactorosResponse();
                $response->getBody()->write('Container Handler!');
                return $response;
            }
        };

        yield 'middleware as container key string' => [
            [
                'controller' => PipeSpec::class,
                'middleware' => 'middleware_container_key',
            ],
            [
                'middleware_container_key' => $directReturnMiddleware,
            ],
            'Middleware!',
        ];
        yield 'middleware as middleware instance' => [
            [
                'controller' => PipeSpec::class,
                'middleware' => $directReturnMiddleware,
            ],
            [],
            'Middleware!',
        ];
        yield 'middleware as handler instance' => [
            [
                'controller' => PipeSpec::class,
                'middleware' => $handler,
            ],
            [],
            'Handler!',
        ];
        yield 'middleware as middleware+handler instance uses handler' => [
            [
                'controller' => PipeSpec::class,
                'middleware' => $middlewareWithHandler,
            ],
            [],
            'Handler!',
        ];
        yield 'middleware as PipeSpec with middleware+handler instance uses middleware' => [
            [
                'controller' => PipeSpec::class,
                'middleware' => new PipeSpec($middlewareWithHandler),
            ],
            [],
            'Middleware!',
        ];
        yield 'middleware as PipeSpec' => [
            [
                'controller' => PipeSpec::class,
                'middleware' => new PipeSpec(
                    $middleware,
                    $handler
                ),
            ],
            [],
            'Handler!Middleware!',
        ];
        yield 'middleware as PipeSpec with all supported types' => [
            [
                'controller' => PipeSpec::class,
                'middleware' => new PipeSpec(
                    $middleware,
                    'middleware_container_key',
                    $closureMiddleware,
                    'handler_container_key'
                ),
            ],
            [
                'middleware_container_key' => $containerMiddleware,
                'handler_container_key'    => $containerHandler,
            ],
            'Container Handler!Closure Middleware!Container Middleware!Middleware!',
        ];
    }

    /**
     * @dataProvider validMiddlewareProvider
     * @psalm-param array<string, mixed> $matchedParams
     * @psalm-param array<string, mixed> $services
     */
    public function testSuccessfullyDispatchesMiddlewareAndReturnsResponse(
        array $matchedParams,
        array $services,
        string $expectedBody
    ): void {
        $event = $this->createMvcEvent($matchedParams, $services);

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);

        self::assertInstanceOf(
            Response::class,
            $return,
            (string) $event->getParam('exception', 'Middleware dispatch failed')
        );
        self::assertSame(200, $return->getStatusCode());
        self::assertEquals($expectedBody, $return->getBody());
        self::assertNull($event->getParam('exception'));
    }

    /**
     * @dataProvider validMiddlewareProvider
     * @psalm-param array<string, mixed> $matchedParams
     * @psalm-param array<string, mixed> $services
     */
    public function testIgnoresMiddlewareParamIfControllerMarkerIsNotPipeSpec(
        array $matchedParams,
        array $services
    ): void {
        $matchedParams['controller'] = 'some_controller';
        $event                       = $this->createMvcEvent($matchedParams, $services);

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);

        self::assertNull($return, 'Middleware must not be dispatched');
        self::assertNull($event->getResult(), 'Middleware must not be dispatched');
    }

    public function testDoesNotAcceptMiddlewareParamAsArray(): void
    {
        $matchedParams = [
            'controller' => PipeSpec::class,
            'middleware' => [
                new class implements MiddlewareInterface {
                    public function process(
                        ServerRequestInterface $request,
                        RequestHandlerInterface $handler
                    ): ResponseInterface {
                        $response = new DiactorosResponse();
                        $response->getBody()->write('Test!');
                        return $response;
                    }
                },
            ],
        ];
        $event         = $this->createMvcEvent($matchedParams, []);
        $application   = $event->getApplication();

        $application->getEventManager()->attach(MvcEvent::EVENT_DISPATCH_ERROR, static function (MvcEvent $e) {
            self::assertEquals(Application::ERROR_MIDDLEWARE_CANNOT_DISPATCH, $e->getError());
            return $e->getParam('exception');
        });

        $listener = new MiddlewareListener();
        /** @var InvalidMiddlewareException $return */
        $return = $listener->onDispatch($event);
        self::assertInstanceOf(InvalidMiddlewareException::class, $return);
        self::assertStringContainsString(
            'array given',
            $return->getMessage(),
            'Exception should provide details of invalid value'
        );
    }

    public function testRouteMatchIsInjectedToRequestAsAttribute(): void
    {
        $routeMatch    = null;
        $matchedParams = [
            'controller' => PipeSpec::class,
            'middleware' => new CallableMiddlewareDecorator(
                static function (ServerRequestInterface $request) use (&$routeMatch) {
                    $routeMatch = $request->getAttribute(RouteMatch::class);
                    return new DiactorosResponse();
                }
            ),
        ];

        $event = $this->createMvcEvent($matchedParams, []);

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);
        self::assertInstanceOf(Response::class, $return, 'Middleware dispatch failed');
        self::assertInstanceOf(RouteMatch::class, $routeMatch);
        self::assertEquals($matchedParams, $routeMatch->getParams());
    }

    public function testRouteMatchParametersAreNotInjectedAsAttributes(): void
    {
        $passedRequest = null;

        $matchedParams = [
            'controller' => PipeSpec::class,
            'middleware' => new CallableMiddlewareDecorator(
                static function (ServerRequestInterface $request) use (&$passedRequest) {
                    $passedRequest = $request;
                    return new DiactorosResponse();
                }
            ),
            'test_param' => 'test_param_value',
        ];

        $event    = $this->createMvcEvent($matchedParams, []);
        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);

        self::assertInstanceOf(Response::class, $return, 'Middleware dispatch failed');
        self::assertInstanceOf(ServerRequestInterface::class, $passedRequest);
        $attributes = $passedRequest->getAttributes();
        self::assertArrayNotHasKey('test_param', $attributes);
        self::assertArrayNotHasKey('controller', $attributes);
        self::assertArrayNotHasKey('middleware', $attributes);
    }

    public function testTriggersDispatchErrorForExceptionRaisedInMiddleware(): void
    {
        $exception     = new Exception();
        $matchedParams = [
            'controller' => PipeSpec::class,
            'middleware' => new CallableMiddlewareDecorator(static function () use ($exception) {
                throw $exception;
            }),
        ];
        $event         = $this->createMvcEvent($matchedParams, []);

        $application = $event->getApplication();
        $application->getEventManager()
            ->attach(MvcEvent::EVENT_DISPATCH_ERROR, static function (MvcEvent $e) {
                self::assertEquals(Application::ERROR_EXCEPTION, $e->getError());
                return $e->getParam('exception');
            });

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);
        self::assertSame($exception, $return, 'Thrown exception must be provided as MvcEvent exception parameter');
    }

    public function testExhaustedMiddlewarePipeTriggersDispatchError(): void
    {
        $exception     = new Exception();
        $matchedParams = [
            'controller' => PipeSpec::class,
            'middleware' => new PipeSpec(),
        ];
        $event         = $this->createMvcEvent($matchedParams, []);

        $application = $event->getApplication();
        $application->getEventManager()
            ->attach(MvcEvent::EVENT_DISPATCH_ERROR, static function (MvcEvent $e) {
                self::assertEquals(Application::ERROR_EXCEPTION, $e->getError());
                return 'Dispatch error triggered';
            });

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);
        self::assertSame('Dispatch error triggered', $return);
        self::assertInstanceOf(EmptyPipelineException::class, $event->getParam('exception'));
    }

    /**
     * @dataProvider validMiddlewareProvider
     * @psalm-param array<string, mixed> $matchedParams
     * @psalm-param array<string, mixed> $services
     */
    public function testValidMiddlewareDispatchCancelsPreviousDispatchFailures(
        array $matchedParams,
        array $services
    ): void {
        $event = $this->createMvcEvent($matchedParams, $services);
        $event->setError(Application::ERROR_CONTROLLER_CANNOT_DISPATCH);

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);

        self::assertInstanceOf(Response::class, $return, 'Middleware dispatch failed');
        self::assertSame(200, $return->getStatusCode());
        self::assertEmpty($event->getError(), 'Previously set MVC errors are canceled by a successful dispatch');
    }

    /**
     * @dataProvider alreadySetMvcEventResultProvider
     * @param mixed $alreadySetResult
     */
    public function testWillNotDispatchWhenAnMvcEventResultIsAlreadySet($alreadySetResult): void
    {
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects(self::never())
            ->method('process');
        $matchedParams = [
            'controller' => PipeSpec::class,
            'middleware' => $middleware,
        ];
        $event         = $this->createMvcEvent($matchedParams, []);
        $event->setResult($alreadySetResult);

        $listener = new MiddlewareListener();
        $return   = $listener->onDispatch($event);

        self::assertNull($return, 'Middleware must not be dispatched');
        self::assertEquals($alreadySetResult, $event->getResult());
    }

    /**
     * @return mixed[][]
     */
    public function alreadySetMvcEventResultProvider(): array
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
