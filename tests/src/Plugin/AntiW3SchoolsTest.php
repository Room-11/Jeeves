<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Amp\Success;
use Room11\Jeeves\Plugins\AntiW3Schools;
use Room11\StackChat\Entities\ChatMessage;

class AntiW3SchoolsTest extends AbstractPluginTest
{
    public function testCommandName()
    {
        $this->assertSame('AntiW3Schools', $this->plugin->getName());
        $this->assertSame(
            'A small monitoring plugin which bothers people when a w3schools link is detected.',
            $this->plugin->getDescription()
        );
        $this->assertSame([], $this->plugin->getEventHandlers());
        $this->assertSame([], $this->plugin->getCommandEndpoints());
    }

    public function testValidMessageHandler()
    {
        $result = $this->plugin->getMessageHandler();

        $this->assertTrue(is_callable($result), 'Message handler expected to be callable, but wasn\'t.');
    }

    public function testNoMentionOfW3Schools()
    {
        /** @var AntiW3Schools $plugin */
        $plugin = $this->plugin;

        $message = $this->getMockBuilder(ChatMessage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $message
            ->expects($this->once())
            ->method('getText')
            ->will($this->returnValue('Happy Friday!'));

        $result = $plugin->handleMessage($message);
        $this->assertInstanceOf(Success::class, $result);
    }

    public function testBasicW3SchoolsMention()
    {
        /** @var AntiW3Schools $plugin */
        $plugin = $this->plugin;
        $client = $this->client;

        $message = $this->getMockBuilder(ChatMessage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $message
            ->expects($this->exactly(2))
            ->method('getText')
            ->will($this->returnValue('What\'s wrong with w3schools.com?'));

        $client
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->identicalTo($message),
                $this->equalTo('W3Schools should not be trusted as a reliable resource. [Click here to read why](http://www.w3fools.com/).')
            );

        $plugin->handleMessage($message);

    }

    public function testSingleCategoryW3SchoolsMention()
    {
        /** @var AntiW3Schools $plugin */
        $plugin = $this->plugin;
        $client = $this->client;

        $message = $this->getMockBuilder(ChatMessage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $message
            ->expects($this->exactly(2))
            ->method('getText')
            ->will($this->returnValue('How do I nest HTML elements like in http://www.w3schools.com/html/html_elements.asp?'));

        $client
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->identicalTo($message),
                $this->equalTo(
                    'W3Schools should not be trusted as a reliable resource. [Click here to read why](http://www.w3fools.com/).'
                    . ' [Check the Mozilla Developer Network HTML documentation](https://developer.mozilla.org/docs/Web/HTML) for help with HTML.'
                )
            );

        $plugin->handleMessage($message);
    }

    public function testMultipleCategoryW3SchoolsMentions()
    {
        /** @var AntiW3Schools $plugin */
        $plugin = $this->plugin;
        $client = $this->client;

        $message = $this->getMockBuilder(ChatMessage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $message
            ->expects($this->exactly(2))
            ->method('getText')
            ->will($this->returnValue('How do I nest HTML elements like in w3schools.com/html/html_elements.asp and styled like w3schools.com/css/fake_page.asp?'));

        $client
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->identicalTo($message),
                $this->equalTo(
                    'W3Schools should not be trusted as a reliable resource. [Click here to read why](http://www.w3fools.com/).'
                    . ' [Check the Mozilla Developer Network HTML documentation](https://developer.mozilla.org/docs/Web/HTML) for help with HTML.'
                    . ' [Check the Mozilla Developer Network CSS documentation](https://developer.mozilla.org/docs/Web/CSS) for help with CSS.'
                )
            );

        $plugin->handleMessage($message);
    }

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->plugin = new AntiW3Schools($this->client);
    }
}
