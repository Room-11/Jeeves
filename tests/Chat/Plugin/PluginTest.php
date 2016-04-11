<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Room11\Jeeves\Chat\Plugin\Plugin;
use Room11\Jeeves\Tests\Chat\ChatTest;

abstract class PluginTest extends ChatTest
{
    /**
     * @var Plugin
     */
    protected $plugin;
}
