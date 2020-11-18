<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace Laminas\Mvc\Middleware;

use Closure;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\Http\Response;
use Laminas\Mvc\Application;
use Laminas\Mvc\Exception\InvalidMiddlewareException;
use Laminas\Mvc\MvcEvent;
use Laminas\Psr7Bridge\Psr7Response;
use Laminas\Stratigility\Middleware\CallableMiddlewareDecorator;
use Laminas\Stratigility\Middleware\RequestHandlerMiddleware;
use Laminas\Stratigility\MiddlewarePipe;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

use function get_class;
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
     * @return null|Response|mixed
     */
    public function onDispatch(MvcEvent $event)
    {
        if (null !== $event->getResult()) {
            return null;
        }

        $routeMatch = $event->getRouteMatch();
        if (! $routeMatch || $routeMatch->getParam('controller') !== PipeSpec::class) {
            return null;
        }

        $middleware = $routeMatch->getParam('middleware', false);
        if (false === $middleware) {
            return null;
        }

        $request        = $event->getRequest();
        /** @var Application $application */
        $application    = $event->getApplication();
        $response       = $application->getResponse();
        $serviceManager = $application->getServiceManager();

        try {
            $pipe = $this->createPipeFromSpec($serviceManager, $middleware);
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
     * @param string|MiddlewareInterface|RequestHandlerInterface|PipeSpec $middlewarePipeSpec
     * @throws InvalidMiddlewareException
     */
    private function createPipeFromSpec(
        ContainerInterface $container,
        $middlewarePipeSpec
    ): RequestHandlerInterface {
        if (is_string($middlewarePipeSpec)) {
            $middlewarePipeSpec = $this->middlewareFromContainer($container, $middlewarePipeSpec);
        }
        if ($middlewarePipeSpec instanceof RequestHandlerInterface) {
            return $middlewarePipeSpec;
        }

        $pipe = new MiddlewarePipe();
        if ($middlewarePipeSpec instanceof MiddlewareInterface) {
            $pipe->pipe($middlewarePipeSpec);
            return $pipe;
        }

        if (! $middlewarePipeSpec instanceof PipeSpec) {
            throw new InvalidMiddlewareException(sprintf(
                'Route match parameter "middleware" must be one of: string container id, %s, %s or %s; %s given',
                MiddlewareInterface::class,
                RequestHandlerInterface::class,
                PipeSpec::class,
                is_object($middlewarePipeSpec) ? get_class($middlewarePipeSpec) : gettype($middlewarePipeSpec)
            ));
        }
        // Pipe has implicit empty pipeline handler
        foreach ($middlewarePipeSpec->getSpec() as $middlewareToBePiped) {
            if (null === $middlewareToBePiped) {
                throw InvalidMiddlewareException::fromNull();
            }

            $middlewareName = is_object($middlewareToBePiped)
                ? get_class($middlewareToBePiped)
                : gettype($middlewareToBePiped);

            if (is_string($middlewareToBePiped)) {
                $middlewareToBePiped = $this->middlewareFromContainer($container, $middlewareToBePiped);
            }

            if ($middlewareToBePiped instanceof Closure) {
                $pipe->pipe(new CallableMiddlewareDecorator($middlewareToBePiped));
                continue;
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
     * @param string $type
     * @param string $middlewareName
     * @param MvcEvent $event
     * @param Application $application
     * @param null|Throwable $exception
     * @return mixed
     */
    protected function marshalInvalidMiddleware(
        string $type,
        string $middlewareName,
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

    /**
     * @return MiddlewareInterface|RequestHandlerInterface
     */
    private function middlewareFromContainer(ContainerInterface $container, string $middlewareName)
    {
        if (! $container->has($middlewareName)) {
            throw InvalidMiddlewareException::fromMiddlewareName($middlewareName);
        }
        $middleware = $container->get($middlewareName);
        if (! $middleware instanceof MiddlewareInterface && ! $middleware instanceof RequestHandlerInterface) {
            throw InvalidMiddlewareException::fromMiddlewareName($middlewareName);
        }
        return $middleware;
    }
}
