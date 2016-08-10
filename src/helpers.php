<?php declare(strict_types = 1);

namespace Room11\Jeeves;

const DNS_NAME_EXPR = '(?:(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]*[a-zA-Z0-9])?)\.)*(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]*[a-zA-Z0-9])?)';
const ROOM_IDENTIFIER_EXPR = '(' . DNS_NAME_EXPR . ')#([0-9]+)';

function dateinterval_to_string(\DateInterval $interval, string $precision = 's'): string
{
    static $unitNames = [
        'y' => 'year', 'm' => 'month', 'd' => 'day',
        'h' => 'hour', 'i' => 'minute', 's' => 'second',
    ];

    $precision = strtolower($precision);

    if (!isset($unitNames[$precision])) {
        throw new \InvalidArgumentException('Invalid precision value: ' . $precision);
    }

    $values = [
        'y' => $interval->y, 'm' => $interval->m, 'd' => $interval->d,
        'h' => $interval->h, 'i' => $interval->i, 's' => $interval->s,
    ];

    $parts = [];

    foreach ($values as $unit => $value) {
        if ($value) {
            $parts[] = sprintf('%d %s%s', $value, $unitNames[$unit], $value === 1 ? '' : 's');
        }

        if ($unit === $precision) {
            break;
        }
    }

    $last = array_pop($parts);

    return $parts ?
        implode(', ', $parts) . ' and ' . $last
        : $last;
}
