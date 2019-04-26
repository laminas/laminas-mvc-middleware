<?php
/**
 * @see       https://github.com/zendframework/zend-mvc-middleware for the canonical source repository
 * @copyright Copyright (c) 2019 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-mvc-middleware/blob/master/LICENSE.md New BSD License
 *
 */

namespace ZendTest\Mvc\Middleware;

use PHPUnit\Framework\TestCase;
use Zend\Mvc\Middleware\Module;

/**
 * @covers \Zend\Mvc\Middleware\Module
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
