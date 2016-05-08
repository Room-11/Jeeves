<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

class PluginCommandEndpoint
{
    private $name;
    private $callback;
    private $defaultCommand;

    /**
     * @param string $name
     * @param callable $callback
     * @param string|null $defaultCommand
     */
    public function __construct(string $name, callable $callback, $defaultCommand = null)
    {
        $this->name = $name;
        $this->callback = $callback;
        $this->defaultCommand = $defaultCommand !== null ? (string)$defaultCommand : null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * @return string|null
     */
    public function getDefaultCommand()
    {
        return $this->defaultCommand;
    }
}
