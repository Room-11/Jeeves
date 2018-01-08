<?php declare(strict_types=1);

echo exec("nohup sleep 60 > /dev/null 2> /dev/null < /dev/null & echo $!"); sleep(10);
