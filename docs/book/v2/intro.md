# Introduction

This library provides the ability to dispatch middleware pipelines in place of
controllers within [laminas-mvc](https://docs.laminas.dev/laminas-mvc/).

## Dispatching PSR-15 Middleware and Request Handlers

[PSR-7](https://www.php-fig.org/psr/psr-7/) defines interfaces for HTTP messages, and is now being adopted by many
frameworks. [PSR-15](https://www.php-fig.org/psr/psr-15/) describes Middleware and Request Handler interfaces, which
consume PSR-7 messages; Laminas itself offers a parallel microframework targeting PSR-7/PSR-15
with [Mezzio](https://docs.mezzio.dev/mezzio). What if you want to dispatch PSR-15 middleware and request handlers from
laminas-mvc?

laminas-mvc currently uses [laminas-http](https://docs.laminas.dev/laminas-http/)
for its HTTP transport layer, and the objects it defines are not compatible with PSR-7, meaning the basic MVC layer does
not and cannot make use of PSR-7 currently.

However, starting with version 2.7.0, laminas-mvc offers
`Laminas\Mvc\MiddlewareListener`. This `Laminas\Mvc\MvcEvent::EVENT_DISPATCH`
listener listens prior to the default `DispatchListener`, and executes if the route matches contain a "middleware"
parameter, and the service that resolves to is callable. When those conditions are met, it uses
the [PSR-7 bridge](https://docs.laminas.dev/laminas-psr7bridge/)
to convert the laminas-http request and response objects into PSR-7 instances, and then invokes the middleware.

Starting with laminas-mvc version 3.2.0, `Laminas\Mvc\MiddlewareListener` is deprecated and replaced
by `Laminas\Mvc\Middleware\MiddlewareListener` provided by this package.  
After package installation, `Laminas\Mvc\Middleware` module must be registered in your laminas-mvc based application. If
the [laminas-component-installer](https://docs.laminas.dev/laminas-component-installer/)
is installed, it will handle the module registration automatically.
