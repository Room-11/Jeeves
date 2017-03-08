<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Log;

use Amp\Promise;
use Room11\Jeeves\Log\NullLogger;
use Room11\Jeeves\Log\BaseLogger;
use Room11\Jeeves\Log\Logger;

class NullLoggerTest extends \PHPUnit\Framework\TestCase
{
    public function testImplementsCorrectInterface()
    {
        $this->assertInstanceOf(Logger::class, new NullLogger(0));
    }

    public function testExtendsBaseClass()
    {
        $this->assertInstanceOf(BaseLogger::class, new NullLogger(0));
    }

    public function testLog()
    {
        $this->assertInstanceOf(Promise::class, (new NullLogger(0))->log(0, 'foo'));
    }
}
