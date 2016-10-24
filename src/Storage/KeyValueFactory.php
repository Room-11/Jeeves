<?php

namespace Room11\Jeeves\Storage;

interface KeyValueFactory
{
    public function build(string $partitionName): KeyValue;
}
