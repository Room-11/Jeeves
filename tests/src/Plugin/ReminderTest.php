<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Command;
use IntervalParser\IntervalParser;
use Room11\Jeeves\Plugins\InvalidReminderTextException;
use Room11\Jeeves\Plugins\Reminder;
use Room11\Jeeves\Storage\File\Admin;
use Room11\Jeeves\Storage\File\KeyValue;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\ChatClient;

class ReminderTest extends AbstractPluginTest
{
    /** @var ChatClient|\PHPUnit_Framework_MockObject_MockObject */
    private $chatClient;

    /** @var Admin|\PHPUnit_Framework_MockObject_MockObject */
    private $adminStorage;

    /** @var KeyValue|\PHPUnit_Framework_MockObject_MockObject */
    private $pluginData;

    /** @var IntervalParser|\PHPUnit_Framework_MockObject_MockObject */
    private $intervalParser;

    /** @var Reminder|\PHPUnit_Framework_MockObject_MockObject */
    protected $plugin;

    protected function setUp()
    {
        parent::setUp();

        $this->intervalParser = $this->createMock(IntervalParser::class);
        $this->adminStorage = $this->createMock(Admin::class);
        $this->chatClient = $this->createMock(ChatClient::class);
        $this->pluginData = $this->createMock(KeyValue::class);

        $this->plugin = new Reminder($this->chatClient, $this->pluginData, $this->adminStorage, $this->intervalParser);
    }

    public function testCommandName()
    {
        $this->assertSame('Reminder', $this->plugin->getName());

        $this->assertSame(
            'Get reminded by an elephpant because, why not?',
            $this->plugin->getDescription()
        );

        $this->assertSame([], $this->plugin->getEventHandlers());

        $endpoints = $this->plugin->getCommandEndpoints();

        $this->assertContainsOnlyInstancesOf(
            PluginCommandEndpoint::class,
            $endpoints,
            'Command endpoints array doesn\'t contain only valid endpoint definitions.'
        );

        $this->assertCount(4, $endpoints, 'Command endpoints array doesn\'t contain exactly 4 endpoint definitions.');
    }

