<?php

namespace Room11\Jeeves\Storage\File;

use Room11\Jeeves\Storage\KeyValue as KeyValueStorage;
use Room11\Jeeves\Storage\KeyValueFactory as KeyValueStorageFactory;

class KeyValueFactory implements KeyValueStorageFactory
{
    private $accessor;
    private $dataFileTemplate;

    public function __construct(JsonFileAccessor $accessor, string $dataFileTemplate)
    {
        $this->accessor = $accessor;
        $this->dataFileTemplate = $dataFileTemplate;
    }

    public function build(string $partitionName): KeyValueStorage
    {
        return new KeyValue($this->accessor, $this->dataFileTemplate, $partitionName);
    }
}
