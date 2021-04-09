# Dispatching Middleware

Apart from PSR-15 `Psr\Http\Server\MiddlewareInterface` or `Psr\Http\Server\RequestHandlerInterface` middleware or
service name strings which resolve to such instances, the `middleware` key in the route definition takes also an
instance of the `PipeSpec` class for the middleware pipe comprising the former.

Technically, middleware or middleware pipe defined in each individual mvc route is a complete request handler:
it must produce a response and cannot delegate to a controller or middleware defined in another route.

Here are some examples how middleware can be dispatched:

## `MiddlewareInterface` or `RequestHandlerInterface` Service Strings

If you only dispatch one class, the preferred method is passing a container service class-string or alias which
resolves to a PSR-15 `Psr\Http\Server\MiddlewareInterface` or `Psr\Http\Server\RequestHandlerInterface`.

`Laminas\Stratigility\MiddlewarePipe` implements both interfaces and could be provided by the application container
as a configured instance. It is what is used internally for the `PipeSpec` definitions.

```php
use Application\Handler\AlbumDetailHandler;
use Application\Middleware\AlbumDetailMiddlewarePipe;
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\Router\Http\Literal;

return [
    'router' => [
        'routes' => [
            'container-handler-class-literal' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/container-handler-class-literal',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => AlbumDetailHandler::class,
                    ],
                ],
            ],
            'container-handler' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/container-handler',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => 'container_id_for_album_detail_handler',
                    ],
                ],
            ],
            'container-pipe' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/container-pipe',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => AlbumDetailMiddlewarePipe::class,
                    ],
                ],
            ],
             'container-middleware' => [
                 'type' => Literal::class,
                 'options' => [
                     'route' => '/container-middleware',
                     'defaults' => [
                         'controller' => PipeSpec::class,
                         'middleware' => SomeMiddleware::class,
                     ],
                 ],
             ],
       ],
    ],
];
```

## `PipeSpec` with PSR-15 Service Names

Middleware can be passed as a `PipeSpec` object with container service class-string literals. This is the preferred
method, if you need to dispatch multiple middleware instances.

Assuming you have two middleware, `Application\Middleware\SelectLanguageMiddleware` which determines the language of
the visitor and `Application\Middleware\AlbumFromRouteMiddleware` which fetches an `Album` object by the ID in the route
param. Both of them add this information to the `ServerRequestInterface` attributes which can be used by the
`Application\Handler\AlbumDetailHandler` at the end of the pipe:

```php
use Application\Handler\AlbumDetailHandler;
use Application\Middleware\AlbumFromRouteMiddlware;
use Application\Middleware\SelectLanguageMiddleware;
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\Router\Http\Segment;

return [
    'router' => [
        'routes' => [
            'album-details' => [
                'type' => Segment::class,
                'options' => [
                    'route' => '/album/:album_id',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => new PipeSpec(
                            SelectLanguageMiddleware::class,
                            AlbumFromRouteMiddelware::class,
                            AlbumDetailHandler::class
                        ),
                    ],
                ],
            ],
        ],
    ],
];
```

## Anonymous Interface Implementations

You can also pass an anonymous class which implements `MiddlewareInterface` or `RequestHandlerInterface` directly.
This is intended primarily for development because it can cause issues with configuration caching.

```php
use Laminas\Diactoros\Response\TextResponse;
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\Router\Http\Literal;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

return [
    'router' => [
        'routes' => [
            'instance-middleware' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/instance-middleware',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => new class implements MiddlewareInterface {
                            public function process(
                                ServerRequestInterface $request,
                                RequestHandlerInterface $handler
                            ): ResponseInterface {
                                // You have to return a response directly because attempting to use $handler here will
                                // always result in an empty pipeline exception.
                                // This middleware pipe does not delegate to controllers or middleware from other routes.
                                return new TextResponse('Hello!');
                            }
                        },
                    ],
                ],
            ],
            'instance-handler' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/instance-handler',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => new class implements RequestHandlerInterface {
                            public function handle(
                                ServerRequestInterface $request
                            ): ResponseInterface {
                                return new TextResponse('Hello!');
                            }
                        },
                    ],
                ],
            ],
        ],
    ],
];
```

## Closures

A `Closure` can also be passed directly. This should also be used only for development.

```php
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\Router\Http\Literal;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

return [
    'router' => [
        'routes' => [
            'closure' => [
                'type'    => Literal::class,
                'options' => [
                    'route'    => '/closure',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => static function (
                            ServerRequestInterface $request,
                            RequestHandlerInterface $handler
                        ): ResponseInterface {
                            return $handler->handle($request);
                        },
                    ],
                ],
            ],
        ],
    ],
];
```

## Mixed `PipeSpec` Arguments

In this example multiple different possible parameters are passed to `PipeSpec`:

```php
use Application\Handler\AlbumDetailHandler;
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\Router\Http\Literal;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

return [
    'router' => [
        'routes' => [
            'instance-pipespec-dev-env' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/instance-pipespec-dev-env',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => new PipeSpec(

                            // id/alias of service in container
                            'container_id_for_album_middleware',

                            // anonymous class implementing a MiddlewareInterface
                            new class implements MiddlewareInterface {
                                public function process(
                                    ServerRequestInterface $request,
                                    RequestHandlerInterface $handler
                                ): ResponseInterface {
                                    return $handler->handle($request);
                                }
                            },

                            // Closure
                            static function (
                                ServerRequestInterface $request,
                                RequestHandlerInterface $handler
                            ): ResponseInterface {
                                return $handler->handle($request);
                            },

                            // class-string of a handler as a convenient way to specify id/alias
                            // which must be resolved by the container
                            AlbumDetailHandler::class,
                        ),
                    ],
                ],
            ],
        ],
    ],
];
```

> ### Note
>
> Please note that if you pass instances of middleware or handlers directly, it might not be compatible with config caching.
> You should use that for development only. The recommended variant is passing container managed middleware
> like `SomeRequestHandler::class`.

## Defining `:middleware` Placeholder in Route Definition

Although you can define a `:middleware` placeholder in your middleware route definition, we are strongly advising
against doing so. This can be utilized maliciously to invoke middleware that was never intended to be called directly.
Since middleware lookup uses the main application container, it can also be abused to cause instantiation of expensive
services leading to a DoS vulnerability.
