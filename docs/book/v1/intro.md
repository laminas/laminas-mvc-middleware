# Introduction

This library provides the ability to dispatch middleware pipelines in place of
controllers within laminas-mvc.

## Dispatching PSR-7 Middleware

[PSR-7](http://www.php-fig.org/psr/psr-7/) defines interfaces for HTTP messages,
and is now being adopted by many frameworks; Laminas itself offers a
parallel microframework targeting PSR-7 with [Mezzio](https://docs.mezzio.dev/mezzio).
What if you want to dispatch PSR-7 middleware from [laminas-mvc](https://docs.laminas.dev/laminas-mvc/)?

laminas-mvc currently uses [laminas-http](https://docs.laminas.dev/laminas-http/)
for its HTTP transport layer, and the objects it defines are not compatible with
PSR-7, meaning the basic MVC layer does not and cannot make use of PSR-7
currently.

However, starting with version 2.7.0, laminas-mvc offers
`Laminas\Mvc\MiddlewareListener`. This `Laminas\Mvc\MvcEvent::EVENT_DISPATCH`
listener listens prior to the default `DispatchListener`, and executes if the
route matches contain a "middleware" parameter, and the service that resolves to
is callable. When those conditions are met, it uses the [PSR-7 bridge](https://docs.laminas.dev/laminas-psr7bridge/)
to convert the laminas-http request and response objects into PSR-7 instances, and
then invokes the middleware.

Starting with laminas-mvc version 3.2.0, `Laminas\Mvc\MiddlewareListener` is deprecated and replaced
by `Laminas\Mvc\Middleware\MiddlewareListener` provided by this package.  
After package installation, `Laminas\Mvc\Middleware` module must be registered in your
laminas-mvc based application.

## Mapping Routes to Middleware

The first step is to map a route to PSR-7 middleware. This looks like any other
[routing](https://docs.laminas.dev/laminas-mvc/routing/) configuration,
with one small change: instead of providing a `controller` in the routing
defaults, you provide `middleware`:

```php
// Via configuration:
use Application\Middleware\IndexMiddleware;
use Laminas\Router\Http\Literal;

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
into a `Laminas\Stratigility\MiddlewarePipe` instance in the order in which they
are present in the array.

Starting with the 1.1 release, direct usage of double-pass and callable middleware
is deprecated. [laminas-stratigility](https://docs.laminas.dev/laminas-stratigility/) 2.2 provides the following
decorators and helper functions that are forwards-compatible with its 3.0 release:

- `Laminas\Stratigility\Middleware\CallableMiddlewareDecorator`
- `Laminas\Stratigility\Middleware\DoublePassMiddlewareDecorator`
- `Laminas\Stratigility\doublePassMiddleware()`
- `Laminas\Stratigility\middleware()`

> ### No Action Required
>
> Unlike action controllers, middleware typically is single purpose, and, as
> such, does not require a default `action` parameter.

## Middleware Services

In a normal laminas-mvc dispatch cycle, controllers are pulled from a dedicated
`ControllerManager`. Middleware, however, are pulled from the application
service manager.

Middleware retrieved *must* be PHP callables or http-middleware instances.
The `MiddlewareListener` will create an error response if non-callable middleware
is indicated.

## Writing Middleware

Starting in laminas-mvc 3.1.0 and continued in this package, the `MiddlewareListener`
always adds middleware to a `Laminas\Stratigility\MiddlewarePipe` instance, and invokes it as
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

> ### Routing Parameters
>
> Route match parameters are pushed into the PSR-7 `ServerRequest` as
> attributes, and may thus be fetched using `$request->getAttribute($attributeName)`.

## Middleware Return Values

Your middleware must return a PSR-7 response. It is converted back to a laminas-http
response and returned by the `MiddlewareListener`, causing the application to
short-circuit and return the response immediately.
