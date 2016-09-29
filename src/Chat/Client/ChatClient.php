<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Room11\DOMUtils\ElementNotFoundException;
use Room11\Jeeves\Chat\Client\Entities\PostedMessage;
use Room11\Jeeves\Chat\Client\Entities\User;
use Room11\Jeeves\Chat\Client\Actions\ActionFactory;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\Room\AccessType as ChatRoomAccessType;
use Room11\Jeeves\Chat\Room\Endpoint as ChatRoomEndpoint;
use Room11\Jeeves\Chat\Room\EndpointURLResolver;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Chat\Room\NotApprovedException as RoomNotApprovedException;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
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

    public function __construct(
        HttpClient $httpClient,
        Logger $logger,
        ActionExecutor $actionExecutor,
        ActionFactory $actionFactory,
        EndpointURLResolver $urlResolver
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->actionExecutor = $actionExecutor;
        $this->actionFactory = $actionFactory;
        $this->urlResolver = $urlResolver;
    }

    private function applyPostFlagsToText(string $text, int $flags)
    {
        $text = rtrim($text);

        if ($flags & PostFlags::SINGLE_LINE) {
            $text = preg_replace('#\s+#u', ' ', $text);
        }
        if ($flags & PostFlags::FIXED_FONT) {
            $text = preg_replace('#(^|\r?\n)#', '$1    ', $text);
        }
        if (!($flags & PostFlags::ALLOW_PINGS)) {
            $text = preg_replace('#(^|\s)@#', "$0\u{2060}", $text);
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
    public function getRoomAccess($room)
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
    public function isRoomOwner($room, int $userId)
    {
        return resolve(function() use($room, $userId) {
            $users = yield $this->getRoomOwners($room);
            return isset($users[$userId]);
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
                return new User($data);
            }, json_try_decode($response->getBody(), true)['users'] ?? []);
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

    public function postMessage(ChatRoom $room, string $text, int $flags = PostFlags::NONE): Promise
    {
        return resolve(function() use ($room, $text, $flags) {
            // the order of these two conditions is very important! MUST short circuit on $flags or new rooms will block on the welcome message!
            if (!($flags & PostFlags::FORCE) && !(yield $room->isApproved())) {
                throw new RoomNotApprovedException('Bot is not approved for message posting in this room');
            }

            if (!mb_check_encoding($text, self::ENCODING)) {
                throw new MessagePostFailureException('Message text encoding invalid');
            }

            $text = $this->applyPostFlagsToText($text, $flags);

            $body = (new FormBody)
                ->addField("text", $text)
                ->addField("fkey", (string)$room->getSessionInfo()->getFKey());

            $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_POST_MESSAGE);

            $request = (new HttpRequest)
                ->setUri($url)
                ->setMethod("POST")
                ->setBody($body);

            $action = $this->actionFactory->createPostMessageAction($request, $room);
            $this->actionExecutor->enqueue($action);

            return $action->getPromisor()->promise();
        });
    }

    public function postReply(Message $origin, string $text, int $flags = PostFlags::NONE): Promise
    {
        return $this->postMessage($origin->getRoom(), ":{$origin->getId()} {$text}", $flags & ~PostFlags::FIXED_FONT);
    }

    public function editMessage(PostedMessage $message, string $text, int $flags = PostFlags::NONE): Promise
    {
        if (!mb_check_encoding($text, self::ENCODING)) {
            throw new MessagePostFailureException('Message text encoding invalid');
        }

        $text = $this->applyPostFlagsToText($text, $flags);

        $body = (new FormBody)
            ->addField("text", $text)
            ->addField("fkey", (string)$message->getRoom()->getSessionInfo()->getFKey());

        $url = $this->urlResolver->getEndpointURL($message->getRoom(), ChatRoomEndpoint::CHATROOM_EDIT_MESSAGE, $message->getMessageId());

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        $action = $this->actionFactory->createEditMessageAction($request);
        $this->actionExecutor->enqueue($action);

        return $action->getPromisor()->promise();
    }

    /**
     * @param Message|int $messageOrId
     * @param ChatRoom|null $room
     * @return Promise
     */
    public function pinOrUnpinMessage($messageOrId, ChatRoom $room = null): Promise
    {
        if ($messageOrId instanceof Message) {
            $messageId = $messageOrId->getId();
            $room = $messageOrId->getRoom();
        } else if (is_int($messageOrId)) {
            $messageId = $messageOrId;
        } else {
            throw new \InvalidArgumentException('$messageOrId must be integer or instance of ' . Message::class);
        }

        $body = (new FormBody)
            ->addField("fkey", $room->getSessionInfo()->getFKey());

        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_PIN_MESSAGE, $messageId);

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        $action = $this->actionFactory->createPinOrUnpinMessageAction($request);
        $this->actionExecutor->enqueue($action);

        return $action->getPromisor()->promise();
    }

    /**
     * @param Message|int $messageOrId
     * @param ChatRoom|null $room
     * @return Promise
     */
    public function unstarMessage($messageOrId, ChatRoom $room = null): Promise
    {
        if ($messageOrId instanceof Message) {
            $messageId = $messageOrId->getId();
            $room = $messageOrId->getRoom();
        } else if (is_int($messageOrId)) {
            $messageId = $messageOrId;
        } else {
            throw new \InvalidArgumentException('$messageOrId must be integer or instance of ' . Message::class);
        }

        $body = (new FormBody)
            ->addField("fkey", $room->getSessionInfo()->getFKey());

        $url = $this->urlResolver->getEndpointURL($room, ChatRoomEndpoint::CHATROOM_UNSTAR_MESSAGE, $messageId);

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        $action = $this->actionFactory->createUnstarMessageAction($request);
        $this->actionExecutor->enqueue($action);

        return $action->getPromisor()->promise();
    }
}
