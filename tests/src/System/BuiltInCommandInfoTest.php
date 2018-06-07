<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat;

use Room11\Jeeves\System\BuiltInCommandInfo;

class BuiltInCommandInfoTest extends \PHPUnit\Framework\TestCase
{
    public function testGetCommand()
    {
        $command = 'foo';
        $description = 'foo command';

        $commandInfo = new BuiltInCommandInfo($command, $description);

        $this->assertSame($command, $commandInfo->getCommand());
    }

    public function testGetDescription()
    {
        $command = 'foo';
        $description = 'foo command';

        $commandInfo = new BuiltInCommandInfo($command, $description);

        $this->assertSame($description, $commandInfo->getDescription());
    }

    public function testRequiresAdminUserDefaultNotSet()
    {
        $command = 'foo';
        $description = 'foo command';

        $commandInfo = new BuiltInCommandInfo($command, $description);

        $this->assertFalse($commandInfo->requiresAdminUser());
    }

    public function testRequiresAdminUserExplicitNotSet()
    {
        $command = 'foo';
        $description = 'foo command';
        $flags = ~BuiltInCommandInfo::REQUIRE_ADMIN_USER;

        $commandInfo = new BuiltInCommandInfo($command, $description, $flags);

        $this->assertFalse($commandInfo->requiresAdminUser());
    }

    public function testRequiresAdminUserExplicitSet()
    {
        $command = 'foo';
        $description = 'foo command';
        $flags = BuiltInCommandInfo::REQUIRE_ADMIN_USER;

        $commandInfo = new BuiltInCommandInfo($command, $description, $flags);

        $this->assertTrue($commandInfo->requiresAdminUser());
    }

    public function testRequiresApprovedRoomDefaultSet()
    {
        $command = 'foo';
        $description = 'foo command';

        $commandInfo = new BuiltInCommandInfo($command, $description);

        $this->assertTrue($commandInfo->requiresApprovedRoom());
    }

    public function testRequiresApprovedRoomExplicitSet()
    {
        $command = 'foo';
        $description = 'foo command';
        $flags = ~BuiltInCommandInfo::ALLOW_UNAPPROVED_ROOM;

        $commandInfo = new BuiltInCommandInfo($command, $description, $flags);

        $this->assertTrue($commandInfo->requiresApprovedRoom());
    }

    public function testRequiresApprovedRoomExplicitNotSet()
    {
        $command = 'foo';
        $description = 'foo command';
        $flags = BuiltInCommandInfo::ALLOW_UNAPPROVED_ROOM;

        $commandInfo = new BuiltInCommandInfo($command, $description, $flags);

        $this->assertFalse($commandInfo->requiresApprovedRoom());
    }
}
