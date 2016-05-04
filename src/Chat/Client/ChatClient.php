<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Client;

use Amp\Artax\HttpClient;
use Amp\Artax\FormBody;
use Amp\Artax\Request;
use Amp\Artax\Response as ArtaxResponse;
use Amp\Pause;
use ExceptionalJSON\DecodeErrorException as JSONDecodeErrorException;
use Room11\Jeeves\Chat\Room\Room;
use Room11\Jeeves\Fkey\FKey;
use Room11\Jeeves\Chat\Message\Message;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\Mutex;

class ChatClient {
    const MAX_POST_ATTEMPTS = 5;

    private $httpClient;
    private $fkey;
    private $room;

    private $postMutex;
    private $editMutex;
    private $logger;
    private $postRecursionDepth = 0;

    public function __construct(HttpClient $httpClient, FKey $fkey, Room $room, Logger $logger) {
        $this->httpClient = $httpClient;
        $this->fkey = $fkey;
        $this->room = $room;
        $this->logger = $logger;

        $this->postMutex = new Mutex();
        $this->editMutex = new Mutex();
    }

    public function getMessage(int $id): \Generator {
        $uri = sprintf(
            "%s://%s/message/%d",
            $this->room->getHost()->isSecure() ? "https" : "http",
            $this->room->getHost()->getHostname(),
            $id
        );

        return yield $this->httpClient->request($uri);
    }

    public function postMessage(string $text): \Generator {
        $body = (new FormBody)
            ->addField("text", $text)
            ->addField("fkey", $this->fkey);

        $uri = sprintf(
            "%s://%s/chats/%d/messages/new",
            $this->room->getHost()->isSecure() ? "https" : "http",
            $this->room->getHost()->getHostname(),
            $this->room->getId()
        );

        $request = (new Request)
            ->setUri($uri)
            ->setMethod("POST")
            ->setBody($body);

        return yield from $this->postMutex->withLock(function() use($body, $uri, $request) {
            $attempt = 0;

            try {
                $this->postRecursionDepth++;

                while ($attempt++ < self::MAX_POST_ATTEMPTS) {
                    /** @var ArtaxResponse $response */
                    $this->logger->log(Level::DEBUG, 'Post attempt ' . $attempt);
                    $response = yield $this->httpClient->request($request);
                    $this->logger->log(Level::DEBUG, 'Got response from server: ' . $response->getBody());

                    try {
                        $decoded = json_try_decode($response->getBody(), true);

                        if (isset($decoded["id"], $decoded["time"])) {
                            return new Response($decoded["id"], $decoded["time"]);
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
                    yield from $this->postMessage("@PeeHaa error has been logged. Fix it fix it fix it fix it.");
                }
            } finally {
                $this->postRecursionDepth--;
            }

            return null;
        });
    }

    /**
     * @param Message|int $origin
     * @param string $text
     * @return \Generator
     */
    public function postReply($origin, string $text): \Generator
    {
        $target = $origin instanceof Message ? $origin->getId() : (int)$origin;
        return $this->postMessage(":{$target} {$text}");
    }

    public function editMessage(int $id, string $text): \Generator {
        $body = (new FormBody)
            ->addField("text", $text)
            ->addField("fkey", $this->fkey);

        $uri = sprintf(
            "%s://%s/messages/%s",
            $this->room->getHost()->isSecure() ? "https" : "http",
            $this->room->getHost()->getHostname(),
            $id
        );

        $request = (new Request)
            ->setUri($uri)
            ->setMethod("POST")
            ->setBody($body);

        yield from $this->editMutex->withLock(function() use($body, $uri, $request) {
            $attempt = 0;

            try {
                $this->postRecursionDepth++;

                while ($attempt++ < self::MAX_POST_ATTEMPTS) {
                    /** @var ArtaxResponse $response */
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
                yield from $this->postMessage("@PeeHaa error has been logged. Fix it fix it fix it fix it.");
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
