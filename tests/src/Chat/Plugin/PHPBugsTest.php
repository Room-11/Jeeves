<?php

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response;
use Amp\Success;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Plugins\PHPBugs;
use Room11\Jeeves\Storage\KeyValue;
use function Amp\resolve;
use function Amp\wait;

class PHPBugsTest extends AbstractPluginTest
{
    /** @var ChatClient */
    private $chatClient;

    /** @var HttpClient */
    private $httpClient;

    /** @var KeyValue */
    private $pluginData;

    protected function setUp()
    {
        parent::setUp();

        $this->chatClient = $this->getMockBuilder(ChatClient::class)->disableOriginalConstructor()->getMock();
        $this->httpClient = $this->getMockBuilder(HttpClient::class)->disableOriginalConstructor()->getMock();
        $this->pluginData = $this->getMockBuilder(KeyValue::class)->disableOriginalConstructor()->getMock();

        $this->plugin = new PHPBugs($this->chatClient, $this->httpClient, $this->pluginData);
    }

    public function testCommandName()
    {
        $this->assertSame('PHPBugs', $this->plugin->getName());
        $this->assertSame(
            'Pushes new PHP.net bugs.',
            $this->plugin->getDescription()
        );
        $this->assertSame([], $this->plugin->getEventHandlers());
        $this->assertSame([], $this->plugin->getCommandEndpoints());
    }

    public function testRecentBugsReturnsFalseOnRequestFailure()
    {
        $response = $this->getMockBuilder(Response::class)->getMock();

        $response->method("getStatus")->willReturn(500);
        $this->httpClient->method("request")->willReturn(new Success($response));

        $reflectionMethod = new \ReflectionMethod($this->plugin, "getRecentBugs");
        $reflectionMethod->setAccessible(true);

        $this->assertFalse(wait(resolve($reflectionMethod->invoke($this->plugin))));
    }
}