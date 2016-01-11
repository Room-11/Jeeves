<?php

namespace Room11\Jeeves\Chat\Message;

class Factory
{
    public function build(array $data)
    {
        $message = reset($data);

        if (isset($message['e']) && $message['e'][0]['event_type'] === 1) {
            return new NewMessage($message['e'][0]);
        }

        return new Unknown($message);
    }
}
