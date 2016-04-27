<?php declare(strict_types=1);

namespace Room11\Jeeves\Tests\OpenId;

use Room11\Jeeves\OpenId\EmailAddress;
use Room11\Jeeves\OpenId\InvalidEmailAddressException;

class EmailAddressTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructorThrowsInInvalidEmailAddress() {
        $this->setExpectedException(InvalidEmailAddressException::class);
        
        new EmailAddress("invalidemailaddress");
    }

    public function testToString() {
        $this->assertSame("peehaa@example.com", (string) new EmailAddress("peehaa@example.com"));
    }
}
