<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\Log;

use Amp\Promise;
use Psr\Log\LoggerInterface;
use Room11\Jeeves\Log\BaseLogger;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\StdOut;

class StdOutTest extends \PHPUnit\Framework\TestCase
{
    private function tellXDebugToFuckOff()
    {
        if (extension_loaded('xdebug')) {
            ini_set('xdebug.overload_var_dump', '0');
        }
    }

    public function testImplementsCorrectInterface()
    {
        $this->assertInstanceOf(LoggerInterface::class, new StdOut(0));
    }

    public function testExtendsBaseClass()
    {
        $this->assertInstanceOf(BaseLogger::class, new StdOut(0));
    }

    public function testLogWithoutMeetingTheLoglevel()
    {
        $this->assertInstanceOf(Promise::class, (new StdOut(Level::ERROR))->log(Level::DEBUG, 'foo'));
    }

    public function testLogWithoutExtraData()
    {
        ob_start();

        (new StdOut(Level::DEBUG))->log(Level::DEBUG, 'foo');

        $this->assertRegExp(
            '~^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] foo$~',
            ob_get_clean()
        );
    }

    public function testLogWithoutExtraDataNoTimestamp()
    {
        ob_start();

        (new StdOut(Level::DEBUG, false))->log(Level::DEBUG, 'foo');

        $this->assertSame("foo\n", ob_get_clean());
    }

    public function testLogWithExtraDataWithoutExtraDataLevel()
    {
        ob_start();

        (new StdOut(Level::DEBUG))->log(Level::DEBUG, 'foo', ['bar']);

        $this->assertRegExp(
            '~^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] foo$~',
            ob_get_clean()
        );
    }

    public function testLogWithExtraDataWithoutExtraDataLevelNoTimestamp()
    {
        ob_start();

        (new StdOut(Level::DEBUG, false))->log(Level::DEBUG, 'foo', ['bar']);

        $this->assertSame("foo\n", ob_get_clean());
    }

    public function testLogWithExtraData()
    {
        $this->tellXDebugToFuckOff();

        ob_start();

        (new StdOut(Level::DEBUG | Level::CONTEXT))->log(Level::DEBUG, 'foo', ['bar']);

        $logLines = explode("\n", ob_get_clean());

        $this->assertCount(3, $logLines);

        $this->assertRegExp(
            '~^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] foo$~',
            $logLines[0]
        );

        $this->assertSame('0: string(3) "bar"', $logLines[1]);

        $this->assertSame('', $logLines[2]);
    }

    public function testLogWithExtraDataNoTimestamp()
    {
        $this->tellXDebugToFuckOff();

        ob_start();

        (new StdOut(Level::DEBUG | Level::CONTEXT, false))->log(Level::DEBUG, 'foo', ['bar']);

        $logLines = explode("\n", ob_get_clean());

        $this->assertCount(3, $logLines);

        $this->assertSame('foo', $logLines[0]);

        $this->assertSame('0: string(3) "bar"', $logLines[1]);

        $this->assertSame('', $logLines[2]);
    }
}
