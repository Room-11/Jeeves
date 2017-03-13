<?php declare(strict_types = 1);

namespace Room11\Jeeves\BuiltIn\Commands;

use Amp\Failure;
use Amp\Promise;
use Room11\Jeeves\Chat\Client\ChatClient;
use Room11\Jeeves\Chat\Client\PostFlags;
use Room11\Jeeves\Chat\Message\Command as CommandMessage;
use Room11\Jeeves\Chat\Room\AlreadyApprovedException;
use Room11\Jeeves\Chat\Room\IdentifierFactory;
use Room11\Jeeves\Chat\Room\InvalidRoomIdentifierException;
use Room11\Jeeves\Chat\Room\PresenceManager;
use Room11\Jeeves\Chat\Room\RoomAlreadyExistsException;
use Room11\Jeeves\Chat\Room\UserAlreadyVotedException;
use Room11\Jeeves\Chat\Room\UserNotAcceptableException;
use Room11\Jeeves\Log\Level;
use Room11\Jeeves\Log\Logger;
use Room11\Jeeves\System\BuiltInCommand;
use Room11\Jeeves\System\BuiltInCommandInfo;
use function Amp\resolve;

class RoomPresence implements BuiltInCommand
{
    private $chatClient;
    private $presenceManager;
    private $identifierFactory;
    private $logger;

    public function __construct(
        ChatClient $chatClient,
        PresenceManager $presenceManager,
        IdentifierFactory $identifierFactory,
        Logger $logger
    ) {
        $this->chatClient = $chatClient;
        $this->presenceManager = $presenceManager;
        $this->identifierFactory = $identifierFactory;
        $this->logger = $logger;
    }

    private function getRoomIdentifierFromArg(string $arg, string $sourceHost)
    {
        if (preg_match('#^[0-9]+$#', $arg)) {
            return $this->identifierFactory->create((int)$arg, $sourceHost);
        }

        if (preg_match('#^https?://' . preg_quote($sourceHost, '#') .'/rooms/([0-9]+)#i', $arg, $match)) {
            return $this->identifierFactory->create((int)$match[1], $sourceHost);
        }

        throw new InvalidRoomIdentifierException('Cannot determine room identifier from argument');
    }

    private function invite(CommandMessage $command)
    {
        if (!$command->hasParameters()) {
            return $this->chatClient->postReply($command, 'If you want to invite me somewhere, you have to tell me where...');
        }

        try {
            $identifier = $this->getRoomIdentifierFromArg($command->getParameter(0), $command->getRoom()->getIdentifier()->getHost());
        } catch (InvalidRoomIdentifierException $e) {
            return $this->chatClient->postReply($command, "Sorry, I can't work out where you are asking me to go");
        }

        if ($identifier->equals($command->getRoom()->getIdentifier())) {
            return $this->chatClient->postReply($command, "Ummm... that's this room?");
        }

        $userId = $command->getUserId();
        $userName = $command->getUserName();

        $this->logger->log(Level::DEBUG, "Invited to {$identifier} by {$userName} (#{$userId})");

        try {
            yield $this->presenceManager->addRoom($identifier, $userId);
            $message = 'See you there shortly! :-)';
        } catch (RoomAlreadyExistsException $e) {
            $message = "I'm already there, I don't need to be invited again";
        } catch (\Throwable $e) {
            $this->logger->log(Level::ERROR, "Error while adding room {$identifier} invited by {$userName} (#{$userId}): {$e}");
            $message = "Something went pretty badly wrong there, I've made a note of it, please report this issue to my maintainers.";
        }

        return $this->chatClient->postReply($command, $message);
    }

    private function approve(CommandMessage $command)
    {
        if ($command->getRoom()->isPermanent()) {
            return $this->chatClient->postReply($command, "This room is my home! I don't need your approval to be here!");
        }

        try {
            $identifier = $command->getRoom()->getIdentifier();

            $requiredVotes = yield $this->presenceManager->getRequiredApproveVoteCount($identifier);
            list($isApproved, $currentVotes) = yield $this->presenceManager->addApproveVote($identifier, $command->getUserId());

            $message = 'Your vote has been recorded. ';
            $message .= $isApproved
                ? 'I have now been approved and am fully active in this room.'
                : ($requiredVotes - $currentVotes) . ' more votes are required to activated me.';
        } catch (AlreadyApprovedException $e) {
            $message = "I've already been activated in this room, but it's nice you know you approve of me :-)";
        } catch (UserNotAcceptableException $e) {
            $message = 'Sorry, only room owners can vote';
        } catch (UserAlreadyVotedException $e) {
            $message = "Sorry, you've already voted, you can't vote again";
        }

        return $this->chatClient->postReply($command, $message, PostFlags::FORCE);
    }

    private function leave(CommandMessage $command)
    {
        if ($command->getRoom()->isPermanent()) {
            return $this->chatClient->postReply($command, "This room is my home! I don't need your approval to be here!");
        }

        try {
            $identifier = $command->getRoom()->getIdentifier();

            yield $this->presenceManager->getRequiredLeaveVoteCount($identifier);
            list($hasLeft) = yield $this->presenceManager->addLeaveVote($identifier, $command->getUserId());

            if ($hasLeft) {
                return null;
            }

            $message = 'Your vote has been recorded. If I get one more vote within an hour I will leave the room.';
        } catch (UserNotAcceptableException $e) {
            $message = 'Sorry, only room owners can vote';
        } catch (UserAlreadyVotedException $e) {
            $message = "Sorry, you've already voted, you can't vote again";
        }

        return $this->chatClient->postReply($command, $message, PostFlags::FORCE);
    }

    public function handleCommand(CommandMessage $command): Promise
    {
        switch ($command->getCommandName()) {
            case 'invite':  return resolve($this->invite($command));
            case 'approve': return resolve($this->approve($command));
            case 'leave':   return resolve($this->leave($command));
        }

        return new Failure(new \LogicException("I don't handle the command '{$command->getCommandName()}'"));
    }

    public function getCommandInfo(): array
    {
        return [
            new BuiltInCommandInfo('invite', "Invite the bot to join a room. This can also be done through the chat web interface."),
            new BuiltInCommandInfo(
                'approve', "Approve the bot for talking in this room. Room owners only.",
                BuiltInCommandInfo::REQUIRE_ADMIN_USER | BuiltInCommandInfo::ALLOW_UNAPPROVED_ROOM
            ),
            new BuiltInCommandInfo(
                'leave', "Ask the bot to leave the room. Room owners only.",
                BuiltInCommandInfo::REQUIRE_ADMIN_USER | BuiltInCommandInfo::ALLOW_UNAPPROVED_ROOM
            ),
        ];
    }
}
