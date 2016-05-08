<?php

namespace Room11\Jeeves\Chat;

use Room11\Jeeves\Exception;

class PluginRegistrationFailedException extends Exception
{
    private $plugin;

    public function __construct($message, Plugin $plugin, \Throwable $previous)
    {
        parent::__construct($message, 0, $previous);
        $this->plugin = $plugin;
    }

    public function getPlugin(): Plugin
    {
        return $this->plugin;
    }
}
