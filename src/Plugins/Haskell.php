<?php

namespace Room11\Jeeves\Plugins;

use Amp\Artax\FormBody;
use Amp\Artax\HttpClient;
use Amp\Artax\Request;
use Amp\Artax\Response;
use Room11\StackChat\Client\Client;
use Room11\Jeeves\Chat\Command;
use Room11\Jeeves\System\PluginCommandEndpoint;

class Haskell extends BasePlugin
{
    private const USAGE = "Usage example: !!haskell sin(pi/2)";

    private $chatClient;
    private $httpClient;

    public function __construct(Client $chatClient, HttpClient $httpClient)
    {
        $this->chatClient = $chatClient;
        $this->httpClient = $httpClient;
    }

    private function getMessage(Response $response): string
    {
        if ($response->getStatus() !== 200) {
            return "Something seems to be not working fine. Please visit https://tryhaskell.org/ directly.";
        }

        $data = json_decode($response->getBody(), true);

        if (isset($data["success"])) {
            $output = str_replace("`", "\\`", preg_replace("~\\s~", " ", implode(" ", $data["success"]["stdout"])));

            return sprintf(
                "Return value: `%s` — Output: %s",
                str_replace("`", "\\`", $data["success"]["value"]),
                $output ? "`{$output}`" : "*none*"
            );
        } else if (isset($data["error"])) {
            return sprintf(
                "Error: `%s`",
                str_replace("`", "\\`", preg_replace("~\\s~", " ", $data["error"]))
            );
        }

        return "Something went wrong, it has to be fixed in code.";
    }

    public function run(Command $command)
    {
        if (!$command->hasParameters()) {
            return $this->chatClient->postMessage($command, self::USAGE);
        }

        $form = (new FormBody)
            ->addField("exp", implode(" ", $command->getParameters()));

        $request = (new Request)
            ->setMethod("POST")
            ->setUri("https://tryhaskell.org/eval")
            ->setBody($form);

        $response = yield $this->httpClient->request($request);

        return $this->chatClient->postMessage($command, $this->getMessage($response));
    }

    public function getDescription(): string
    {
        return 'Run haskell code.';
    }

    /**
     * @return PluginCommandEndpoint[]
     */
    public function getCommandEndpoints(): array
    {
        return [new PluginCommandEndpoint('Haskell', [$this, 'run'], 'haskell')];
    }
}
