<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\BuiltIn\EventHandlers;

use Amp\Success;
use Room11\Jeeves\BuiltIn\EventHandlers\Invite;
use Room11\Jeeves\Chat\Event\Invitation;
use Room11\Jeeves\Chat\Event\EventType;
use Room11\Jeeves\Chat\Room\Identifier;
use Room11\Jeeves\Chat\Room\IdentifierFactory;
use Room11\Jeeves\Chat\Room\PresenceManager;
use Room11\Jeeves\Log\Logger;

class InviteTest extends \PHPUnit\Framework\TestCase
{
    private $event;
    private $identifierFactory;
    private $presenceManager;

    public function setUp()
    {
        parent::setUp();

        $this->identifierFactory = $this->createMock(IdentifierFactory::class);
        $this->presenceManager = $this->createMock(PresenceManager::class);

        $this->event = new Invite(
            $this->identifierFactory,
            $this->presenceManager,
            $this->createMock(Logger::class)
        );
    }

    public function testEventHandling()
    {
        $event = $this->createMock(Invitation::class);
        $identifier = $this->createMock(Identifier::class);

        $event->method('getUserId')->will($this->returnValue(123));
        $event->method('getUserName')->will($this->returnValue('Jeeves'));
        $event->method('getRoomId')->will($this->returnValue(11));
        $event->method('getHost')->will($this->returnValue('someHost'));

        $this->identifierFactory
            ->expects($this->once())
            ->method('create')
            ->with(
                $this->identicalTo(11),
                $this->identicalTo('someHost')
            )
            ->will($this->returnValue($identifier))
        ;

        $this->presenceManager
            ->expects($this->once())
            ->method('addRoom')
            ->with(
                $this->identicalTo($identifier),
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
