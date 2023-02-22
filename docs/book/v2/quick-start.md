# Quick Start

## Mapping Routes to Middleware and Request Handlers

The first step is to map a route to PSR-15 middleware or request handler. This looks like any other
[routing](https://docs.laminas.dev/laminas-mvc/routing/) configuration, with small changes: the `controller` key in the
routing options has to be the `Laminas\Mvc\Middleware\PipeSpec` class literal, and you provide a `middleware` key.

For example, to register an `AlbumListHandler` located in the `module/Application/Handler` directory to the routes of
your `Application` module, add the following route to `module/Application/config/module.config.php`:

```php
use Application\Handler\BlogListHandler;
use Laminas\Mvc\Middleware\PipeSpec;
use Laminas\Router\Http\Literal;

return [
    'service_manager' => [
        'factories' => [
            Middleware\BlogListMiddleware::class => Factory\BlogListMiddlewareFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'blog-list' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/blog',
                    'defaults' => [
                        'controller' => PipeSpec::class,
                        'middleware' => Middleware\BlogListMiddleware::class,
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
which they are present in the `PipeSpec`. See [dispatching middleware](dispatching-middleware.md) for examples.

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

### Middleware

```php
namespace Blog\Middleware;

use Blog\Repository\BlogListRepositoryInterface;
use Blog\Handler\BlogListnHandler;
use Fig\Http\Message\StatusCodeInterface;
use Laminas\Diactoros\StreamFactory;
use Laminas\Router\RouteMatch;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BlogListMiddleware implements MiddlewareInterface
{
    /** @var BlogListRepositoryInterface */
    public $blogListRepository;

    /** @var ResponseFactoryInterface */
    public $responseFactory;

    /** @var BlogListnHandler */
    protected $handler;

    /** @var StreamFactoryInterface */
    public $streamFactory;

    public function __construct(BlogListRepositoryInterface $blogListRepository, ResponseFactoryInterface $responseFactory)
    {
        $this->blogListRepository = $blogListRepository;
        $this->responseFactory    = $responseFactory;
        $this->streamFactory      = new StreamFactory();
        $this->handler            = new BlogListnHandler($this->responseFactory, $this->streamFactory);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var RouteMatch $routeMatch */
        $routeMatch = $request->getAttribute(RouteMatch::class);
        $blogId     = $routeMatch->getParam('blog_id');
        $blogPost   = $this->blogListRepository->findById($blogId);

        // if no album was found, we short-circuit the pipe and return a 404 error:
        if ($blogPost === null) {
            return $this->responseFactory->createResponse(
                StatusCodeInterface::STATUS_NOT_FOUND,
                sprintf('Blog post with ID %s not found!', $blogId)
            );
        }

        // ...otherwise we populate the request with the album and call the RequestHandler
        $request = $request->withAttribute('blogPost', $blogPost);
        return $this->handler->handle($request);
    }
}
```

Middleware can return a direct response, in effect short-circuiting the middleware pipe, or pass request further
while having a chance to act on passed request or returned response.
Middleware in laminas-mvc is similar to [routed middleware](https://docs.mezzio.dev/mezzio/v3/features/router/piping/#routing)
in Mezzio.
> laminas-mvc does not have a global middleware pipe, so middleware can not be piped in front of MVC controllers.

### Request Handlers

```php
namespace Blog\Handler;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

class BlogListHandler implements RequestHandlerInterface
{
    /** @var ResponseFactoryInterface */
    public $responseFactory;

    /** @var StreamFactoryInterface */
    public $streamFactory;

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory   = $streamFactory;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $blogPost = $request->getAttribute('blogPost');

        $body = $this->streamFactory->createStream('The name of the blog post title is: ' . $blogPost->getTitle());
        return $this->responseFactory->createResponse()->withBody($body);
    }
}
```

RequestHandlers resemble a single MVC Controller action, and will be used as the primary application functionality when
dispatching a request.

## Middleware Return Values

As middleware returns a PSR-7 response (`Psr\Http\Message\ResponseInterface`) it is converted back to a
[laminas-http](https://docs.laminas.dev/laminas-http/) response and returned by the `MiddlewareListener`, causing the
application to short-circuit and return the response immediately.
