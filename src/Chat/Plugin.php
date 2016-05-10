<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

interface Plugin
{
    public function getName(): string;

    public function getDescription(): string;

    public function getHelpText(array $args): string;

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array;

    /**
     * @return callable[] An array of callbacks with filter strings as keys
     */
    public function getEventHandlers(): array;

    /**
     * @return callable|null
     */
    public function getMessageHandler();

    public function enableForRoom(string $roomIdent) /*: void*/;

    public function disableForRoom(string $roomIdent) /*: void*/;
}
