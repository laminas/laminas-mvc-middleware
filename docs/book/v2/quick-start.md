# Quick Start

## Mapping Routes to Middleware and Request Handlers

The first step is to map a route to PSR-15 middleware or request handler. This looks like any other
[routing](https://docs.laminas.dev/laminas-mvc/routing/) configuration, with small changes: the `controller` key in the
routing options has to be the `Laminas\Mvc\Middleware\PipeSpec` class literal, and you provide a `middleware` key.

For example, to register an `AlbumListHandler` located in the `module/Application/Handler` directory to the routes of
your `Application` module, add the following route to `module/Application/config/module.config.php`:

```php
use Application\Handler\AlbumListHandler;
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\Router\Http\Literal;

return [
    'router' => [
        'routes' => [
            'album-list' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/albums',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => AlbumListHandler::class,
                    ],
                ],
            ],
        ],
    ],
];
```

Middleware may be provided as instance of a PSR-15 `Psr\Http\Server\MiddlewareInterface`
or `Psr\Http\Server\RequestHandlerInterface`
or as service name strings resolving to such instances.  
You may also specify an instance of the `PipeSpec` class which accepts both middleware types above or their service name
strings, or a `Closure` . These will then be piped into a `Laminas\Stratigility\MiddlewarePipe` instance in the order in
which they are present in the `PipeSpec`. See [routing middleware](routing-middleware.md) for examples.

> ### No Action Required
>
> Unlike action controllers, middleware typically is single purpose, and, as
> such, does not require a default `action` parameter.

## Middleware Services

Middleware are pulled from the application service manager, unlike controllers in a normal
[laminas-mvc](https://docs.laminas.dev/laminas-mvc/) dispatch cycle, which are pulled from a
dedicated `ControllerManager`.

Middleware retrieved **must** be a PSR-15 `MiddlewareInterface` or `RequestHandlerInterface` instance.
Otherwise, `MiddlewareListener` will create an error response.

## Writing Middleware

The next step is writing actual middleware to dispatch. PSR-15 defines two different interfaces:

### `MiddlewareInterface` vs. `RequestHandlerInterface`

_Middleware_ is code sitting between a request and a response; it typically analyzes the request to aggregate incoming data, delegates it to another layer to process, and then creates and returns a response.

A _RequestHandler_ is a class that receives a request and returns a response, without delegating to other layers of the application. This is generally the inner-most layer of your application.

For more in-depth documentation visit the documentation for [Mezzio](https://docs.mezzio.dev/mezzio/v3/getting-started/features/)
and [Stratigility](https://docs.laminas.dev/laminas-stratigility/v3/intro/) or the [PSR-15 specification](https://www.php-fig.org/psr/psr-15/).

### Request Handlers

```php
namespace Application\Handler;

use Application\Entity\Album;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AlbumDetailMiddleware implements RequestHandlerInterface
{
    /** @var ResponseFactoryInterface */
    private $responseFactory;
    /** @var StreamFactoryInterface */
    private $streamFactory;

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory   = $streamFactory;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var Album $album */
        $album = $request->getAttribute('album');

        $body = $this->streamFactory->createStream('The name of the album is: ' . $album->getName());
        return $this->responseFactory->createResponse()->withBody($body);
    }
}
```

RequestHandlers resemble a single MVC Controller action, and will be used as the primary application functionality when
dispatching a request.

### Middleware

```php
namespace Application\Middleware;

use Application\Repository\AlbumRepositoryInterface;
use Fig\Http\Message\StatusCodeInterface;
use Laminas\Router\RouteMatch;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AlbumFromRouteMiddleware implements MiddlewareInterface
{
    /** @var AlbumRepositoryInterface */
    private $albumRepository;
    /** @var ResponseFactoryInterface */
    private $responseFactory;

    public function __construct(AlbumRepositoryInterface $albumRepository, ResponseFactoryInterface $responseFactory)
    {
        $this->albumRepository = $albumRepository;
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var RouteMatch $routeMatch */
        $routeMatch = $request->getAttribute(RouteMatch::class);
        $albumId    = $routeMatch->getParam('album_id');
        $album      = $this->albumRepository->findById($albumId);

        // if no album was found, we short-circuit the pipe and return a 404 error:
        if ($album === null) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_NOT_FOUND,
                sprintf('Album with ID %s not found!', $albumId)
            );
        }

        // ...otherwise we populate the request with the album and call the RequestHandler
        $request = $request->withAttribute('album', $album);
        return $handler->handle($request);
    }
}
```

Middleware can return a direct response, in effect short-circuiting the middleware pipe, or pass request further
while having a chance to act on passed request or returned response.
Middleware in laminas-mvc is similar to [routed middleware](https://docs.mezzio.dev/mezzio/v3/features/router/piping/#routing)
in Mezzio.
> laminas-mvc does not have a global middleware pipe, so middleware can not be piped in front of MVC controllers.

## Middleware Return Values

As middleware returns a PSR-7 response (`Psr\Http\Message\ResponseInterface`) it is converted back to a
[laminas-http](https://docs.laminas.dev/laminas-http/) response and returned by the `MiddlewareListener`, causing the
application to short-circuit and return the response immediately.
