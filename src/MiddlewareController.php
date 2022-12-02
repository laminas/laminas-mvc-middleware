<?php

declare(strict_types=1);

namespace Laminas\Mvc\Middleware;

use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Request;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\Controller\MiddlewareController as DeprecatedMiddlewareController;
use Laminas\Mvc\Exception\RuntimeException;
use Laminas\Mvc\MvcEvent;
use Laminas\Psr7Bridge\Psr7ServerRequest;
use Laminas\Router\RouteMatch;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function sprintf;

/**
 * @internal
 */
final class MiddlewareController extends AbstractController
{
    /** @var RequestHandlerInterface */
    private $requestHandler;

    public function __construct(
        RequestHandlerInterface $requestHandler,
        EventManagerInterface $eventManager,
        MvcEvent $event
    ) {
        $this->eventIdentifier = [
            DeprecatedMiddlewareController::class,
            self::class,
        ];
        $this->requestHandler  = $requestHandler;

        $this->setEventManager($eventManager);
        $this->setEvent($event);
    }

    /**
     * {@inheritDoc}
     *
     * @throws RuntimeException
     */
    public function onDispatch(MvcEvent $e)
    {
        $routeMatch  = $e->getRouteMatch();
        $psr7Request = $this->loadRequest();
        if ($routeMatch) {
            $psr7Request = $psr7Request->withAttribute(RouteMatch::class, $routeMatch);
        }

        $psr7Response = $this->requestHandler->handle($psr7Request);

        $e->setResult($psr7Response);
        return $psr7Response;
    }

    /**
     * @throws RuntimeException
     */
    private function loadRequest(): ServerRequestInterface
    {
        $request = $this->request;

        if (! $request instanceof Request) {
            throw new RuntimeException(sprintf(
                'Expected request to be a %s, %s given',
                Request::class,
                $request::class
            ));
        }

        return Psr7ServerRequest::fromLaminas($request);
    }
}
