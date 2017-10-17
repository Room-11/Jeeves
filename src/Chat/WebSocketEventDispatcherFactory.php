<?php declare(strict_types = 1);

namespace Room11\Jeeves\Chat;

use Psr\Log\LoggerInterface as Logger;
use Room11\Jeeves\System\BuiltInActionManager;
use Room11\Jeeves\System\PluginManager;
use Room11\StackChat\Auth\SessionTracker;

class WebSocketEventDispatcherFactory
{
    private const BUFFER_SIZE = 20;

    private $pluginManager;
    private $builtInActionManager;
    private $commandFactory;
    private $sessions;
    private $logger;
    private $devMode;

    private $recentGlobalEventBuffer;

    public function __construct(
        PluginManager $pluginManager,
        BuiltInActionManager $builtInActionManager,
        CommandFactory $commandFactory,
        SessionTracker $sessions,
        Logger $logger,
        bool $devMode
    ) {
        $this->pluginManager = $pluginManager;
        $this->builtInActionManager = $builtInActionManager;
        $this->commandFactory = $commandFactory;
        $this->sessions = $sessions;
        $this->logger = $logger;
        $this->devMode = $devMode;

        $this->recentGlobalEventBuffer = new FixedSizeEventBuffer(self::BUFFER_SIZE);
    }

    public function create(PresenceManager $presenceManager): WebSocketEventDispatcher
    {
        return new WebSocketEventDispatcher(
            $this->pluginManager, $this->builtInActionManager, $this->commandFactory, $this->sessions,
            $presenceManager, $this->logger, $this->recentGlobalEventBuffer, $this->devMode
        );
    }
}
