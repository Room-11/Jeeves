<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Room11\DOMUtils\ElementNotFoundException;
use Room11\Jeeves\Chat\Client\Actions\ActionFactory;
use Room11\Jeeves\Chat\Endpoint as ChatRoomEndpoint;
use Room11\Jeeves\Chat\EndpointURLResolver;
use Room11\Jeeves\Chat\Entities\ChatUser;
use Room11\Jeeves\Chat\Entities\MainSiteUser;
use Room11\Jeeves\Chat\Entities\PostedMessage;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\IdentifierFactory as ChatRoomIdentifierFactory;
use Room11\Jeeves\Chat\Room\NotApprovedException as RoomNotApprovedException;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Chat\Room\UserAccessType as ChatRoomAccessType;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use function Amp\all;
use function Amp\resolve;
use function Room11\DOMUtils\domdocument_load_html;
use function Room11\DOMUtils\xpath_get_element;
use function Room11\DOMUtils\xpath_get_elements;

class ChatClient
{
    const MAX_POST_ATTEMPTS = 5;
    const ENCODING = 'UTF-8';
    const TRUNCATION_LIMIT = 500;

    private $httpClient;
    private $logger;
    private $actionExecutor;
    private $actionFactory;
    private $urlResolver;
    private $identifierFactory;

    public function __construct(
        HttpClient $httpClient,
        Logger $logger,
        ActionExecutor $actionExecutor,
        ActionFactory $actionFactory,
        EndpointURLResolver $urlResolver,
        ChatRoomIdentifierFactory $identifierFactory
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->actionExecutor = $actionExecutor;
        $this->actionFactory = $actionFactory;
        $this->urlResolver = $urlResolver;
        $this->identifierFactory = $identifierFactory;
    }

    private function checkAndNormaliseEncoding(string $text): string
    {
        if (!mb_check_encoding($text, self::ENCODING)) {
            throw new MessagePostFailureException('Message text encoding invalid');
        }

        $text = \Normalizer::normalize(rtrim($text), \Normalizer::FORM_C);

        if ($text === false) {
            throw new MessagePostFailureException('Failed to normalize message text');
        }

        return $text;
    }

    private function applyPostFlagsToText(string $text, int $flags): string
    {
        $text = rtrim($this->checkAndNormaliseEncoding($text));

        if ($flags & PostFlags::SINGLE_LINE) {
            $text = preg_replace('#\s+#u', ' ', $text);
        }
        if ($flags & PostFlags::FIXED_FONT) {
            $text = preg_replace('#(^|\r?\n)#', '$1    ', $text);
        }
        if (!($flags & PostFlags::ALLOW_PINGS)) {
            $text = preg_replace('#@((?:\p{L}|\p{N})(?:\p{L}|\p{N}|\.|-|_|\')*)#u', "@\u{2060}$1", $text);
        }
        if (!($flags & PostFlags::ALLOW_REPLIES)) {
            $text = preg_replace('#^:([0-9]+)\s*#', '', $text);
        }
        if (($flags & ~PostFlags::SINGLE_LINE) & PostFlags::TRUNCATE) {
            $text = $this->truncateText($text);
        }

        return $text;
    }

    private function getIdentifierFromArg($arg): ChatRoomIdentifier
    {
        if ($arg instanceof ChatRoom) {
            return $arg->getIdentifier();
        } else if ($arg instanceof ChatRoomIdentifier) {
            return $arg;
        }

        throw new \InvalidArgumentException('Invalid chat room identifier');
    }

    /**
     * @param PostedMessage|Message|int $messageOrId
     * @param ChatRoom|null $room
     * @return array
     */
    private function getMessageIDAndRoomPairFromArgs($messageOrId, ChatRoom $room = null): array
    {
        if ($messageOrId instanceof Message || $messageOrId instanceof PostedMessage) {
            return [$messageOrId->getId(), $messageOrId->getRoom()];
        }

        if (!is_int($messageOrId)) {
            throw new \InvalidArgumentException('$messageOrId must be integer or instance of ' . Message::class);
        }

        if ($room === null) {
            throw new \InvalidArgumentException('Room required if message ID is specified');
        }

        return [$messageOrId, $room];
    }

