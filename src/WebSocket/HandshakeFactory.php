<?php  declare(strict_types=1);
namespace Room11\Jeeves\WebSocket;

use Amp\Websocket\Handshake;

class HandshakeFactory
{
    public function build(string $url)
    {
        return new Handshake($url);
    }
}
