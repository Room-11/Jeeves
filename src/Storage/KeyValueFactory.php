<?php

namespace Room11\Jeeves\Storage;

interface KeyValueFactory
{
    function build(string $partitionName): KeyValue;
}
