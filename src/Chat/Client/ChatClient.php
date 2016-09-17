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
use Room11\Jeeves\Chat\Room\Endpoint as ChatRoomEndpoint;
use Room11\Jeeves\Chat\Room\Identifier as ChatRoomIdentifier;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
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

    public function __construct(
        HttpClient $httpClient,
        Logger $logger,
        ActionExecutor $actionExecutor,
        ActionFactory $actionFactory
    ) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->actionExecutor = $actionExecutor;
        $this->actionFactory = $actionFactory;
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

    /**
     * @param ChatRoom|ChatRoomIdentifier $arg
     * @return ChatRoomIdentifier
     */
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

    public function getRoomOwnerIds(ChatRoom $room): Promise
    {
        return resolve(function() use($room) {
            $url = $room->getEndpointURL(ChatRoomEndpoint::CHATROOM_INFO_ACCESS);

            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($url);

            $doc = domdocument_load_html($response->getBody());

            $ownerSection = $doc->getElementById('access-section-owner');
            if ($ownerSection === null) {
                throw new \RuntimeException('Could not find the access-section-owner container div');
            }

            $userEls = xpath_get_elements($ownerSection, ".//div[contains(concat(' ', normalize-space(@class), ' '), ' usercard ')]");
            $users = [];

            foreach ($userEls as $userEl) {
                $profileAnchor = xpath_get_element($userEl, ".//a[contains(concat(' ', normalize-space(@class), ' '), ' username ')]");

                if (!preg_match('#^/users/([0-9]+)/#', $profileAnchor->getAttribute('href'), $match)) {
                    continue;
                }

                $users[(int)$match[1]] = trim($profileAnchor->textContent);
            }

            return $users;
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
        $url = $identifier->getEndpointUrl(ChatRoomEndpoint::CHAT_USER_INFO);

        $body = (new FormBody)
            ->addField('roomId', $identifier->getId())
            ->addField('ids', implode(',', $ids));

        $request = (new HttpRequest)
            ->setMethod('POST')
            ->setUri($url)
            ->setBody($body);

        return resolve(function() use($request) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($request);

            return array_map(function($data) {
                return new User($data);
            }, json_try_decode($response->getBody(), true)['users'] ?? []);
        });
    }

    public function getPingableUsers(ChatRoom $room): Promise
    {
        $url = $room->getEndpointURL(ChatRoomEndpoint::CHATROOM_INFO_PINGABLE);

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

    public function getPingableName(ChatRoom $room, string $name): Promise
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

    public function getMessageHTML(ChatRoom $room, int $id): Promise
    {
        $url = $room->getEndpointURL(ChatRoomEndpoint::CHATROOM_GET_MESSAGE_HTML, $id);

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

    public function getMessageText(ChatRoom $room, int $id): Promise
    {
        $url = $room->getEndpointURL(ChatRoomEndpoint::CHATROOM_GET_MESSAGE_TEXT, $id);

        return resolve(function() use($url) {
            /** @var HttpResponse $response */
            $response = yield $this->httpClient->request($url);

            if ($response->getStatus() !== 200) {
                throw new MessageFetchFailureException("It doesn't working", $response->getStatus());
            }

            return (string)$response->getBody();
        });
    }

    public function postMessage(ChatRoom $room, string $text, int $flags = PostFlags::NONE): Promise
    {
        if (!mb_check_encoding($text, self::ENCODING)) {
            throw new MessagePostFailureException('Message text encoding invalid');
        }
        
        $text = $this->applyPostFlagsToText($text, $flags);

        $body = (new FormBody)
            ->addField("text", $text)
            ->addField("fkey", (string)$room->getSessionInfo()->getFKey());

        $url = $room->getEndpointURL(ChatRoomEndpoint::CHATROOM_POST_MESSAGE);

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        $action = $this->actionFactory->createPostMessageAction($request, $room);
        $this->actionExecutor->enqueue($action);

        return $action->getPromisor()->promise();
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

        $url = $message->getRoom()->getEndpointURL(ChatRoomEndpoint::CHATROOM_EDIT_MESSAGE, $message->getMessageId());

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

        $url = $room->getEndpointURL(ChatRoomEndpoint::CHATROOM_PIN_MESSAGE, $messageId);

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

        $url = $room->getEndpointURL(ChatRoomEndpoint::CHATROOM_UNSTAR_MESSAGE, $messageId);

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        $action = $this->actionFactory->createUnstarMessageAction($request);
        $this->actionExecutor->enqueue($action);

        return $action->getPromisor()->promise();
    }

    public function getPinnedMessages(ChatRoom $room): Promise
    {
        return resolve(function() use($room) {
            /** @var HttpResponse $response */
            $this->logger->log(Level::DEBUG, 'Getting pinned messages');
            $response = yield $this->httpClient->request($room->getEndpointURL(ChatRoomEndpoint::CHATROOM_STARS_LIST));

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
}
