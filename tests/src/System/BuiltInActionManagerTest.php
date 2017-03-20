<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Chat;

use Amp\Success;
use Room11\Jeeves\BuiltIn\Commands\Uptime;
use Room11\Jeeves\BuiltIn\EventHandlers\Invite;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Event\Invitation;
use Room11\Jeeves\Chat\Event\EventType;
use Room11\Jeeves\Chat\Room\StatusManager;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Storage\Ban as BanStorage;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\BuiltInCommandInfo;
use Room11\Jeeves\System\BuiltInEventHandler;

class BuiltInActionManagerTest extends \PHPUnit\Framework\TestCase
{
    private $banStorage;
    private $roomStatusManager;
    private $logger;
    private $builtInActionManager;

    public function Setup()
    {
        $this->banStorage = $this->createMock(BanStorage::class);
        $this->roomStatusManager = $this->createMock(StatusManager::class);
        $this->logger = $this->createMock(Logger::class);

        $this->builtInActionManager = new BuiltInActionManager(
            $this->banStorage,
            $this->roomStatusManager,
            $this->logger
        );
    }

    public function testRegisterCommand()
    {
        $this->builtInActionManager->registerCommand(
            new Uptime($this->createMock(ChatClient::class))
        );

        $this->assertTrue($this->builtInActionManager->hasRegisteredCommand('uptime'));

        $this->assertInstanceOf(
            BuiltInCommandInfo::class,
            $this->builtInActionManager->getRegisteredCommandInfo()['uptime']
        );
    }

    public function testHandleEventUnknownEventHandler()
    {
        $event = $this->createMock(Invitation::class);
        $event
            ->method('getTypeId')
            ->will($this->returnValue(123456))
        ;

        $result = \Amp\wait($this->builtInActionManager->handleEvent($event));
        $this->assertNull($result);
    }

    public function testRegisterEventHandlerWithHadleEvent()
    {
        $handler = $this->createMock(Invite::class);
        $event = $this->createMock(Invitation::class);

        $handler
            ->method('getEventTypes')
            ->will($this->returnValue([EventType::INVITATION]))
        ;

        $this->builtInActionManager->registerEventHandler($handler);

        $event
            ->method('getTypeId')
            ->will($this->returnValue($event::TYPE_ID))
        ;

        $handler
            ->expects($this->once())
            ->method('handleEvent')
            ->with($this->identicalTo($event))
            ->will($this->returnValue(new Success()))
        ;

        \Amp\wait($this->builtInActionManager->handleEvent($event));
    }
}
