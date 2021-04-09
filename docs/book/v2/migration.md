# Migration from Version 1

This document details changes made from version 1 of this package or from its implementation in
[laminas-mvc](https://docs.laminas.dev/laminas-mvc/).
If installed, it overrides the default behavior in laminas-mvc.

## `'controller'` Route Array Key

In addition to providing a `'middleware'` array key in the route defaults definition, the value of the `'controller'`
array key has to be explicitly set to `Laminas\Mvc\Middleware\PipeSpec::class`:

```php
/* ... */
'some-route' => [
    'type' => Literal::class,
    'options' => [
        'route' => '/some-route',
        'defaults' => [
            'controller' => \Laminas\Mvc\Middleware\PipeSpec::class,
            'middleware' => SomeRequestHandler::class,
        ],
    ],
],
```

## `PipeSpec` Instance in `middleware` Definition

If you used to pass an array of middleware to the `middleware` array key to create a middleware pipe, it has to be
rewritten to an instance of the `PipeSpec` object.

Before:

```php
/* ... */
'some-route' => [
    'type' => Literal::class,
    'options' => [
        'route' => '/some-route',
        'defaults' => [
            'middleware' => [
                SomeMiddleware::class,
                SomeRequestHandler::class,
            ],
        ],
    ],
],
```

After:

```php
/* ... */
'some-route' => [
    'type' => Literal::class,
    'options' => [
        'route' => '/some-route',
        'defaults' => [
            'controller' => \Laminas\Mvc\Middleware\PipeSpec::class,
            'middleware' => new \Laminas\Mvc\Middleware\PipeSpec(
                SomeMiddleware::class,
                SomeRequestHandler::class
            ),
        ],
    ],
],
```

## PSR-15 Middleware

This package now supports only PSR-15 interfaces. Support of `http-interop/http-middleware` and `callable` middleware
has been dropped. A `Closure` can still be used and its signature must match the signature of PSR-15 middleware:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

static function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
```

You have to rewrite all your existing handlers and middleware to implement `Psr\Http\Server\RequestHandlerInterface`
or `Psr\Http\Server\MiddlewareInterface`, respectively. Alternatively, you can decorate your middleware using one of
the [decorators provided by laminas-stratigility](https://docs.laminas.dev/laminas-stratigility/v3/creating-middleware/)
.

Please also refer
to [laminas-stratigility v3 migration guide](https://docs.laminas.dev/laminas-stratigility/v3/migration/)
if you used any of its features.

## Removed Route Match Params from Request

Previously, for the matched route both the `Laminas\Router\RouteMatch` object as well as individual matched params were
added to the PSR-7 request as attributes. This is no longer the case and only the `RouteMatch` object can be accessed as
PSR-7 request attribute: `$request->getAttribute(\Laminas\Router\RouteMatch::class);`

Consider the following route definition: `/show-album/:album_id`
Previously, all matched parameters (in this case `album_id`) were available directly in the `$request` as an attribute:
`$albumId = $request->getAttribute('album_id')`. Now, this is no longer the case. To get the `album_id` param you have
to fetch it from the `RouteMatch` object:

```php
/** @var \Laminas\Router\RouteMatch $routeMatch */
$routeMatch = $request->getAttribute(\Laminas\Router\RouteMatch::class);
$albumId = $routeMatch->getParam('album_id');
```
