<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Mutex\QueuedExclusiveMutex;
use Amp\Pause;
use ExceptionalJSON\DecodeErrorException as JSONDecodeErrorException;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Chat\Room\Endpoint as ChatRoomEndpoint;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;

class ChatClient {
    const MAX_POST_ATTEMPTS = 5;

    private $httpClient;
    private $logger;

    private $postMutex;
    private $editMutex;

    private $postRecursionDepth = 0;

    public function __construct(HttpClient $httpClient, Logger $logger) {
        $this->httpClient = $httpClient;
        $this->logger = $logger;

        $this->postMutex = new QueuedExclusiveMutex();
        $this->editMutex = new QueuedExclusiveMutex();
    }

    public function getMessage(ChatRoom $room, int $id): \Generator {
        $url = $room->getIdentifier()->getEndpointURL(ChatRoomEndpoint::GET_MESSAGE, $id);
        return yield $this->httpClient->request($url);
    }

    public function postMessage(ChatRoom $room, string $text): \Generator {
        $body = (new FormBody)
            ->addField("text", $text)
            ->addField("fkey", (string) $room->getFKey());

        $url = $room->getIdentifier()->getEndpointURL(ChatRoomEndpoint::POST_MESSAGE);

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        return yield from $this->postMutex->withLock(function() use($request, $room) {
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

                if ($this->postRecursionDepth === 1) {
                    yield new Pause(2000);
                    yield from $this->postMessage($room, "@PeeHaa error has been logged. Fix it fix it fix it fix it.");
                }
            } finally {
                $this->postRecursionDepth--;
            }

            return null;
        });
    }

    /**
     * @param Message $origin
     * @param string $text
     * @return \Generator
     */
    public function postReply(Message $origin, string $text): \Generator
    {
        return $this->postMessage($origin->getRoom(), ":{$origin->getId()} {$text}");
    }

    public function editMessage(PostedMessage $message, string $text): \Generator {
        $body = (new FormBody)
            ->addField("text", $text)
            ->addField("fkey", (string) $message->getRoom()->getFKey());

        $url = $message->getRoom()
            ->getIdentifier()
            ->getEndpointURL(ChatRoomEndpoint::EDIT_MESSAGE, $message->getMessageId());

        $request = (new HttpRequest)
            ->setUri($url)
            ->setMethod("POST")
            ->setBody($body);

        yield from $this->editMutex->withLock(function() use($request, $message) {
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

                yield new Pause(2000);
                yield from $this->postMessage($message->getRoom(), "@PeeHaa error has been logged. Fix it fix it fix it fix it.");
            } finally {
                $this->postRecursionDepth--;
            }

            return false;
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
