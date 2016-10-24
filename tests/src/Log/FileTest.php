<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Log;

use Amp\Promise;
use Room11\Jeeves\Log\File;
use Room11\Jeeves\Log\BaseLogger;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Log\Level;

class FileTest extends \PHPUnit_Framework_TestCase
{
    private $logFile;

    public function setUp()
    {
        $this->logFile = __DIR__ . '/../../data/log.txt';

        @unlink($this->logFile);

        file_put_contents($this->logFile, '');
    }

    public function tearDown()
    {
        @unlink($this->logFile);
    }

    public function testImplementsCorrectInterface()
    {
        $this->assertInstanceOf(Logger::class, new File(0, $this->logFile));
    }

    public function testExtendsBaseClass()
    {
        $this->assertInstanceOf(BaseLogger::class, new File(0, $this->logFile));
    }

    public function testLogWithoutMeetingTheLogLevel()
    {
        $result = (new File(Level::ERROR, $this->logFile))->log(Level::DEBUG, 'foo');

        $this->assertInstanceOf(Promise::class, $result);

        \Amp\wait($result);

        $this->assertSame('', file_get_contents($this->logFile));
    }

    public function testLogWithoutExtraData()
    {
        $result = (new File(Level::DEBUG, $this->logFile))->log(Level::DEBUG, 'foo');

        $this->assertInstanceOf(Promise::class, $result);

        \Amp\wait($result);

        $this->assertRegExp(
            '~^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] foo$~',
            file_get_contents($this->logFile)
        );
    }

    public function testLogWithExtraDataWithoutExtraDataLevel()
    {
        $result = (new File(Level::DEBUG, $this->logFile))->log(Level::DEBUG, 'foo', 'bar');

        $this->assertInstanceOf(Promise::class, $result);

        \Amp\wait($result);

        $this->assertRegExp(
            '~^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] foo$~',
            file_get_contents($this->logFile)
        );
    }

    public function testLogWithExtraData()
    {
        $result = (new File(Level::DEBUG | Level::EXTRA_DATA, $this->logFile))->log(Level::DEBUG, 'foo', 'bar');

        $this->assertInstanceOf(Promise::class, $result);

        \Amp\wait($result);

        $logLines = explode("\n", file_get_contents($this->logFile));

        $this->assertSame(3, count($logLines));

        $this->assertRegExp(
            '~^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] foo$~',
            $logLines[0]
        );

        $this->assertRegExp(
            '~^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] "bar"$~',
            $logLines[1]
        );

        $this->assertSame('', $logLines[2]);
    }
}
