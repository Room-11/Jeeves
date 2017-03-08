<?php declare(strict_types=1);

namespace Room11\Jeeves\Test\Chat\Event;

use Room11\Jeeves\Chat\Event\BaseEvent;
use Room11\Jeeves\Chat\Event\Event;

class BaseEventTest extends \PHPUnit\Framework\TestCase
{
    /** @var BaseEvent */
    private $event;

    public function setUp()
    {
        $this->event = new class(['id' => 2, 'time_stamp' => 1005], 'testdomain.tld') extends BaseEvent {
            public function __construct(array $data, string $host) {
                parent::__construct($data, $host);
            }
        };
    }

    public function testImplementsCorrectInterface()
    {
        $this->assertInstanceOf(Event::class, $this->event);
    }

    public function testGetTypeId()
    {
        $this->assertSame(0, $this->event->getTypeId());
    }

    public function testGetId()
    {
        $this->assertSame(2, $this->event->getId());
    }

    public function testGetHost()
    {
        $this->assertSame('testdomain.tld', $this->event->getHost());
    }

    public function testGetTimestamp()
    {
        $timestamp = $this->event->getTimestamp();

        $this->assertInstanceOf(\DateTimeImmutable::class, $timestamp);
        $this->assertSame('1970-01-01 00:16:45', $timestamp->format('Y-m-d H:i:s'));
    }
}
