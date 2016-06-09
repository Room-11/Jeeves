<?php declare(strict_types=1);

namespace Room11\Jeeves;

use Room11\Jeeves\Chat\PluginCommandEndpoint;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;

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
     *
     * Filter syntax:
     *
     *  Filters are similar to application/x-www-form-urlencoded strings.  They do not need to be URL-encoded, instead
     *  values that contain characters that need to be escaped can be quoted as strings using single or double quotes.
     *  This is currently not required as no fields can contain data which will cause a problem.
     *
     *  A filter is constructed from one or more conditions, separated by ampersands. All of the conditions must match
     *  for a filter to be considered to match.   A condition is comprised of a field name on the left and a value for
     *  comparison on the right.   The comparison value may also be a set, the behaviour of the set is defined by it's
     *  name, valid set names are field-dependent and documented below. Whitespace between symbols is tolerated.
     *
     * Supported filter fields:
     *
     *  type={id}, type=any({ids})
     *   - the event type id, available from the {EventClass}::TYPE_ID constant and $event->getTypeId() method
     *
     *  room={id}, room=any({ids})
     *   - the room ident string (domain#id). Available from $room->getIdentifier()->getIdent().
     *
     *  class={class}, class=any({classes}), class=all({classes})
     *   - classes define event subtypes based on their API. Currently available classes are:
     *     user    => UserSourcedEvent
     *     room    => RoomSourcedEvent
     *     message => MessageEvent
     *
     * Example filters:
     *
     *  type = 1 // all NewMessage events
     *  type = 1 & room = chat.stackoverflow.com#11 // all NewMessage events from SO PHP chat
     *  room=chat.stackoverflow.com#11&class=any(user,room) // any message from SO PHP chat that is UserSourcedEvent or RoomSourcedEvent
     */
    public function getEventHandlers(): array;

    /**
     * @return callable|null
     */
    public function getMessageHandler() /* : ?callable */;

    public function enableForRoom(ChatRoom $room, bool $persist) /* : void */;

    public function disableForRoom(ChatRoom $room, bool $persist) /* : void */;
}
