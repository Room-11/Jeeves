<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\OpenId;

use Room11\Jeeves\OpenId\Password;

class PasswordTest extends \PHPUnit_Framework_TestCase
{
    public function testToString() {
        $this->assertSame("mytotallyawesomepassword", (string) new Password("mytotallyawesomepassword"));
    }
}
