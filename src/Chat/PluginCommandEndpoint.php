<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat;

class PluginCommandEndpoint
{
    private $name;
    private $callback;
    private $defaultCommand;
    private $description;

    /**
     * @param string $name
     * @param callable $callback
     * @param string|null $defaultCommand
     * @param string|null $description
     */
    public function __construct(string $name, callable $callback, $defaultCommand = null, $description = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->callback = $callback;
        $this->defaultCommand = $defaultCommand !== null ? (string)$defaultCommand : null;
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

    /**
     * @return string|null
     */
    public function getDescription()
    {
        return $this->description;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