    public function testCommandIn()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->exactly(2))
            ->method('getCommandName')
            ->will($this->returnValue('in'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true));
        $command
            ->expects($this->once())
            ->method("getParameters")
            ->will($this->returnValue(['25', 'seconds', 'foo']));

        $this->intervalParser
            ->expects($this->once())
            ->method("normalizeTimeInterval")
            ->with('25 seconds foo')
            ->will($this->returnValue('25 seconds foo'));

        $this->pluginData
            ->expects($this->once())
            ->method('set')
            ->will($this->returnValue(new Success(true)));

        $command
            ->expects($this->once())
            ->method("getId")
            ->will($this->returnValue(12345678));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                'Reminder 12345678 is set.'
            )
            ->will($this->returnValue(new Success()));

        \Amp\wait($this->plugin->handleCommand($command));
    }

    public function testCommandInReturnsUsageWhenMissingTrailingString()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->exactly(2))
            ->method('getCommandName')
            ->will($this->returnValue('in'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true));
        $command
            ->expects($this->once())
            ->method("getParameters")
            ->will($this->returnValue(['5', 'seconds']));

        $this->intervalParser
            ->expects($this->once())
            ->method("normalizeTimeInterval")
            ->with('5 seconds')
            ->will($this->returnValue('5 seconds'));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                /** @lang text */ "Usage: `!!reminder [ examples | list | <text> [ at <time> | in <delay> ] | unset <id> ]` Try `!!reminder examples`"
            )
            ->will($this->returnValue(new Success()));

        \Amp\wait($this->plugin->handleCommand($command));
    }

    public function testCommandInReturnMessageUponInvalidTime()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->exactly(2))
            ->method('getCommandName')
            ->will($this->returnValue('in'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true));
        $command
            ->expects($this->once())
            ->method("getParameters")
            ->will($this->returnValue(['5', 'beers']));

        $this->intervalParser
            ->expects($this->once())
            ->method("normalizeTimeInterval")
            ->with('5 beers')
            ->will($this->returnValue('5 beers'));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                'Have a look at the time again, yo!'
            )
            ->will($this->returnValue(new Success()));

        \Amp\wait($this->plugin->handleCommand($command));
    }

    public function testCommandAt()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->exactly(2))
            ->method('getCommandName')
            ->will($this->returnValue('at'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true));
        $command
            ->expects($this->once())
            ->method("getParameters")
            ->will($this->returnValue(['16:00', 'foo']));
        $command
            ->expects($this->once())
            ->method("getParameter")
            ->with(0)
            ->will($this->returnValue('16:00'));

        $this->pluginData
            ->expects($this->once())
            ->method('set')
            ->will($this->returnValue(new Success(true)));

        $command
            ->expects($this->once())
            ->method("getId")
            ->will($this->returnValue(12345678));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                'Reminder 12345678 is set.'
            )
            ->will($this->returnValue(new Success()));

        \Amp\wait($this->plugin->handleCommand($command));
    }

    public function testCommandAtReturnsUsageWhenMissingTrailingString()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->exactly(2))
            ->method('getCommandName')
            ->will($this->returnValue('at'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true));
        /*$command
            ->expects($this->once())
            ->method("getParameters")
            ->will($this->returnValue(['16:00']));*/
        $command
            ->expects($this->once())
            ->method("getParameter")
            ->with(0)
            ->will($this->returnValue('16:00'));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                /** @lang text */ "Usage: `!!reminder [ examples | list | <text> [ at <time> | in <delay> ] | unset <id> ]` Try `!!reminder examples`"
            )
            ->will($this->returnValue(new Success()));

        \Amp\wait($this->plugin->handleCommand($command));
    }

    public function testCommandAtReturnMessageUponInvalidTime()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->exactly(2))
            ->method('getCommandName')
            ->will($this->returnValue('at'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true));
        $command
            ->expects($this->once())
            ->method("getParameter")
            ->with(0)
            ->will($this->returnValue('76:00'));
        $command
            ->expects($this->once())
            ->method("getParameters")
            ->will($this->returnValue(['76:00', 'foo']));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                'Have a look at the time again, yo!'
            )
            ->will($this->returnValue(new Success()));

        \Amp\wait($this->plugin->handleCommand($command));
    }

    public function testCommandReminderWithoutParametersRemindsUsage()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->once())
            ->method('getCommandName')
            ->will($this->returnValue('reminder'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(false));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                /** @lang text */ "Usage: `!!reminder [ examples | list | <text> [ at <time> | in <delay> ] | unset <id> ]` Try `!!reminder examples`"
            );

        $this->assertInstanceOf(Promise::class, $this->plugin->handleCommand($command));
    }

    public function testCommandReminderListWhenNoReminders()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->once())
            ->method('getCommandName')
            ->will($this->returnValue('reminder'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true));
        $command
            ->expects($this->once())
            ->method("getParameters")
            ->will($this->returnValue(['list']));

        $this->chatClient
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                "There aren't any scheduled reminders."
            );

        $this->assertInstanceOf(Promise::class, $this->plugin->handleCommand($command));
    }

    public function testCommandRemindersWhenNoReminders()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->once())
            ->method('getCommandName')
            ->will($this->returnValue('reminders'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(false));

        $this->chatClient
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                "There aren't any scheduled reminders."
            );

        $this->assertInstanceOf(Promise::class, $this->plugin->handleCommand($command));
    }

    public function testCommandReminderExamples()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->once())
            ->method('getCommandName')
            ->will($this->returnValue('reminder'));
        $command
            ->method('hasParameters')
            ->will($this->returnValue(true));
        $command
            ->method("getParameters")
            ->will($this->returnValue(['examples']));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                $this->matchesRegularExpression('/Examples:/')
            );

        $this->assertInstanceOf(Promise::class, $this->plugin->handleCommand($command));
    }

    public function testCommandReminder()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->exactly(2))
            ->method('getCommandName')
            ->will($this->returnValue('reminder'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true));
        $command
            ->expects($this->exactly(2))
            ->method("getParameters")
            ->will($this->returnValue(['foo', 'in', '25', 'seconds']));

        $this->intervalParser
            ->expects($this->once())
            ->method("normalizeTimeInterval")
            ->with('25 seconds')
            ->will($this->returnValue('25 seconds'));

        $this->pluginData
            ->expects($this->once())
            ->method('set')
            ->will($this->returnValue(new Success(true)));

        $command
            ->expects($this->once())
            ->method("getId")
            ->will($this->returnValue(12345678));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                'Reminder 12345678 is set.'
            )
            ->will($this->returnValue(new Success()));

        \Amp\wait($this->plugin->handleCommand($command));
    }

    public function testCommandReminderReturnsUsageWhenMissingLeadingString()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->exactly(2))
            ->method('getCommandName')
            ->will($this->returnValue('reminder'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true));
        $command
            ->expects($this->exactly(2))
            ->method("getParameters")
            ->will($this->returnValue(['in', '5', 'seconds']));

        $this->intervalParser
            ->expects($this->once())
            ->method("normalizeTimeInterval")
            ->with('5 seconds')
            ->will($this->returnValue('5 seconds'));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                /** @lang text */ "Usage: `!!reminder [ examples | list | <text> [ at <time> | in <delay> ] | unset <id> ]` Try `!!reminder examples`"
            )
            ->will($this->returnValue(new Success()));

        \Amp\wait($this->plugin->handleCommand($command));
    }

    public function testCommandReminderReturnMessageUponInvalidTime()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->exactly(2))
            ->method('getCommandName')
            ->will($this->returnValue('reminder'));
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true));
        $command
            ->expects($this->exactly(2))
            ->method("getParameters")
            ->will($this->returnValue(['foo', 'in', '5', 'beers']));

        $this->intervalParser
            ->expects($this->once())
            ->method("normalizeTimeInterval")
            ->with('5 beers')
            ->will($this->returnValue('5 beers'));

        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                'Have a look at the time again, yo!'
            )
            ->will($this->returnValue(new Success()));

        \Amp\wait($this->plugin->handleCommand($command));
    }

    public function testCommandReminderList()
    {
        $command = $this->createMock(Command::class);
        $command
            ->expects($this->once())
            ->method('getCommandName')
            ->will($this->returnValue('reminder'))
        ;
        $command
            ->expects($this->once())
            ->method('hasParameters')
            ->will($this->returnValue(true))
        ;
        $command
            ->expects($this->once())
            ->method('getParameters')
            ->will($this->returnValue(['list']))
        ;
        $this->pluginData
            ->expects($this->once())
            ->method('getAll')
            ->will($this->returnValue(new Success([
                [
                    'id' => 12345678,
                    'userId' => 2852427,
                    'username' => 'Ekin',
                    'text' => 'foo',
                    'delay' => '25 seconds',
                    'timestamp' => strtotime('+25 seconds')
                ]
            ])))
        ;
        $this->chatClient
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->isInstanceOf(Command::class),
                $this->matchesRegularExpression('/Registered reminders are:\s.+Set by Ekin/')
            )
            ->will($this->returnValue(new Success()))
        ;

        \Amp\wait($this->plugin->handleCommand($command));
    }
}
