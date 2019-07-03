# Introduction

This library provides the ability to dispatch middleware pipelines in place of
controllers within zend-mvc.

## Dispatching PSR-7 Middleware

[PSR-7](http://www.php-fig.org/psr/psr-7/) defines interfaces for HTTP messages,
and is now being adopted by many frameworks; Zend Framework itself offers a
parallel microframework targeting PSR-7 with [Expressive](https://docs.zendframework.com/zend-expressive).
What if you want to dispatch PSR-7 middleware from zend-mvc?

zend-mvc currently uses [zend-http](https://github.com/zendframework/zend-http)
for its HTTP transport layer, and the objects it defines are not compatible with
PSR-7, meaning the basic MVC layer does not and cannot make use of PSR-7
currently.

However, starting with version 2.7.0, zend-mvc offers
`Zend\Mvc\MiddlewareListener`. This `Zend\Mvc\MvcEvent::EVENT_DISPATCH`
listener listens prior to the default `DispatchListener`, and executes if the
route matches contain a "middleware" parameter, and the service that resolves to
is callable. When those conditions are met, it uses the [PSR-7 bridge](https://github.com/zendframework/zend-psr7bridge)
to convert the zend-http request and response objects into PSR-7 instances, and
then invokes the middleware.

Starting with zend-mvc version 3.2.0, `Zend\Mvc\MiddlewareListener` is deprecated and replaced
by `Zend\Mvc\Middleware\MiddlewareListener` provided by this package.  
After package installation, `Zend\Mvc\Middleware` module must be registered in your
zend-mvc based application.

## Mapping routes to Middleware

The first step is to map a route to PSR-7 middleware. This looks like any other
[routing](https://docs.zendframework.com/zend-mvc/routing/) configuration,
with one small change: instead of providing a `controller` in the routing
defaults, you provide `middleware`:

```php
// Via configuration:
use Application\Middleware\IndexMiddleware;
use Zend\Router\Http\Literal;

return [
    'router' => [
        'routes' => [
            'home' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/',
                    'defaults' => [
                        'middleware' => IndexMiddleware::class,
                    ],
                ],
            ],
        ],
    ],
];
```

Middleware may be provided as PHP callables, [http-interop/http-middleware](https://github.com/http-interop/http-middleware)
or as string service names.  
You may also specify an `array` of above middleware types. These will then be piped
into a `Zend\Stratigility\MiddlewarePipe` instance in the order in which they
are present in the array.

> ### No action required
>
> Unlike action controllers, middleware typically is single purpose, and, as
> such, does not require a default `action` parameter.

## Middleware services

In a normal zend-mvc dispatch cycle, controllers are pulled from a dedicated
`ControllerManager`. Middleware, however, are pulled from the application
service manager.

Middleware retrieved *must* be PHP callables or http-middleware instances.
The `MiddlewareListener` will create an error response if non-callable middleware
is indicated.

## Writing Middleware

Starting in zend-mvc 3.1.0 and continued in this package, the `MiddlewareListener`
always adds middleware to a `Zend\Stratigility\MiddlewarePipe` instance, and invokes it as
[http-interop/http-middleware](https://github.com/http-interop/http-middleware),
passing it a PSR-7 `ServerRequestInterface` and an http-interop `DelegateInterface`.

As such, ideally your middleware should implement the `MiddlewareInterface` from
[http-interop/http-middleware](https://github.com/http-interop/http-middleware):

```php
namespace Application\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;

class IndexMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        // do some work
    }
}
```

Alternately, you may still write `callable` middleware using the following
signature:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

function (ServerRequestInterface $request, ResponseInterface $response, callable $next)
{
    // do some work
}
```

In the above case, the `DelegateInterface` is decorated as a callable.

In all versions, within your middleware, you can pull information from the
composed request, and return a response.

> ### Routing parameters
>
> Route match parameters are pushed into the PSR-7 `ServerRequest` as
> attributes, and may thus be fetched using `$request->getAttribute($attributeName)`.

## Middleware return values

Your middleware must return a PSR-7 response. It is converted back to a zend-http
response and returned by the `MiddlewareListener`, causing the application to
short-circuit and return the response immediately.
