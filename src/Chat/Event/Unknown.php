<?php  declare(strict_types=1);
namespace Room11\Jeeves\Chat\Event;

class Unknown extends BaseEvent
{
    public function __construct(array $data)
    {
        parent::__construct($data);
    }
}
