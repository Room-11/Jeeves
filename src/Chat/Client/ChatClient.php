<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Mutex\QueuedExclusiveMutex;
use Amp\Pause;
use Amp\Promise;
use ExceptionalJSON\DecodeErrorException as JSONDecodeErrorException;
use Room11\DOMUtils\ElementNotFoundException;
use Room11\Jeeves\Chat\Client\Entities\PostedMessage;
use Room11\Jeeves\Chat\Client\Entities\User;
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

    private $postMutex;
    private $editMutex;
    private $starMutex;

    private $postRecursionDepth = 0;

    public function __construct(HttpClient $httpClient, Logger $logger) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;

        $this->postMutex = new QueuedExclusiveMutex();
        $this->editMutex = new QueuedExclusiveMutex();
        $this->starMutex = new QueuedExclusiveMutex();
    }

    private function applyPostFlagsToText(string $text, int $flags)
    {
        if ($flags & PostFlags::SINGLE_LINE) {
            $text = preg_replace('#\s+#u', ' ', $text);
        }
        if ($flags & PostFlags::FIXED_FONT) {
            $text = preg_replace('#(^|\r?\n)#', '$1    ', $text);
        }
        if (!($flags & PostFlags::ALLOW_PINGS)) {
            $text = preg_replace('#(^|\s)@#', "$0\u{2060}", $text);
        }

        if ((($flags & ~PostFlags::SINGLE_LINE) & PostFlags::TRUNCATE)
            && mb_strlen($text, self::ENCODING) > self::TRUNCATION_LIMIT) {
            $text = mb_substr($text, 0, self::TRUNCATION_LIMIT, self::ENCODING);

            for ($i = self::TRUNCATION_LIMIT - 1; $i >= 0; $i--) {
                if (preg_match('#^\pZ$#u', mb_substr($text, $i, 1, self::ENCODING))) {
                    break;
                }
            }

            if ($i === 0) {
                // srsly
                throw new \Exception('This should not be caught by anything. Your code is bad and you should feel bad.');
            }

            $text = mb_substr($text, 0, $i, self::ENCODING) . Chars::ELLIPSIS;
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
            ->addField("text", rtrim($text)) // only rtrim an not trim, leading space may be legit
            ->addField("fkey", (string) $room->getSessionInfo()->getFKey());

        $url = $room->getEndpointURL(ChatRoomEndpoint::CHATROOM_POST_MESSAGE);

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        return $this->postMutex->withLock(function() use($request, $room) {
            $attempt = 0;

            try {
                $this->postRecursionDepth++;

                while ($attempt++ < self::MAX_POST_ATTEMPTS) {
                    /** @var HttpResponse $response */
                    $this->logger->log(Level::DEBUG, 'Post attempt ' . $attempt);
                    $response = yield $this->httpClient->request($request);
                    $this->logger->log(Level::DEBUG, 'Got response from server: ' . $response->getBody());

                    try {
                        $decoded = json_try_decode($response->getBody(), true);

                        if (isset($decoded["id"], $decoded["time"])) {
                            return new PostedMessage($room, $decoded["id"], $decoded["time"]);
                        }

                        if ($attempt >= self::MAX_POST_ATTEMPTS) {
                            break;
                        }

                        if (!array_key_exists('id', $decoded)) {
                            throw new \RuntimeException('A JSON response that I don\'t understand was received');
                        }

                        // sometimes we can get {"id":null,"time":null}
                        // I think this happens when we repeat ourselves too quickly
                        $delay = $attempt * 1000;
                        $this->logger->log(Level::DEBUG, "Got a null message post response, waiting for {$delay}ms before trying again");
                    } catch (JSONDecodeErrorException $e) {
                        if ($attempt >= self::MAX_POST_ATTEMPTS) {
                            break;
                        }

                        if (0 === $delay = $this->getBackOffDelay($response->getBody())) {
                            throw new \RuntimeException(
                                'A response that could not be decoded as JSON or otherwise handled was received'
                                . ' (JSON decode error: ' . $e->getMessage() . ')'
                            );
                        }

                        $this->logger->log(Level::DEBUG, "Backing off message posting for {$delay}ms");
                    }

                    yield new Pause($delay);
                }

                $attempt--;
                throw new \RuntimeException(
                    'Sending the message failed after ' . self::MAX_POST_ATTEMPTS . ' attempts and I know when to quit'
                );
            } catch (\Throwable $e) {
                $errorInfo = [
                    'attempt' => $attempt,
                    'postRecursionDepth' => $this->postRecursionDepth,
                    'responseBody' => isset($response) ? $response->getBody() : 'No response data',
                ];

                $this->logger->log(Level::DEBUG, 'Error while posting message: ' . $e->getMessage(), $errorInfo);
            } finally {
                $this->postRecursionDepth--;
            }

            return null;
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

        $url = $message->getRoom()->getEndpointURL(ChatRoomEndpoint::CHATROOM_EDIT_MESSAGE, $message->getMessageId());

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        return $this->editMutex->withLock(function() use($request, $message) {
            $attempt = 0;

            try {
                $this->postRecursionDepth++;

                while ($attempt++ < self::MAX_POST_ATTEMPTS) {
                    /** @var HttpResponse $response */
                    $this->logger->log(Level::DEBUG, 'Edit attempt ' . $attempt);
                    $response = yield $this->httpClient->request($request);
                    $this->logger->log(Level::DEBUG, 'Got response from server: ' . $response->getBody());

                    try {
                        $decoded = json_try_decode($response->getBody(), true);

                        if ($decoded === 'ok') {
                            return true;
                        }

                        throw new \RuntimeException('A JSON response that I don\'t understand was received');
                    } catch (JSONDecodeErrorException $e) {
                        if ($attempt >= self::MAX_POST_ATTEMPTS) {
                            break;
                        }

                        if (0 === $delay = $this->getBackOffDelay($response->getBody())) {
                            throw new \RuntimeException(
                                'A response that could not be decoded as JSON or otherwise handled was received'
                                . ' (JSON decode error: ' . $e->getMessage() . ')'
                            );
                        }

                        $this->logger->log(Level::DEBUG, "Backing off message posting for {$delay}ms");
                        yield new Pause($delay);
                    }
                }

                $attempt--;
                throw new \RuntimeException(
                    'Editing the message failed after ' . self::MAX_POST_ATTEMPTS . ' attempts and I know when to quit'
                );
            } catch (\Throwable $e) {
                $errorInfo = [
                    'attempt' => $attempt,
                    'responseBody' => isset($response) ? $response->getBody() : 'No response data',
                ];

                $this->logger->log(Level::ERROR, 'Error while editing message: ' . $e->getMessage(), $errorInfo);
            } finally {
                $this->postRecursionDepth--;
            }

            return false;
        });
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

        return $this->starMutex->withLock(function() use($request, $room) {
            $attempt = 0;

            try {
                $this->postRecursionDepth++;

                while ($attempt++ < self::MAX_POST_ATTEMPTS) {
                    /** @var HttpResponse $response */
                    $this->logger->log(Level::DEBUG, 'Pin attempt ' . $attempt);
                    $response = yield $this->httpClient->request($request);
                    $this->logger->log(Level::DEBUG, 'Got response from server: ' . $response->getBody());

                    try {
                        $decoded = json_try_decode($response->getBody(), true);

                        if ($decoded === 'ok') {
                            return true;
                        }

                        throw new \RuntimeException('A JSON response that I don\'t understand was received');
                    } catch (JSONDecodeErrorException $e) {
                        if ($attempt >= self::MAX_POST_ATTEMPTS) {
                            break;
                        }

                        if (0 === $delay = $this->getBackOffDelay($response->getBody())) {
                            throw new \RuntimeException(
                                'A response that could not be decoded as JSON or otherwise handled was received'
                                . ' (JSON decode error: ' . $e->getMessage() . ')'
                            );
                        }

                        $this->logger->log(Level::DEBUG, "Backing off message pinning for {$delay}ms");
                        yield new Pause($delay);
                    }
                }

                $attempt--;
                throw new \RuntimeException(
                    'Pinning the message failed after ' . self::MAX_POST_ATTEMPTS . ' attempts and I know when to quit'
                );
            } catch (\Throwable $e) {
                $errorInfo = [
                    'attempt' => $attempt,
                    'responseBody' => isset($response) ? $response->getBody() : 'No response data',
                ];

                $this->logger->log(Level::ERROR, 'Error while pinning message: ' . $e->getMessage(), $errorInfo);
            } finally {
                $this->postRecursionDepth--;
            }

            return false;
        });
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

        return $this->starMutex->withLock(function() use($request, $room) {
            $attempt = 0;

            try {
                $this->postRecursionDepth++;

                while ($attempt++ < self::MAX_POST_ATTEMPTS) {
                    /** @var HttpResponse $response */
                    $this->logger->log(Level::DEBUG, 'Unstar attempt ' . $attempt);
                    $response = yield $this->httpClient->request($request);
                    $this->logger->log(Level::DEBUG, 'Got response from server: ' . $response->getBody());

                    try {
                        $decoded = json_try_decode($response->getBody(), true);

                        if ($decoded === 'ok') {
                            return true;
                        }

                        throw new \RuntimeException('A JSON response that I don\'t understand was received');
                    } catch (JSONDecodeErrorException $e) {
                        if ($attempt >= self::MAX_POST_ATTEMPTS) {
                            break;
                        }

                        if (0 === $delay = $this->getBackOffDelay($response->getBody())) {
                            throw new \RuntimeException(
                                'A response that could not be decoded as JSON or otherwise handled was received'
                                . ' (JSON decode error: ' . $e->getMessage() . ')'
                            );
                        }

                        $this->logger->log(Level::DEBUG, "Backing off message pinning for {$delay}ms");
                        yield new Pause($delay);
                    }
                }

                $attempt--;
                throw new \RuntimeException(
                    'Unstarring the message failed after ' . self::MAX_POST_ATTEMPTS . ' attempts and I know when to quit'
                );
            } catch (\Throwable $e) {
                $errorInfo = [
                    'attempt' => $attempt,
                    'responseBody' => isset($response) ? $response->getBody() : 'No response data',
                ];

                $this->logger->log(Level::ERROR, 'Error while unstarring message: ' . $e->getMessage(), $errorInfo);
            } finally {
                $this->postRecursionDepth--;
            }

            return false;
        });
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

    private function getBackOffDelay(string $body): int
    {
        if (!preg_match('/You can perform this action again in (\d+) seconds/i', $body, $matches)) {
            return 0;
        }

        return (int)(($matches[1] + 1.1) * 1000);
    }
}
