<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\BuiltIn\Commands;

use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Version;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\System\BuiltInCommandInfo;
use function Room11\Jeeves\get_current_version;

require __DIR__ . '/../../../../version.php';

define('Room11\\Jeeves\\APP_BASE', realpath(__DIR__ . '/../../../../'));
define('Room11\\Jeeves\\GITHUB_PROJECT_URL', 'https://github.com/Room-11/Jeeves');

class VersionTest extends AbstractCommandTest
{
    private $builtIn;
    private $client;
    private $command;
    private $room;

    public function setUp()
    {
        parent::setUp();

        $this->client = $this->createMock(ChatClient::class);
        $this->builtIn = new Version($this->client);
        $this->command = $this->createMock(Command::class);
        $this->room = $this->createMock(Room::class);

        $this->setReturnValue($this->command, 'getRoom', $this->room);
    }

    public function testVersionCommand()
    {
        $this->setReturnValue($this->room, 'isApproved', new Success(true));
        $version = get_current_version();

        $this->client
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->identicalTo($this->command),
                $this->identicalTo(
                    sprintf(
                        "[%s](%s)",
                        $version->getVersionString(),
                        $version->getGithubUrl()
                    )
                )
            )
            ->will($this->returnValue(new Success(true)))
        ;

        \Amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandWithoutApproval()
    {
        $this->setReturnValue($this->room, 'isApproved', new Success(false));
        $response = \Amp\wait($this->builtIn->handleCommand($this->command));

        $this->assertNull($response);
    }

    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
    }
}
