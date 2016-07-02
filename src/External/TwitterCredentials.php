<?php declare(strict_types=1);

namespace Room11\Jeeves\External;

class TwitterCredentials {
    private $consumerKey;

    private $consumerSecret;

    private $accessToken;

    private $accessTokenSecret;

    public function __construct(
        string $consumerKey,
        string $consumerSecret,
        string $accessToken,
        string $accessTokenSecret
    )
    {
        $this->consumerKey       = $consumerKey;
        $this->consumerSecret    = $consumerSecret;
        $this->accessToken       = $accessToken;
        $this->accessTokenSecret = $accessTokenSecret;
    }

    public function getConsumerKey(): string {
        return $this->consumerKey;
    }

    public function getConsumerSecret(): string {
        return $this->consumerSecret;
    }

    public function getAccessToken(): string {
        return $this->accessToken;
    }

    public function getAccessTokenSecret(): string {
        return $this->accessTokenSecret;
    }
}
