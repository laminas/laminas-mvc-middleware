<?php

/**
 * @see       https://github.com/laminas/laminas-mvc-middleware for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mvc-middleware/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mvc-middleware/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mvc\Middleware;

use Laminas\Mvc\Middleware\Module;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Laminas\Mvc\Middleware\Module
 */
class ModuleTest extends TestCase
{
    /**
     * @var Module
     */
    private $module;

    public function setUp()
    {
        $this->module = new Module();
    }

    public function testGetConfigReturnsArray()
    {
        $config = $this->module->getConfig();
        $this->assertInternalType('array', $config);
        return $config;
    }

    /**
     * @depends testGetConfigReturnsArray
     */
    public function testReturnedArrayContainsDependencies(array $config)
    {
        $this->assertArrayHasKey('service_manager', $config);
        $this->assertInternalType('array', $config['service_manager']);
    }
}
