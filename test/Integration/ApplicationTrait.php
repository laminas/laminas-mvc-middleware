<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 *
 */

namespace ZendTest\Mvc\Middleware\Integration;

use Zend\Mvc\Application;
use Zend\Mvc\Middleware\Module as MiddlewareModule;
use Zend\Router\Module as RouterModule;

trait ApplicationTrait
{
    /**
     * @var Application
     */
    protected $application;
    protected $config;

    protected function setUpApplication()
    {
        $config = [
            'modules' => [
                RouterModule::class,
                MiddlewareModule::class,
            ],
            'module_listener_options' => [
                'config_cache_enabled' => false,
                'use_zend_loader' => false,
            ]

        ];
        $this->application = Application::init($config);
        return $this->application;
    }

    protected function tearDownApplication()
    {
        $this->application = null;
    }
}
