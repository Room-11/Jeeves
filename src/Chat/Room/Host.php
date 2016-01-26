<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat\Room;

class Host {
    private $hostname;
    private $secure;

    public function __construct(string $hostname, bool $secure) {
        $this->hostname = $hostname;
        $this->secure = $secure;
    }

    public function isSecure() {
        return $this->secure;
    }

    public function getHostname() {
        return $this->hostname;
    }
}