<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Mvc\Middleware;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Response;
use Laminas\Mvc\Application;
use Laminas\Mvc\Exception\InvalidMiddlewareException;
use Laminas\Mvc\MvcEvent;
use Laminas\Psr7Bridge\Psr7Response;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

use function gettype;
use function is_object;
use function is_string;

class MiddlewareListener extends AbstractListenerAggregate
{
    /**
     * Attach listeners to an event manager
     *
     * @return void
     */
    public function attach(EventManagerInterface $events, $priority = 1) : void
    {
        $this->listeners[] = $events->attach(MvcEvent::EVENT_DISPATCH, [$this, 'onDispatch'], 1);
    }

    /**
     * Listen to the "dispatch" event
     *
     * @return null|Response
     */
    public function onDispatch(MvcEvent $event)
    {
        if (null !== $event->getResult()) {
            return null;
        }

        $routeMatch = $event->getRouteMatch();
        $middleware = $routeMatch->getParam('middleware', false);
        if (false === $middleware) {
            return null;
        }

        $request        = $event->getRequest();
        $application    = $event->getApplication();
        $response       = $application->getResponse();
        $serviceManager = $application->getServiceManager();

        try {
            $pipe = $this->createPipeFromSpec(
                $serviceManager,
                is_array($middleware) ? $middleware : [$middleware]
            );
        } catch (InvalidMiddlewareException $invalidMiddlewareException) {
            $return = $this->marshalInvalidMiddleware(
                Application::ERROR_MIDDLEWARE_CANNOT_DISPATCH,
                $invalidMiddlewareException->toMiddlewareName(),
                $event,
                $application,
                $invalidMiddlewareException
            );
            $event->setResult($return);
            return $return;
        }

        $caughtException = null;
        try {
            $return = (new MiddlewareController(
                $pipe,
                $application->getServiceManager()->get('EventManager'),
                $event
            ))->dispatch($request, $response);
        } catch (Throwable $exception) {
            $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
            $event->setError(Application::ERROR_EXCEPTION);
            $event->setParam('exception', $exception);

            $events  = $application->getEventManager();
            $results = $events->triggerEvent($event);
            $return  = $results->last();
            if (! $return) {
                $return = $event->getResult();
            }
        }

        $event->setError('');

        if (! $return instanceof ResponseInterface) {
            $event->setResult($return);
            return $return;
        }
        $response = Psr7Response::toLaminas($return);
        $event->setResult($response);
        return $response;
    }

    /**
     * Create a middleware pipe from the array spec given.
     *
     * @param mixed[] $middlewarePipeSpec
     *      List of string ids for middleware in container, instances of MiddlewareInterface
     *      or RequestHandlerInterface
     * @throws InvalidMiddlewareException
     */
    private function createPipeFromSpec(
        ContainerInterface $serviceLocator,
        array $middlewarePipeSpec
    ): RequestHandlerInterface {
        // Pipe has implicit empty pipeline handler
        $pipe = new MiddlewarePipe();
        foreach ($middlewarePipeSpec as $middlewareToBePiped) {
            if (null === $middlewareToBePiped) {
                throw InvalidMiddlewareException::fromNull();
            }

            $middlewareName = is_object($middlewareToBePiped)
                ? get_class($middlewareToBePiped)
                : gettype($middlewareToBePiped);

            if (is_string($middlewareToBePiped)) {
                $middlewareName = $middlewareToBePiped;
                if (! $serviceLocator->has($middlewareToBePiped)) {
                    // throw separately for stacktrace line hint
                    throw InvalidMiddlewareException::fromMiddlewareName($middlewareName);
                }
                $middlewareToBePiped = $serviceLocator->get($middlewareToBePiped);
            }

            if ($middlewareToBePiped instanceof MiddlewareInterface) {
                $pipe->pipe($middlewareToBePiped);
                continue;
            }
            if ($middlewareToBePiped instanceof RequestHandlerInterface) {
                $middlewareToBePiped = new RequestHandlerMiddleware($middlewareToBePiped);
                $pipe->pipe($middlewareToBePiped);
                break; // request handler will always stop pipe processing. Rest of the pipe can be ignored
            }

            throw InvalidMiddlewareException::fromMiddlewareName($middlewareName);
        }
        return $pipe;
    }

    /**
     * Marshal a middleware not callable exception event
     *
     * @param  string $type
     * @param  string $middlewareName
     * @return mixed
     */
    protected function marshalInvalidMiddleware(
        $type,
        $middlewareName,
        MvcEvent $event,
        Application $application,
        Throwable $exception = null
    ) {
        $event->setName(MvcEvent::EVENT_DISPATCH_ERROR);
        $event->setError($type);
        $event->setController($middlewareName);
        $event->setControllerClass('Middleware not callable: ' . $middlewareName);
        if ($exception !== null) {
            $event->setParam('exception', $exception);
        }

        $events  = $application->getEventManager();
        $results = $events->triggerEvent($event);
        $return  = $results->last();
        if (! $return) {
            $return = $event->getResult();
        }
        return $return;
    }
}
