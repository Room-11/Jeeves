<?php declare(strict_types = 1);

namespace Room11\Jeeves\Tests\Chat\Plugin;

use Room11\Jeeves\Chat\Plugin;
use Room11\Jeeves\Tests\Chat\AbstractChatTest;

abstract class AbstractPluginTest extends AbstractChatTest
{
    /**
     * @var Plugin
     */
    protected $plugin;
}
