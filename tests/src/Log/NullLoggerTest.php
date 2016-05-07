<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Log;

use Room11\Jeeves\Log\NullLogger;
use Room11\Jeeves\Log\BaseLogger;
use Room11\Jeeves\Log\Logger;

class NullLoggerTest extends \PHPUnit_Framework_TestCase
{
    public function testImplementsCorrectInterface()
    {
        $this->assertInstanceof(Logger::class, new NullLogger(0));
    }

    public function testExtendsBaseClass()
    {
        $this->assertInstanceof(BaseLogger::class, new NullLogger(0));
    }

    public function testLog()
    {
        $this->assertNull((new NullLogger(0))->log(0, 'foo'));
    }
}
