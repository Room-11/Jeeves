<?php declare(strict_types = 1);

namespace Room11\Jeeves\Plugins;

use Room11\Jeeves\System\Plugin;
use Room11\StackChat\Room\Room as ChatRoom;

abstract class BasePlugin implements Plugin
{
    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return basename(strtr(get_class($this), '\\', '/'));
    }

    /**
     * @inheritdoc
     */
    abstract public function getDescription(): string;

    /**
     * @inheritdoc
     */
    public function getCommandEndpoints(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getEventHandlers(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getMessageHandler() /* : ?callable */
    {
        return null;
    }

    /**
     * @inheritdoc
     */
    public function enableForRoom(ChatRoom $room, bool $persist) /* : void */ {}

    /**
     * @inheritdoc
     */
    public function disableForRoom(ChatRoom $room, bool $persist) /* : void */ {}
}
