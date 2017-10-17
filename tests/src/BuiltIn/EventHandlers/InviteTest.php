<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\BuiltIn\EventHandlers;

use Amp\Success;
use Psr\Log\LoggerInterface;
use Room11\Jeeves\BuiltIn\EventHandlers\Invite;
use Room11\Jeeves\Chat\PresenceManager;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Event\EventType;
use Room11\StackChat\Event\Invitation;
use Room11\StackChat\Room\Room;

class InviteTest extends \PHPUnit\Framework\TestCase
{
    /** @var Invite|\PHPUnit_Framework_MockObject_MockObject */
    private $event;

    /** @var PresenceManager|\PHPUnit_Framework_MockObject_MockObject */
    private $presenceManager;

    /** @var ChatClient|\PHPUnit_Framework_MockObject_MockObject */
    private $client;

    public function setUp()
    {
        parent::setUp();

        $this->presenceManager = $this->createMock(PresenceManager::class);
        $this->client = $this->createMock(ChatClient::class);

        $this->event = new Invite(
            $this->client,
            $this->presenceManager,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testEventHandling()
    {
        $event = $this->createMock(Invitation::class);

        $event->method('getUserId')->will($this->returnValue(123));
        $event->method('getUserName')->will($this->returnValue('Jeeves'));
        $event->method('getRoomId')->will($this->returnValue(11));
        $event->method('getHost')->will($this->returnValue('someHost'));

        $this->presenceManager
            ->expects($this->once())
            ->method('addRoom')
            ->with(
                $this->callback(function($room) {
                    return (new Room(11, 'someHost'))->equals($room);
                }),
                $this->identicalTo(123)
            )
            ->will($this->returnValue(new Success()))
        ;

        \Amp\wait($this->event->handleEvent($event));
    }

    public function testGetEventTypes()
    {
        $this->assertEquals(
            [EventType::INVITATION],
            $this->event->getEventTypes()
        );
    }
}
