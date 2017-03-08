<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat\BuiltIn;

use Amp\Success;
use AsyncInterop\Loop;
use Room11\Jeeves\BuiltIn\Commands\Uptime;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\System\BuiltInCommandInfo;

class UptimeTest extends AbstractBuiltInTest
{
    const VALID_UPTIME_EXP = "\d \bsecond(s)?\b|\d \bminute(s)?\b|\d \bday(s)?\b|\d \bhour(s)?\b";

    private $command; 
    private $room;

    public function setUp()
    {
        parent::setUp();

        if (!defined('Room11\\Jeeves\\PROCESS_START_TIME')) {
            define('Room11\\Jeeves\\PROCESS_START_TIME', time());
        }

        $this->builtIn = new Uptime($this->client);

        $this->command = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->room = $this->getMockBuilder(Room::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
    }

    public function testUptimeCommand()
    {
        $this->room
            ->expects($this->exactly(5000))
            ->method('isApproved')
            ->will($this->returnValue(new Success(true)));

        $this->client
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->identicalTo($this->command),
                $this->matchesRegularExpression(self::VALID_UPTIME_EXP)
            );

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testUnApprovedUptimeCommand()
    {
        $this->room
            ->expects($this->once())
            ->method('isApproved')
            ->will($this->returnValue(false));

        $response = yield $this->builtIn->handleCommand($this->command);

        $this->assertNull($response);
    }
}
