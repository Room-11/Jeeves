<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Room11\Jeeves\Plugins\CanIUse;

class CanIUsePluginTest extends AbstractPluginTest
{
    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->plugin = new CanIUse($this->client);
    }

    public function testCommandName()
    {
        $this->assertSame('CanIUse', $this->plugin->getName());
        $this->assertSame([], $this->plugin->getEventHandlers());
        $this->assertSame(null, $this->plugin->getMessageHandler());
    }
    
}
