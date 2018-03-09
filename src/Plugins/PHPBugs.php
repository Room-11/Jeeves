<?php declare(strict_types=1);

namespace Room11\Jeeves\Plugins;

use Amp\Artax\HttpClient;
use Amp\Artax\Response as HttpResponse;
use Room11\Jeeves\Storage\KeyValue as KeyValueStore;
use Room11\Jeeves\System\PluginCommandEndpoint;
use Room11\StackChat\Client\Client as ChatClient;
use Room11\StackChat\Room\Room as ChatRoom;
use function Amp\cancel;
use function Amp\repeat;
use function Amp\resolve;
use function Room11\DOMUtils\domdocument_load_html;

class PHPBugs extends BasePlugin
{
    private const RECENT_BUGS = "https://bugs.php.net/search.php?search_for=&boolean=0&limit=30&order_by=id&direction=DESC&cmd=display&status=All&bug_type=All&project=PHP&php_os=&phpver=&cve_id=&assign=&author_email=&bug_age=0&bug_updated=0";
    private const DATA_KEY = "plugin.phpbugs.lastId";

    private $chatClient;
    private $httpClient;
    private $pluginData;
    private $rooms;

    private $watcher;

    public function __construct(ChatClient $chatClient, HttpClient $httpClient, KeyValueStore $pluginData)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
        $this->pluginData = $pluginData;
        $this->rooms = [];
    }

    public function enableForRoom(ChatRoom $room, bool $persist = true)
    {
        $this->rooms[$room->getIdentString()] = $room;

        if ($this->watcher !== null) {
            return null;
        }

        $this->watcher = repeat(function () {
            return $this->checkBugs();
        }, 300000);

        return resolve($this->checkBugs());
    }

    public function disableForRoom(ChatRoom $room, bool $persist)
    {
        unset($this->rooms[$room->getIdentString()]);

        if (!empty($this->rooms) || !$this->watcher) {
            return null;
        }

        cancel($this->watcher);
        $this->watcher = null;

        try {
            return $this->pluginData->unset(self::DATA_KEY);
        } catch (\LogicException $e) {
            return null; /* don't care */
        }
    }

    private function checkBugs()
    {
        $bugs = yield from $this->getRecentBugs();

        if (!$bugs) {
            return null;
        }

        if (!yield $this->pluginData->exists(self::DATA_KEY)) {
            yield $this->pluginData->set(self::DATA_KEY, $bugs[0]["id"]);
            return null;
        }

        $lastId = yield $this->pluginData->get(self::DATA_KEY);
        yield $this->pluginData->set(self::DATA_KEY, $bugs[0]["id"]);

        foreach ($bugs as $bug) {
            if ($bug["id"] <= $lastId) {
                return null;
            }

            foreach ($this->rooms as $room) {
                yield $this->chatClient->postMessage($room, \sprintf(
                    "[tag:php] [tag:%s] %s – [#%d](%s)",
                    $bug["type"],
                    $bug["title"],
                    $bug["id"],
                    $bug["url"]
                ));
            }
        }

        return null;
    }

    private function getRecentBugs()
    {
        /** @var HttpResponse $response */
        $response = yield $this->httpClient->request(self::RECENT_BUGS);

        if ($response->getStatus() !== 200) {
            return false;
        }

        return $this->parseBugs($response->getBody());
    }

    private function parseBugs(string $body)
    {
        static $query = "/html/body/table[2]/tr/td/table/tr[@valign]";
        static $tags = [
            "Doc" => "documentation",
            "Req" => "feature-request",
            "Sec Bug" => "security"
        ];

        $dom = domdocument_load_html($body);
        $xpath = new \DOMXPath($dom);

        $bugs = [];

        foreach ($xpath->query($query) as $row) {
            $cells = $row->getElementsByTagName("td");
            $id = (int) $cells->item(0)->firstChild->textContent;
            $type = $tags[$cells->item(4)->textContent] ?? "bug";

            $bugs[] = [
                "id" => $id,
                "title" => $cells->item(8)->textContent ?: "*none*",
                "type" => $type,
                "url" => "https://bugs.php.net/bug.php?id={$id}",
            ];
        }

        return $bugs;
    }

    /**
     * @inheritDoc
     */
    public function getName(): string
    {
        return 'PHPBugs';
    }

    public function getDescription(): string
    {
        return 'Pushes new PHP.net bugs.';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [];
    }
}
