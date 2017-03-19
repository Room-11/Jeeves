<?php declare(strict_types = 1);
 
namespace Room11\Jeeves\Tests\BuiltIn\Commands;
 
abstract class AbstractCommandTest extends \PHPUnit\Framework\TestCase
{
    protected function setReturnValue(\PHPUnit_Framework_MockObject_MockObject $mock, string $method, $value, int $expectedCalls = null)
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
