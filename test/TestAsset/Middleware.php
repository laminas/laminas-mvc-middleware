<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc\Middleware\TestAsset;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ServerRequestInterface;

class Middleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $response = new Response();
        $response->getBody()->write(__CLASS__);
        return $response;
    }
}