    public function truncateText(string $text, $length = self::TRUNCATION_LIMIT): string
    {
        if (mb_strlen($text, self::ENCODING) <= $length) {
            return $text;
        }

        $text = mb_substr($text, 0, $length, self::ENCODING);

        for ($pos = $length - 1; $pos >= 0; $pos--) {
            if (preg_match('#^\s$#u', mb_substr($text, $pos, 1, self::ENCODING))) {
                break;
            }
        }

        if ($pos === 0) {
            $pos = $length - 1;
        }

        return mb_substr($text, 0, $pos, self::ENCODING) . Chars::ELLIPSIS;
    }

    private function parseRoomAccessSection(\DOMElement $section): array
    {
        try {
            $userEls = xpath_get_elements($section, ".//div[contains(concat(' ', normalize-space(@class), ' '), ' usercard ')]");
        } catch (ElementNotFoundException $e) {
            return [];
        }

        $users = [];

        foreach ($userEls as $userEl) {
            $profileAnchor = xpath_get_element($userEl, ".//a[contains(concat(' ', normalize-space(@class), ' '), ' username ')]");

            if (!preg_match('#^/users/([0-9]+)/#', $profileAnchor->getAttribute('href'), $match)) {
                continue;
            }

            $users[(int)$match[1]] = trim($profileAnchor->textContent);
        }

        return $users;
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @return Promise<string[][]>
     */
    public function getRoomAccess($room): Promise
    {
        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_INFO_ACCESS);

        return resolve(function() use($url) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($url);

            $doc = domdocument_load_html($response->getBody());

            $result = [];

            foreach ([ChatRoomAccessType::READ_ONLY, ChatRoomAccessType::READ_WRITE, ChatRoomAccessType::OWNER] as $accessType) {
                $sectionEl = $doc->getElementById('access-section-' . $accessType);
                $result[$accessType] = $sectionEl !== null ? $this->parseRoomAccessSection($sectionEl) : [];
            }

            return $result;
        });
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @return Promise<string[]>
     */
    public function getRoomOwners($room): Promise
    {
        return resolve(function() use($room) {
            $users = yield $this->getRoomAccess($room);
            return $users[ChatRoomAccessType::OWNER];
        });
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @param int $userId
     * @return Promise<bool>
     */
    public function isRoomOwner($room, int $userId): Promise
    {
        return resolve(function() use($room, $userId) {
            $users = yield $this->getRoomOwners($room);
            return isset($users[$userId]);
        });
    }

    /**
     * @param ChatRoom $room
     * @return Promise<bool>
     */
    public function isBotUserRoomOwner(ChatRoom $room): Promise
    {
        return $this->isRoomOwner($room, $room->getSession()->getUser()->getId());
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @param int $messageId
     * @return Promise<ChatRoom>
     */
    public function getRoomIdentifierFromMessageID($room, int $messageId)
    {
        $identifier = $this->getIdentifierFromArg($room);
        $url = $this->urlResolver->getEndpointURL($identifier, ChatRoomEndpoint::CHATROOM_GET_MESSAGE_HISTORY, $messageId);

        return resolve(function() use($identifier, $url, $messageId) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($url);

            $doc = domdocument_load_html($response->getBody());
            $els = (new \DOMXPath($doc))->query("//*[@id='message-{$messageId}']//a[@name='{$messageId}']");

            if ($els->length === 0) {
                throw new DataFetchFailureException('Unable to find message anchor element in response HTML');
            }

            /** @var \DOMElement $anchorEl */
            $anchorEl = $els->item(0);

            if (!preg_match('~^/transcript/([0-9]+)~', $anchorEl->getAttribute('href'), $match)) {
                throw new DataFetchFailureException('Message anchor element href in an unexpected format');
            }

            return $this->identifierFactory->create((int)$match[1], $identifier->getHost());
        });
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @param int[] ...$ids
     * @return Promise
     */
    public function getChatUsers($room, int ...$ids): Promise
    {
        $identifier = $this->getIdentifierFromArg($room);
        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHAT_USER_INFO);

        $body = (new FormBody)
            ->addField('roomId', $identifier->getId())
            ->addField('ids', implode(',', $ids));

        $request = (new HttpRequest)
            ->setMethod('POST')
            ->setUri($url)
            ->setBody($body);

        return resolve(function() use($request, $identifier) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($request);

            return array_map(function($data) use($identifier) {
                return new ChatUser($data);
            }, json_try_decode($response->getBody(), true)['users'] ?? []);
        });
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @param int[] ...$ids
     * @return Promise
     */
    public function getMainSiteUsers($room, int ...$ids): Promise
    {
        $identifier = $this->getIdentifierFromArg($room);

        $promises = [];

        foreach ($ids as $id) {
            $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::MAINSITE_USER, $id);
            $promises[$id] = $this->httpClient->request($url);
        }

        return resolve(function() use($promises, $identifier) {
            /** @var HttpResponse[] $responses */
            $responses = yield all($promises);

            $result = [];

            foreach ($responses as $id => $response) {
                $result[$id] = MainSiteUser::createFromDOMDocument(domdocument_load_html($response->getBody()));
            }

            return $result;
        });
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @return Promise
     */
    public function getPingableUsers($room): Promise
    {
        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_INFO_PINGABLE);

        return resolve(function() use($url) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($url);

            if ($response->getStatus() !== 200) {
                throw new DataFetchFailureException(
                    "Fetching pingable users list failed with response code " . $response->getStatus()
                );
            }

            $result = [];

            foreach (json_try_decode($response->getBody(), true) as $item) {
                $result[] = [
                    'id'       => (int)$item[0],
                    'name'     => $item[1],
                    'pingable' => preg_replace('~\s+~', '', $item[1]),
                ];
            }

            return $result;
        });
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @param string $name
     * @return Promise
     */
    public function getPingableName($room, string $name): Promise
    {
        return resolve(function() use($room, $name) {
            $lower = strtolower($name);
            $users = yield $this->getPingableUsers($room);

            foreach ($users as $user) {
                if (strtolower($user['name']) === $lower || strtolower($user['pingable']) === $lower) {
                    return $user['pingable'];
                }
            }

            return null;
        });
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @param string[] $names
     * @return Promise<int[]>
     */
    public function getPingableUserIDs($room, string ...$names): Promise
    {
        return resolve(function() use($room, $names) {
            $users = yield $this->getPingableUsers($room);
            $result = [];

            foreach ($names as $name) {
                $lower = strtolower($name);

                foreach ($users as $user) {
                    if (strtolower($user['name']) === $lower || strtolower($user['pingable']) === $lower) {
                        $result[$name] = $user['id'];
                        break;
                    }
                }
            }

            return $result;
        });
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @return Promise
     */
    public function getPinnedMessages($room): Promise
    {
        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_STARS_LIST);

        return resolve(function() use($url) {
            /** @var HttpResponse $response */
            $this->logger->log(Level::DEBUG, 'Getting pinned messages');
            $response = yield $this->httpClient->request($url);

            $doc = domdocument_load_html($response->getBody());

            try {
                $pinnedEls = xpath_get_elements($doc, ".//li[./span[contains(concat(' ', normalize-space(@class), ' '), ' owner-star ')]]");
            } catch (ElementNotFoundException $e) {
                return [];
            }

            $result = [];
            foreach ($pinnedEls as $el) {
                $result[] = (int)explode('_', $el->getAttribute('id'))[1];
            }

            $this->logger->log(Level::DEBUG, 'Got pinned messages: ' . implode(',', $result));
            return $result;
        });
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @param int $id
     * @return Promise
     */
    public function getMessageHTML($room, int $id): Promise
    {
        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_GET_MESSAGE_HTML, $id);

        return resolve(function() use($url, $id) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($url);

            if ($response->getStatus() !== 200) {
                throw new MessageFetchFailureException(
                    "Fetching message #{$id} failed with response code " . $response->getStatus()
                );
            }

            return (string)$response->getBody();
        });
    }

