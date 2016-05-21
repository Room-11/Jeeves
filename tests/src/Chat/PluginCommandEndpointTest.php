<?php declare(strict_types=1);

namespace Room11\Jeeves\Test\Chat;

use Room11\Jeeves\Chat\PluginCommandEndpoint;

class PluginCommandEndpointTest extends \PHPUnit_Framework_TestCase
{
    private $pluginCommandEndpoint;

    public function setUp()
    {
        $this->pluginCommandEndpoint = new PluginCommandEndpoint('testPlugin', function() {
            return 'testCallback';
        }, 'test', 'Test Description');
    }

    public function testGetCallback()
    {
        $this->assertSame('testCallback', $this->pluginCommandEndpoint->getCallback()());
    }

    public function testGetDefaultCommand()
    {
        $this->assertSame('test', $this->pluginCommandEndpoint->getDefaultCommand());
    }

    public function testGetDefaultCommandWithoutDefault()
    {
        $pluginCommandEndpoint = new PluginCommandEndpoint('testPlugin', function() {
            return 'testCallback';
        });

        $this->assertNull($pluginCommandEndpoint->getDefaultCommand());
    }

    public function testGetDefaultCommandWithoutDefaultExplicitNull()
    {
        $pluginCommandEndpoint = new PluginCommandEndpoint('testPlugin', function() {
            return 'testCallback';
        }, null);

        $this->assertNull($pluginCommandEndpoint->getDefaultCommand());
    }

    public function testGetDescription()
    {
        $this->assertSame('Test Description', $this->pluginCommandEndpoint->getDescription());
    }

    public function testGetName()
    {
        $this->assertSame('testPlugin', $this->pluginCommandEndpoint->getName());
    }
}
