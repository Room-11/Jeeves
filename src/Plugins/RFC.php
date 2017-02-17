<?php  declare(strict_types=1);
namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Request as HttpRequest;
use Amp\Artax\Response as HttpResponse;
use Amp\Promise;
use Amp\Success;
use Room11\Jeeves\Chat\Client\Chars;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Entities\PostedMessage;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Amp\all;
use function Amp\resolve;
use function Room11\DOMUtils\domdocument_load_html;

class RFC extends BasePlugin
{
    private $chatClient;
    private $httpClient;
    private $pluginData;

    const BASE_URI = 'https://wiki.php.net/rfc';

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, KeyValueStore $pluginData)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->pluginData = $pluginData;
    }

    public function search(Command $command)
    {
        if ($command->hasParameters(1)) {
            // !!rfcs some-rfc-name
            return yield from $this->getRFC($command);
        }

        $room = $command->getRoom();

        // this may take a while, apparently, so start it going asap
        $pinInfoPromise = all([$this->chatClient->getPinnedMessages($room), $this->getLastPinId($room)]);

        /** @var HttpResponse $response */
        $request = (new HttpRequest)
            ->setMethod('GET')
            ->setUri(self::BASE_URI)
            ->setHeader('Connection', 'close');

        $response = yield $this->httpClient->request($request);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command, "Nope, we can't have nice things.");
        }

        $list = domdocument_load_html($response->getBody())
            ->getElementById("in_voting_phase")
            ->nextSibling
            ->nextSibling
            ->getElementsByTagName("ul")
            ->item(0)
            ->childNodes;

        $rfcsInVoting = [];

        foreach ($list as $node) {
            if ($node instanceof \DOMText) {
                continue;
            }

            /** @var \DOMElement $node */
            /** @var \DOMElement $href */
            $href = $node->getElementsByTagName("a")->item(0);

            $rfcsInVoting[] = sprintf(
                "[%s](%s)",
                trim($href->textContent),
                \Sabre\Uri\resolve(self::BASE_URI, $href->getAttribute("href"))
            );
        }

        if (empty($rfcsInVoting)) {
            yield $this->chatClient->postMessage($command, "Sorry, but we can't have nice things.");

            if (yield $this->chatClient->isBotUserRoomOwner($room)) {
                return all([
                    $this->clearLastPinId($room),
                    $this->unpinPreviousMessage($room, $pinInfoPromise),
                ]);
            }

            return new Success();
        }

        /** @var PostedMessage $postedMessage */
        $postedMessage = yield $this->chatClient->postMessage(
            $command,
            sprintf(
                "[tag:rfc-vote] %s",
                implode(" | ", $rfcsInVoting)
            )
        );

        if (yield $this->chatClient->isBotUserRoomOwner($room)) {
            return all([
                $this->unpinPreviousMessage($room, $pinInfoPromise),
                $this->pinCurrentMessage($room, $postedMessage),
            ]);
        }

        return new Success();
    }

    private function pinCurrentMessage(ChatRoom $room, PostedMessage $message): Promise
    {
        return all([
            $this->pluginData->set('lastpinid', $message->getId(), $room),
            $this->chatClient->pinOrUnpinMessage($message, $room),
        ]);
    }

    private function getLastPinId(ChatRoom $room): Promise
    {
        return resolve(function () use ($room) {
            return (yield $this->pluginData->exists('lastpinid', $room))
                ? yield $this->pluginData->get('lastpinid', $room)
                : -1;
        });
    }

    private function clearLastPinId(ChatRoom $room): Promise
    {
        return resolve(function () use ($room) {
            if (yield $this->pluginData->exists('lastpinid', $room)) {
                yield $this->pluginData->unset('lastpinid', $room);
            }
        });
    }

    private function unpinPreviousMessage(ChatRoom $room, Promise $pinInfoPromise): Promise
    {
        return resolve(function () use ($room, $pinInfoPromise) {
            list($pinnedMessages, $lastPinId) = yield $pinInfoPromise;

            if (in_array($lastPinId, $pinnedMessages)) {
                yield $this->chatClient->unstarMessage($lastPinId, $room);
            }
        });
    }

    public function getRFC(Command $command)
    {
        $rfc = $command->getParameter(0);

        if ($rfc === null) {
            // e.g.: !!rfc pipe-operator
            return $this->chatClient->postMessage($command, "Usage: `!!rfc <id>`");
        }

        $uri = self::BASE_URI . '/' . urlencode($rfc);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($uri);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command, "Nope, we can't have nice things.");
        }

        $messages = $this->prepareVotes($response->getBody(), $uri);

        if (count($messages) === 0) {
            return $this->chatClient->postMessage($command, "No votes found.");
        }

        if (count($messages) === 1) {
            return $this->chatClient->postMessage(
                $command,
                sprintf(
                    "[tag:rfc-vote] [%s](%s) %s",
                    $messages[0]['name'],
                    $messages[0]['href'],
                    $messages[0]['breakdown']
                )
            );
        }

        $message = implode("\n", array_map(function ($message) {
            return sprintf(
                '%s %s - %s (%s)',
                Chars::BULLET,
                $message['name'],
                $message['breakdown'],
                $message['href']
            );
        }, $messages));

        return $this->chatClient->postMessage($command, $message);
    }

    public function getRFCVotes(Command $command)
    {
        $rfc = $command->getParameter(0);

        if ($rfc === null) {
            return $this->chatClient->postMessage($command, "Usage: `!!voting <id>`");
        }

        $uri = self::BASE_URI . '/' . urlencode($rfc);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($uri);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command, "Nope, we can't have nice things.");
        }

        $messages = $this->prepareVotes($response->getBody(), $uri);

        if (count($messages) === 0) {
            return $this->chatClient->postMessage($command, "No votes found.");
        }

        return $this->chatClient->postMessage($command, implode("\n", array_map(function ($message) {

            $voters = implode("\n", array_map(function ($voter) {

                if ($voter['voted'] === false) {
                    return sprintf('%s has not voted', $voter['name']);
                }

                return sprintf(
                    '%s %s %s',
                    $voter['name'],
                    Chars::RIGHTWARDS_ARROW,
                    $voter['choice']
                );
            }, $message['voters']));

            return sprintf(
                "%s %s - %s (%s)\n%s",
                Chars::BULLET,
                $message['name'],
                $message['breakdown'],
                $message['href'],
                $voters
            );
        }, $messages)));
    }

    private function prepareVotes(string $rfcData, string $uri): array
    {

        $votes = self::parseVotes($rfcData);
        if (empty($votes)) {
            return [];
        }

        $messages = [];

        foreach ($votes as $id => $vote) {
            $breakdown = [];

            $total = array_sum($vote['votes']);
            if ($total > 0) {
                foreach ($vote['votes'] as $option => $value) {
                    $breakdown[] = sprintf("%s (%d: %d%%)", $option, $value, 100 * $value / $total);
                }
            }

            $messages[] = [
                'name' => $vote['name'],
                'href' => $uri . '#' . $id,
                'breakdown' => implode(', ', $breakdown),
                'voters' => $vote['voters']
            ];
        }

        return $messages;
    }

    private static function parseVotes(string $html)
    {
        $dom = domdocument_load_html($html);
        $votes = [];

        /** @var \DOMElement $form */
        foreach ($dom->getElementsByTagName('form') as $form) {
            if ($form->getAttribute('name') != 'doodle__form') {
                continue;
            }

            $id = $form->getAttribute('id');
            $info = [
                'name' => $id,
                'votes' => [],
                'voters' => []
            ];
            $options = [];

            /** @var \DOMElement $table */
            $table = $form->getElementsByTagName('table')->item(0);

            /** @var \DOMElement $row */
            foreach ($table->getElementsByTagName('tr') as $row) {
                $class = $row->getAttribute('class');

                if ($class === 'row0') { // Title
                    $title = trim($row->getElementsByTagName('th')->item(0)->textContent);

                    if (!empty($title)) {
                        $info['name'] = $title;
                    }

                    continue;
                }

                if ($class == 'row1') { // Options
                    /** @var \DOMElement $opt */
                    foreach ($row->getElementsByTagName('td') as $i => $opt) {
                        $options[$i] = strval($opt->textContent);
                        $info['votes'][$options[$i]] = 0;
                    }

                    continue;
                }

                $voterColumns = $row->getElementsByTagName('td');

                if ($voterColumns->length === 0) {
                    continue;
                }

                $voter = [
                    'name' => '',
                    'voted' => false,
                    'choice' => null,
                    'at' => null
                ];

                /** @var \DOMElement $vote */
                foreach ($voterColumns as $i => $vote) {
                    if ($i === 0) {
                        $voter['name'] = $vote->getElementsByTagName('a')[0]->nodeValue;
                    }

                    // Adjust by one to ignore voter name
                    $voteImageCollection = $vote->getElementsByTagName('img');

                    if ($voteImageCollection->length > 0) {
                        /** @var \DOMElement $voteImage */
                        $voteImage = $voteImageCollection[0];
                        ++$info['votes'][$options[$i - 1]];
                        $voter['voted'] = true;
                        $voter['choice'] = $options[$i - 1];
                        $voter['at'] = $voteImage->getAttribute('title') ?: null;
                    }
                }

                $info['voters'][] = $voter;
            }

            $votes[$id] = $info;
        }

        return $votes;
    }

    public function getName(): string
    {
        return 'RFC.PHP';
    }

    public function getDescription(): string
    {
        return 'Displays the PHP RFCs which are currently in the voting phase';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [
            new PluginCommandEndpoint(
                'Search',
                [$this, 'search'],
                'rfcs',
                'List RFCs currently in voting, or get the current vote status of a given RFC'
            ),
            new PluginCommandEndpoint(
                'Votes',
                [$this, 'getRFC'],
                'rfc',
                'Get the current vote status of a given RFC'
            ),
            new PluginCommandEndpoint(
                'Voting',
                [$this, 'getRFCVotes'],
                'voting',
                'List all voters of a given RFC.'
            ),
        ];
    }
}
