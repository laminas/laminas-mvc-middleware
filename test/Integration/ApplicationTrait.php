<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (https://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace ZendTest\Mvc\Middleware\Integration;

use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\Mvc\SendResponseListener;
use ZendTest\Mvc\Middleware\Integration\TestAsset\NoopSendResponseListener;

trait ApplicationTrait
{
    /**
     * @var Application
     */
    protected $application;

    /**
     * Extra config to use during application set up
     */
    protected $extraConfig = [];

    /**
     * Fail test with exception message if mvc error event is triggered.
     */
    protected $failOnErrorEvents = true;

    protected function setUpApplication()
    {
        $extraConfig = $this->extraConfig;
        $extraConfig['service_manager']['services'][SendResponseListener::class] = new NoopSendResponseListener();
        $config = [
            'modules' => [
                'Zend\Router',
                'Zend\Mvc\Middleware',
            ],
            'module_listener_options' => [
                'config_cache_enabled' => false,
                'extra_config' => $extraConfig,
            ],
        ];
        $this->application = Application::init($config);

        //setup verbose error listeners
        $errorListener = function (MvcEvent $event) {
            if (! $this->failOnErrorEvents) {
                return;
            }
            $exception = $event->getParam('exception');
            $exception = $exception ?: $event->getError();
            $this->fail((string) $exception);
        };
        $this->application
            ->getEventManager()
            ->attach(MvcEvent::EVENT_DISPATCH_ERROR, $errorListener, -10000);
        $this->application
            ->getEventManager()
            ->attach(MvcEvent::EVENT_RENDER_ERROR, $errorListener, -10000);
        return $this->application;
    }

    protected function tearDownApplication()
    {
        $this->application = null;
        $this->failOnErrorEvents = true;
        $this->extraConfig = [];
    }
}
