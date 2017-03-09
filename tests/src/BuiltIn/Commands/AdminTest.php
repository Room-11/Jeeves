<?php declare(strict_types = 1);
 
namespace Room11\Jeeves\Tests\BuiltIn\Commands;
 
use Amp\Success;
use Amp\Artax\HttpClient;
use Room11\Jeeves\BuiltIn\Commands\Admin;
use Room11\Jeeves\Chat\Entities\PostedMessage;
use Room11\Jeeves\Chat\Entities\ChatUser;
use Room11\Jeeves\Chat\Client\PostedMessageTracker;
use Room11\Jeeves\Storage\Admin as AdminStorage;
use Room11\Jeeves\System\BuiltInCommandInfo;
 
class AdminTest extends AbstractCommandTest
{
    private $admin;
    private $httpClient;
 
    public function setUp()
    {
        parent::setUp();
 
        $this->httpClient = $this->createMock(HttpClient::class);
        $this->admin = $this->createMock(AdminStorage::class);
 
        $this->builtIn = new Admin(
            $this->client,
            $this->httpClient,
            $this->admin
        );

        $this->command
            ->method('getUserId')
            ->will($this->returnValue(123))
        ;
    }

    public function testRemoveCommand()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(true);
        $this->setCommandParameters([[0, 'remove'], [1, 456]]);
        $this->setAdminsInStorage([], [456]);

        $this->admin
            ->method('remove')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo(456)
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->expectMessage("User removed from the admin list.");
        \amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testRemoveCommandAlreadyOwner()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(true);
        $this->setCommandParameters([[0, 'remove'], [1, 456]]);
        $this->setAdminsInStorage([456], []);

        $this->expectReply("User is a room owner and has implicit admin rights.");
        \amp\wait($this->builtIn->handleCommand($this->command));         
    }

    public function testRemoveCommandNotAdmin()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(true);
        $this->setCommandParameters([[0, 'remove'], [1, 456]]);
        $this->setAdminsInStorage([], []);

        $this->expectReply("User not currently on admin list.");
        \amp\wait($this->builtIn->handleCommand($this->command));        
    }

    public function testAddCommand()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(true);
        $this->setCommandParameters([[0, 'add'], [1, 456]]);
        $this->setAdminsInStorage([], []);

        $this->admin
            ->method('add')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo(456)
            )
            ->will($this->returnValue(new Success(true)))
        ;

        $this->expectMessage("User added to the admin list.");
        \amp\wait($this->builtIn->handleCommand($this->command)); 
    }

    public function testAddCommandAlreadyOwner()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(true);
        $this->setCommandParameters([[0, 'add'], [1, 456]]);
        $this->setAdminsInStorage([456], []);

        $this->expectReply("User is a room owner and has implicit admin rights.");
        \amp\wait($this->builtIn->handleCommand($this->command));        
    }

    public function testAddCommandAlreadyAdmin()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(true);
        $this->setCommandParameters([[0, 'add'], [1, 456]]);
        $this->setAdminsInStorage([], [456]);

        $this->expectReply("User already on admin list.");
        \amp\wait($this->builtIn->handleCommand($this->command));
    }

    public function testCommandWithoutAdmin()
    {
        $this->setRoomApproval(true);
        $this->setAdmin(false);
        $this->expectReply("I'm sorry Dave, I'm afraid I can't do that");

        \amp\wait($this->builtIn->handleCommand($this->command));
    }
 
    public function testCommandList()
    {
        $this->setRoomApproval(true);
        $this->setCommandParameter(0, 'list');
        $this->setAdminsInStorage([123, 456], [789, 101112]);

        $this->client
            ->method('getChatUsers')
            ->will($this->returnValue(new Success([
                    new ChatUser(['id' => 123, 'name' => 'firstOwner']), 
                    new ChatUser(['id' => 456, 'name' => 'secondOwner']), 
                    new ChatUser(['id' => 789, 'name' => 'firstAdmin']), 
                    new ChatUser(['id' => 101112, 'name' => 'secondAdmin'])
                ])
            ))
        ;

        $this->expectMessage(
            "firstAdmin, *firstOwner*, secondAdmin, *secondOwner*"
        );

        \amp\wait($this->builtIn->handleCommand($this->command));
    }
 
    public function testCommandListWithNoAdmins()
    {
        $this->setRoomApproval(true);
        $this->setCommandParameter(0, 'list');
        $this->setAdminsInStorage([], []);
 
        $this->expectMessage('There are no registered admins');
 
        \amp\wait($this->builtIn->handleCommand($this->command));
 
    }
 
    public function testCommandHelp()
    {
        $this->setRoomApproval(true);
        $this->setCommandParameter(0, 'help');
        $this->expectMessage($this->builtIn::COMMAND_HELP_TEXT);
 
        \amp\wait($this->builtIn->handleCommand($this->command));
    }
 
    public function testCommandWithoutApproval()
    {
        $this->setRoomApproval(false);
 
        $response = \amp\wait($this->builtIn->handleCommand($this->command));
        $this->assertNull($response); 
    }
    
    public function testCommandInfo()
    {
        $this->assertInstanceOf(BuiltInCommandInfo::class, $this->builtIn->getCommandInfo()[0]);
    }

    private function setAdmin(bool $isAdmin)
    {
        $this->admin
            ->method('isAdmin')
            ->with(
                $this->identicalTo($this->room),
                $this->identicalTo(123)
            )
            ->will($this->returnValue(new Success($isAdmin)))
        ;
    }
 
    private function setAdminsInStorage(array $owners, array $admins)
    {
        $this->admin
            ->method('getAll')
            ->with($this->identicalTo($this->room))
            ->will($this->returnValue(new Success([
                    'owners' => $owners, 
                    'admins' => $admins
                ])
            ))
        ;        
    }

    private function setCommandParameters(array $parameters)
    {
        $this->command
            ->method('getParameter')
            ->will($this->returnValueMap($parameters))
        ;
    }

    private function setCommandParameter(int $parameter, $value)
    {
        $this->command
            ->method('getParameter')
            ->with($this->equalTo($parameter))
            ->will($this->returnValue($value))
        ;
    }

    private function expectReply(string $message)
    {
        $this->client
            ->expects($this->once())
            ->method('postReply')
            ->with(
                $this->identicalTo($this->command),
                $this->identicalTo($message)
            )
            ->will($this->returnValue(new Success(true)))
        ;
    }
 
    private function expectMessage(string $message)
    {
        $this->client
            ->expects($this->once())
            ->method('postMessage')
            ->with(
                $this->identicalTo($this->command),
                $this->equalTo($message)
            )
            ->will($this->returnValue(new Success(true)))
        ;       
    }
}
