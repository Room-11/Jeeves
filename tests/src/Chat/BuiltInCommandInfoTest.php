<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat;

use Room11\Jeeves\System\BuiltInCommandInfo;

class BuiltInCommandInfoTest extends \PHPUnit_Framework_TestCase
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

    public function testIsAdminOnlyDefaultFalse()
    {
        $command = 'foo';
        $description = 'foo command';

        $commandInfo = new BuiltInCommandInfo($command, $description);

        $this->assertSame(false, $commandInfo->isAdminOnly());
    }

    public function testIsAdminOnlyExplicitFalse()
    {
        $command = 'foo';
        $description = 'foo command';
        $adminOnly = false;

        $commandInfo = new BuiltInCommandInfo($command, $description, $adminOnly);

        $this->assertSame($adminOnly, $commandInfo->isAdminOnly());
    }

    public function testIsAdminOnlyExplicitTrue()
    {
        $command = 'foo';
        $description = 'foo command';
        $adminOnly = true;

        $commandInfo = new BuiltInCommandInfo($command, $description, $adminOnly);

        $this->assertSame($adminOnly, $commandInfo->isAdminOnly());
    }
}