    /**
     * @param ChatRoom|ChatRoomIdentifier $room
     * @param int $id
     * @return Promise
     */
    public function getMessageText($room, int $id): Promise
    {
        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_GET_MESSAGE_TEXT, $id);

        return resolve(function() use($url, $id) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($url);

            if ($response->getStatus() !== 200) {
                throw new MessageFetchFailureException(
                    "Fetching message #{$id} failed with response code " . $response->getStatus()
                );
            }

            return (string)$response->getBody();
        });
    }

    /**
     * @param ChatRoom $room
     * @param $pendingMessage
     * @param int $flags
     * @return Promise
     */
    public function postMessage(ChatRoom $room, $pendingMessage, int $flags = PostFlags::NONE): Promise
    {
        return resolve(function() use ($room, $pendingMessage, $flags) {
            // the order of these two conditions is very important! MUST short circuit on $flags or new rooms will block on the welcome message!
            if (!($flags & PostFlags::FORCE) && !(yield $room->isApproved())) {
                throw new RoomNotApprovedException('Bot is not approved for message posting in this room');
            }

            if (is_string($pendingMessage)) {
                $pendingMessage = new PendingMessage($pendingMessage);
            } else if (!$pendingMessage instanceof PendingMessage) {
                throw new InvalidMessageTypeException('Message must be a string or an instance of ' . PendingMessage::class);
            }

            $text = $this->applyPostFlagsToText($pendingMessage->getText(), $flags);
            $pendingMessage->setText($text);

            $body = (new FormBody)
                ->addField("text", $text)
                ->addField("fkey", (string)$room->getSession()->getFKey());

            $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_POST_MESSAGE);

            $request = (new HttpRequest)
                ->setUri($url)
                ->setMethod("POST")
                ->setBody($body);

            $action = $this->actionFactory->createPostMessageAction($request, $room, $pendingMessage);
            $this->actionExecutor->enqueue($action);

            return $action->promise();
        });
    }

    public function moveMessages(ChatRoom $room, int $targetRoomId, int ...$messageIds): Promise
    {
        $body = (new FormBody)
            ->addField("fkey", $room->getSession()->getFKey())
            ->addField('ids', implode(',', $messageIds))
            ->addField('to', $targetRoomId);

        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_MOVE_MESSAGE);

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        $action = $this->actionFactory->createMoveMessageAction($request, $room);
        return $this->actionExecutor->enqueue($action);
    }

    /**
     * @param IdentifiableMessage $origin
     * @param $pendingMessage
     * @param int $flags
     * @return Promise
     * @internal param string $text
     */
    public function postReply(IdentifiableMessage $origin, $pendingMessage, int $flags = PostFlags::NONE): Promise
    {
        $flags |= PostFlags::ALLOW_REPLIES;
        $flags &= ~PostFlags::FIXED_FONT;

        if (!$pendingMessage instanceof PendingMessage && $origin instanceof Command) {
            $pendingMessage = new PendingMessage($pendingMessage, $origin);
        }

        $pendingMessage->setText(
            ":{$origin->getId()} {$pendingMessage->getText()}"
        );

        return $this->postMessage($origin->getRoom(), $pendingMessage, $flags);
    }

    /**
     * @param PostedMessage|Message $message
     * @param string $text
     * @param int $flags
     * @return Promise
     */
    public function editMessage($message, string $text, int $flags = PostFlags::NONE): Promise
    {
        $text = $this->applyPostFlagsToText($text, $flags);

        $body = (new FormBody)
            ->addField("text", $text)
            ->addField("fkey", (string)$message->getRoom()->getSession()->getFKey());

        $url = $this->urlResolver->getEndpointURL($message->getRoom(), ChatRoomEndpoint::CHATROOM_EDIT_MESSAGE, $message->getId());

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        $action = $this->actionFactory->createEditMessageAction($request, $message->getRoom());

        return $this->actionExecutor->enqueue($action);
    }

    /**
     * @param PostedMessage|Message|int $messageOrId
     * @param ChatRoom|null $room
     * @return Promise
     */
    public function pinOrUnpinMessage($messageOrId, ChatRoom $room = null): Promise
    {
        list($messageId, $room) = $this->getMessageIDAndRoomPairFromArgs($messageOrId, $room);

        $body = (new FormBody)
            ->addField("fkey", $room->getSession()->getFKey());

        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_PIN_MESSAGE, $messageId);

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        $action = $this->actionFactory->createPinOrUnpinMessageAction($request, $room);

        return $this->actionExecutor->enqueue($action);
    }

    /**
     * @param PostedMessage|Message|int $messageOrId
     * @param ChatRoom|null $room
     * @return Promise
     */
    public function unstarMessage($messageOrId, ChatRoom $room = null): Promise
    {
        list($messageId, $room) = $this->getMessageIDAndRoomPairFromArgs($messageOrId, $room);

        $body = (new FormBody)
            ->addField("fkey", $room->getSession()->getFKey());

        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_UNSTAR_MESSAGE, $messageId);

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        $action = $this->actionFactory->createUnstarMessageAction($request, $room);

        return $this->actionExecutor->enqueue($action);
    }
}
