<?php declare(strict_types = 1);

namespace Room11\Jeeves;

use SebastianBergmann\Version as SebastianVersion;

function get_current_version(): VersionIdentifier
{
    $version = (new SebastianVersion(VERSION, APP_BASE))->getVersion();

    if (!preg_match('@^(.+?)(?:-([0-9]+)-g([0-9a-f]+))?$@', $version, $match)) {
        throw new InvalidVersionStringException('Invalid version string: ' . $version);
    }

    return new VersionIdentifier($match[0], $match[1], (int)($match[2] ?? 0), $match[3] ?? '');
}

function dateinterval_to_string(\DateInterval $interval, string $precision = 's'): string
{
    static $unitNames = [
        'y' => 'year', 'm' => 'month', 'd' => 'day',
        'h' => 'hour', 'i' => 'minute', 's' => 'second',
    ];

    // because apparently DateInterval is pointless
    $now = new \DateTimeImmutable();
    $interval = $now->diff($now->add($interval));

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

    if (empty($parts)) {
        return '0 seconds';
    }

    $last = array_pop($parts);

    return !empty($parts) ?
        implode(', ', $parts) . ' and ' . $last
        : $last;
}

function normalize_stack_exchange_url(string $url): string
{
    static $questionExpr, $answerExpr;
    static $domains = [
        'stackoverflow\.com',
        'superuser\.com',
        'serverfault\.com',
        '[a-z0-9]+\.stackexchange\.com',
    ];

    $questionOrAnswerExpr = $questionExpr ?? '~^https?://(?:www\.|meta\.)?(' . implode('|', $domains) . ')/q(?:uestions)?/([0-9]+)(?:[^#]+#([0-9]+))?~';
    $answerExpr = $answerExpr ?? '#^https?://(?:www\.|meta\.)?(' . implode('|', $domains) . ')/a/([0-9]+)#';

    if (preg_match($questionOrAnswerExpr, $url, $match)) {
        return !empty($match[3])
            ? 'https://' . $match[1] . '/a/' . $match[3]
            : 'https://' . $match[1] . '/q/' . $match[2];
    }

    if (preg_match($answerExpr, $url, $match)) {
        return 'https://' . $match[1] . '/a/' . $match[2];
    }

    throw new InvalidStackExchangeUrlException('Unrecognised Stack Exchange URL format');
}

function text_contains_ping(string $text, string $userName): bool
{
    return (bool)\preg_match('#@' . \preg_quote($userName, '#') . '(?:[\s,.\'?!;:<>\#@~{}"%^&*-]|$)#iu', $text);
}
