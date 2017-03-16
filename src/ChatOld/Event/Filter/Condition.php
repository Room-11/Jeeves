<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event\Filter;

class Condition
{
    const VALUE_TYPE_SCALAR = 1;
    const VALUE_TYPE_SET = 2;

    private $fieldName;
    private $valueType;
    private $scalarValue;
    private $setName;
    private $setMembers;

    public function __construct(string $fieldName, int $valueType, string $scalarValue, string $setName, array $setMembers)
    {
        $this->fieldName   = $fieldName;
        $this->valueType   = $valueType;
        $this->scalarValue = $scalarValue;
        $this->setName     = $setName;
        $this->setMembers  = $setMembers;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getValueType(): int
    {
        return $this->valueType;
    }

    public function getScalarValue(): string
    {
        return $this->scalarValue;
    }

    public function getSetName(): string
    {
        return $this->setName;
    }

    /**
     * @return string[]
     */
    public function getSetMembers(): array
    {
        return $this->setMembers;
    }
}
