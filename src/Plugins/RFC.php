<?php  declare(strict_types=1);
namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\Entities\PostedMessage;
use Room11\Jeeves\Chat\Message\Command;
use Room11\Jeeves\Chat\Room\Room as ChatRoom;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use function Room11\DOMUtils\domdocument_load_html;

class RFC extends BasePlugin
{
    private $chatClient;
    private $httpClient;
    private $pluginData;

    const BASE_URI = 'https://wiki.php.net/rfc';
    const BULLET   = "\xE2\x80\xA2";

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, KeyValueStore $pluginData)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->pluginData = $pluginData;
    }

    public function search(Command $command): \Generator {
        if ($command->hasParameters(1)) {
            // !!rfcs some-rfc-name
            return yield from $this->getRFC($command);
        }

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(self::BASE_URI);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command->getRoom(), "Nope, we can't have nice things.");
        }

        $dom = domdocument_load_html($response->getBody());

        $list = $dom->getElementById("in_voting_phase")->nextSibling->nextSibling->getElementsByTagName("ul")->item(0);
        $rfcsInVoting = [];

        foreach ($list->childNodes as $node) {
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

        yield from $this->unpinPreviousMessage($command->getRoom());

        if (empty($rfcsInVoting)) {
            if (yield $this->pluginData->exists('lastpinid', $command->getRoom())) {
                yield $this->pluginData->unset('lastpinid', $command->getRoom());
            }
            
            return $this->chatClient->postMessage($command->getRoom(), "Sorry, but we can't have nice things.");
        }

        /** @var PostedMessage $postedMessage */
        $postedMessage = yield $this->chatClient->postMessage(
            $command->getRoom(),
            sprintf(
                "[tag:rfc-vote] %s",
                implode(" | ", $rfcsInVoting)
            )
        );

        yield $this->pluginData->set('lastpinid', $postedMessage->getMessageId(), $command->getRoom());
        return $this->chatClient->pinOrUnpinMessage($postedMessage->getMessageId(), $command->getRoom());
    }

    private function unpinPreviousMessage(ChatRoom $room) {
        $pinnedMessages = yield $this->chatClient->getPinnedMessages($room);
        $lastPinId = (yield $this->pluginData->exists('lastpinid', $room))
            ? yield $this->pluginData->get('lastpinid', $room)
            : -1;

        if (in_array($lastPinId, $pinnedMessages)) {
            yield $this->chatClient->unstarMessage($lastPinId, $room);
        }
    }

    public function getRFC(Command $command): \Generator {
        $rfc = $command->getParameter(0);
        if ($rfc === null) {
            // e.g.: !!rfc pipe-operator
            return $this->chatClient->postMessage($command->getRoom(), "RFC id required");
        }

        $uri = self::BASE_URI . '/' . urlencode($rfc);

        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request($uri);

        if ($response->getStatus() !== 200) {
            return $this->chatClient->postMessage($command->getRoom(), "Nope, we can't have nice things.");
        }

        $votes = self::parseVotes($response->getBody());
        if (empty($votes)) {
            return $this->chatClient->postMessage($command->getRoom(), "No votes found");
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
            ];
        }

        if (count($messages) === 1) {
            return $this->chatClient->postMessage(
                $command->getRoom(),
                sprintf(
                    "[tag:rfc-vote] [%s](%s) %s",
                    $messages[0]['name'],
                    $messages[0]['href'],
                    $messages[0]['breakdown']
                )
            );
        }

        $message = implode("\n", array_map(function($message) {
            return sprintf(
                '%s %s - %s (%s)',
                self::BULLET,
                $message['name'],
                $message['breakdown'],
                $message['href']
            );
        }, $messages));

        return $this->chatClient->postMessage($command->getRoom(), $message);
    }

    private static function parseVotes(string $html) {
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

                /** @var \DOMElement $vote */
                foreach ($row->getElementsByTagName('td') as $i => $vote) {
                    // Adjust by one to ignore voter name
                    if ($vote->getElementsByTagName('img')->length > 0) {
                        ++$info['votes'][$options[$i - 1]];
                    }
                }
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
            new PluginCommandEndpoint('Search', [$this, 'search'], 'rfcs', 'List RFCs currently in voting, or get the current vote status of a given RFC'),
            new PluginCommandEndpoint('Votes', [$this, 'getRFC'], 'rfc', 'Get the current vote status of a given RFC'),
        ];
    }
}
