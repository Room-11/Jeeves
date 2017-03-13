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

    public function testIsAdminOnlyDefaultNotSet()
    {
        $command = 'foo';
        $description = 'foo command';

        $commandInfo = new BuiltInCommandInfo($command, $description);

        $this->assertSame(false, $commandInfo->requiresAdminUser());
    }

    public function testIsAdminOnlyExplicitNotSet()
    {
        $command = 'foo';
        $description = 'foo command';
        $flags = ~BuiltInCommandInfo::REQUIRE_ADMIN_USER;

        $commandInfo = new BuiltInCommandInfo($command, $description, $flags);

        $this->assertSame(false, $commandInfo->requiresAdminUser());
    }

    public function testIsAdminOnlyExplicitTrue()
    {
        $command = 'foo';
        $description = 'foo command';
        $flags = BuiltInCommandInfo::REQUIRE_ADMIN_USER;

        $commandInfo = new BuiltInCommandInfo($command, $description, $flags);

        $this->assertSame(true, $commandInfo->requiresAdminUser());
    }
}
