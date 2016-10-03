<?php declare(strict_types=1);

namespace Room11\Jeeves\Chat\Event\Filter;

use Room11\Jeeves\Chat\Event\Event;
use Room11\Jeeves\Chat\Event\GlobalEvent;
use Room11\Jeeves\Chat\Event\MessageEvent;
use Room11\Jeeves\Chat\Event\RoomSourcedEvent;
use Room11\Jeeves\Chat\Event\UserSourcedEvent;
use const Room11\Jeeves\ROOM_IDENTIFIER_EXPR;

class Compiler
{
    const FIELD_TYPE = 'type';
    const FIELD_ROOM = 'room';
    const FIELD_CLASS = 'class';

    private function getValidatedConditions(array $conditions): array
    {
        $keyedConditions = [];

        foreach ($conditions as $condition) {
            $fieldName = $condition->getFieldName();

            if (isset($keyedConditions[$fieldName])) {
                throw new CompileErrorException('Field name ' . $fieldName . ' specified multiple times');
            }

            $keyedConditions[$fieldName] = $condition;
        }

        return $keyedConditions;
    }

    private function getValidatedInt(string $int, string $field): int
    {
        if (!preg_match('#^[0-9]+$#', $int)) {
            throw new CompileErrorException('Expecting integer value for field ' . $field);
        }

        return (int)$int;
    }

    private function processType(Condition $condition): array
    {
        if ($condition->getValueType() === Condition::VALUE_TYPE_SCALAR) {
            $typeId = $this->getValidatedInt($condition->getScalarValue(), self::FIELD_TYPE);

            return [[$typeId], function(Event $event) use($typeId) { return $event->getTypeId() === $typeId; }];
        }

        if ($condition->getSetName() !== 'any') {
            throw new CompileErrorException(
                "Unknown set type '{$condition->getSetName()}' for field " . self::FIELD_TYPE
            );
        }

        $typeIds = [];
        foreach ($condition->getSetMembers() as $typeId) {
            $typeId = $this->getValidatedInt($typeId, self::FIELD_TYPE);
            $typeIds[$typeId] = $typeId;
        }

        if ($typeIds === []) {
            throw new CompileErrorException('Unexpected empty set for field ' . self::FIELD_TYPE);
        }

        return [$typeIds, function(Event $event) use($typeIds) { return isset($typeIds[$event->getTypeId()]); }];
    }

    private function processRoom(Condition $condition): array
    {
        if ($condition->getValueType() === Condition::VALUE_TYPE_SCALAR) {
            if (!preg_match('/^' . ROOM_IDENTIFIER_EXPR . '$/i', $condition->getScalarValue(), $match)) {
                throw new CompileErrorException(
                    "Invalid room identifier '{$condition->getScalarValue()}' for field " . self::FIELD_ROOM
                );
            }

            $fullId = strtolower($match[0]);
            $host = strtolower($match[1]);
            $roomId = (int)$match[2];

            return [[$fullId], function(Event $event) use($host, $roomId) {
                if (!$event instanceof RoomSourcedEvent) {
                    return false;
                }

                $id = $event->getRoom()->getIdentifier();

                return $id->getId() === $roomId && $id->getHost() === $host;
            }];
        }

        if ($condition->getSetName() !== 'any') {
            throw new CompileErrorException(
                "Unknown set type '{$condition->getSetName()}' for field " . self::FIELD_ROOM
            );
        }

        $roomIds = $rooms = [];
        foreach ($condition->getSetMembers() as $roomSpec) {
            if (!preg_match('/^' . ROOM_IDENTIFIER_EXPR . '$/i', $roomSpec, $match)) {
                throw new CompileErrorException("
                    Invalid room identifier '{$condition->getScalarValue()}' for field " . self::FIELD_ROOM
                );
            }

            $id = strtolower($match[0]);
            $rooms[] = $id;
            $roomIds[$id] = [strtolower($match[1]), (int)$match[2]];
        }

        return [$rooms, function(Event $event) use($roomIds) {
            if (!$event instanceof RoomSourcedEvent) {
                return false;
            }

            $id = $event->getRoom()->getIdentifier();
            $idStr = $id->getHost() . '#' . $id->getId();

            return isset($roomIds[$idStr]);
        }];
    }

    private function processClass(Condition $condition): callable
    {
        static $classMap = [
            'user'    => UserSourcedEvent::class,
            'room'    => RoomSourcedEvent::class,
            'global'  => GlobalEvent::class,
            'message' => MessageEvent::class,
        ];

        if ($condition->getValueType() === Condition::VALUE_TYPE_SCALAR) {
            $class = $condition->getScalarValue();

            if (!isset($classMap[$class])) {
                throw new CompileErrorException("Invalid class name '{$class}'");
            }

            $className = $classMap[$class];

            return [[$class], function(Event $event) use($className) { return $event instanceof $className; }];
        }

        $setName = $condition->getSetName();
        $classes = [];

        foreach ($condition->getSetMembers() as $class) {
            if (!isset($classMap[$class])) {
                throw new CompileErrorException("Invalid class name '{$class}'");
            }

            $classes[$classMap[$class]] = $classMap[$class];
        }

        switch ($condition->getSetName()) {
            case 'any':
                return function(Event $event) use($classes) {
                    foreach ($classes as $class) {
                        if ($event instanceof $class) {
                            return true;
                        }
                    }

                    return false;
                };
                break;

            case 'all':
                return function(Event $event) use($classes) {
                    foreach ($classes as $class) {
                        if (!$event instanceof $class) {
                            return false;
                        }
                    }

                    return true;
                };
                break;
        }

        throw new CompileErrorException("Unknown set type '{$setName}' for field " . self::FIELD_CLASS);
    }

    /**
     * @param Condition[] $conditions
     * @return array
     * @throws CompileErrorException
     */
    public function compile(array $conditions): array
    {
        $predicates = $types = $rooms = [];

        $conditions = $this->getValidatedConditions($conditions);
        
        if (isset($conditions[self::FIELD_TYPE])) {
            list($types, $predicate) = $this->processType($conditions[self::FIELD_TYPE]);
            $predicates[] = $predicate;
        }

        if (isset($conditions[self::FIELD_ROOM])) {
            list($rooms, $predicate) = $this->processRoom($conditions[self::FIELD_ROOM]);
            $predicates[] = $predicate;
        }

        if (isset($conditions[self::FIELD_CLASS])) {
            $predicates[] = $this->processClass($conditions[self::FIELD_CLASS]);
        }

        return [$predicates, $types, $rooms];
    }
}
