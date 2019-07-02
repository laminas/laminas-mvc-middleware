<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Mvc\Middleware;

use Zend\Mvc\MiddlewareListener as DeprecatedMiddlewareListener;
use Zend\ServiceManager\Factory\InvokableFactory;

class Module
{
    /**
     * @return array
     */
    public function getConfig()
    {
        return [
            'service_manager' => [
                'aliases' => [
                    DeprecatedMiddlewareListener::class => MiddlewareListener::class,
                ],
                'factories' => [
                    MiddlewareListener::class => InvokableFactory::class,
                ],
            ],
        ];
    }
}
