<?php declare(strict_types = 1);
 
namespace Room11\Jeeves\Tests\BuiltIn\Commands;
 
use Amp\Success;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Chat\Message\Command;
 
abstract class AbstractCommandTest extends \PHPUnit\Framework\TestCase
{
    protected function setReturnValue($mock, string $method, $value, int $expectedCalls = null)
    {
        $mock
            ->expects(
                $expectedCalls ? $this->exactly($expectedCalls) : $this->any()
            )
            ->method($method)
            ->will($this->returnValue($value))
        ;
    }
}
