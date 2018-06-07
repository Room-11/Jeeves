<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Room11\Jeeves\Plugins\Rebecca;

class RebeccaTest extends AbstractPluginTest
{
    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->plugin = new Rebecca($this->client);
    }

    public function testCommandName()
    {
        $this->assertSame('Rebecca', $this->plugin->getName());
        $this->assertSame([], $this->plugin->getEventHandlers());
        $this->assertNull($this->plugin->getMessageHandler());
    }
}
