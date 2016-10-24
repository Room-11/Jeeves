<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Log;

use Amp\Promise;
use Room11\Jeeves\Log\BaseLogger;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Log\Level;

class BaseLoggerTest extends \PHPUnit_Framework_TestCase
{
    private $logger;

    public function setUp()
    {
        $this->logger = new class(Level::EVENT) extends BaseLogger {
            public function log(int $level, string $message, $extraData = null): Promise { }

            public function testMeetsLogLevel(int $messageLogLevel)
            {
                return $this->meetsLogLevel($messageLogLevel);
            }
        };
    }

    public function testImplementsCorrectInterface()
    {
        $this->assertInstanceOf(Logger::class, $this->logger);
    }

    public function testMeetsLogLevelDoesntMeet()
    {
        $this->assertFalse($this->logger->testMeetsLoglevel(Level::ERROR));
    }

    public function testMeetsLogLevelMeets()
    {
        $this->assertTrue($this->logger->testMeetsLoglevel(Level::EVENT));
    }
}
