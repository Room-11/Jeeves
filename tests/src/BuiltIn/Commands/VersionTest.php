<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\BuiltIn\Commands;

use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Version;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\StackChat\Client\ChatClient;
use function Room11\Jeeves\get_current_version;

class VersionTest extends AbstractCommandTest
{
    /** @var Version|\PHPUnit_Framework_MockObject_MockObject */
    private $builtIn;

    /** @var ChatClient|\PHPUnit_Framework_MockObject_MockObject */
    private $client;

    /** @var Command|\PHPUnit_Framework_MockObject_MockObject */
    private $command;

    public function setUp()
    {
        parent::setUp();

        $this->client = $this->createMock(ChatClient::class);
        $this->builtIn = new Version($this->client);
        $this->command = $this->createMock(Command::class);
    }

    public function testVersionCommand()
    {
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

    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
    }
}
