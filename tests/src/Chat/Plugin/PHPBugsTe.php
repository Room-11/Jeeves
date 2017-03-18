<?php

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Amp\Artax\HttpClient;
use Amp\Artax\Response;
use Amp\Success;
use function Amp\wait;
use PHPUnit\Framework\TestCase;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Plugins\PHPBugs;
use Room11\Jeeves\Storage\KeyValue;

class PHPBugsTest extends TestCase {
    public function testRecentBugsReturnsFalseOnRequestFailure()
    {
        $chatClient = $this->getMockBuilder(ChatClient::class)->getMock();
        $httpClient = $this->getMockBuilder(HttpClient::class)->getMock();
        $pluginData = $this->getMockBuilder(KeyValue::class)->getMock();
        $response = $this->getMockBuilder(Response::class)->getMock();

        $response->method("getStatus")->willReturn(500);
        $httpClient->method("request")->willReturn(new Success($response));

        $plugin = new PHPBugs($chatClient, $httpClient, $pluginData);

        $reflectionMethod = new \ReflectionMethod($plugin, "getRecentBugs");
        $reflectionMethod->setAccessible(true);

        $this->assertFalse(wait($reflectionMethod->invoke($plugin)));
    }
}