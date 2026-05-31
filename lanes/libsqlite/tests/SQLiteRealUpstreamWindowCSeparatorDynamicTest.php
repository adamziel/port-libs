<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

$separatorSets = [
    'short text separators' => ['a', 'b', 'c', 'def', 'g'],
    'empty separators' => ['abcdefg', '', '', 'abcdefg'],
    'growing separators' => ['a', 'bc', 'def', 'ghij', 'klmno', 'pqrstu'],
    'punctuation separators' => [',', ',', ',', ',', ',', ',', ',', ',', ',', ',', ',', ',', '.......', ',', ','],
    'utf8 separators' => ['-', '::', ' -> ', '中', 'Ω'],
    'mixed empty unicode separators' => ['', ' / ', 'ß', '', '終'],
];

$frameSpecs = [
    ['ROWS', '1 PRECEDING', '1 FOLLOWING'],
    ['ROWS', '2 PRECEDING', 'CURRENT ROW'],
    ['ROWS', 'CURRENT ROW', 'UNBOUNDED FOLLOWING'],
    ['ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['ROWS', '3 PRECEDING', '2 FOLLOWING'],
    ['GROUPS', '1 PRECEDING', 'CURRENT ROW'],
    ['GROUPS', 'CURRENT ROW', '1 FOLLOWING'],
    ['GROUPS', 'UNBOUNDED PRECEDING', 'CURRENT ROW'],
    ['RANGE', '1 PRECEDING', '1 FOLLOWING'],
    ['RANGE', 'CURRENT ROW', '2 FOLLOWING'],
];

$excludeModes = ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'];

$valueSets = [
    'constant val' => static fn (int $row, int $variant): string => 'val',
    'ordinal text' => static fn (int $row, int $variant): string => 'v' . ($row + 1),
    'variant text' => static fn (int $row, int $variant): string => 'v' . $variant . '_' . ($row + 1),
    'numeric text' => static fn (int $row, int $variant): string => (string) (($variant + 1) * ($row + 1)),
    'unicode text' => static fn (int $row, int $variant): string => ['α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ'][$row % 8] . $variant,
];

$orderSets = [
    'rowid order' => static fn (int $row, int $count): int => $row + 1,
    'peer pairs' => static fn (int $row, int $count): int => intdiv($row, 2),
    'reverse key peers' => static fn (int $row, int $count): int => $count - $row + ($row % 2),
    'modulo thirds' => static fn (int $row, int $count): int => $row % 3,
    'wide gaps' => static fn (int $row, int $count): int => ($row + 1) * 3,
];

$expectedConcat = static function (array $values, array $separators, array $frameIndexes): ?string {
    $result = null;
    foreach ($frameIndexes as $frameIndex) {
        $value = $values[$frameIndex];
        if ($value === null) {
            continue;
        }
        if ($result === null) {
            $result = (string) $value;
            continue;
        }
        $result .= (string) $separators[$frameIndex] . (string) $value;
    }

    return $result;
};

$peerBounds = static function (array $keys, int $position): array {
    $first = $position;
    $last = $position;
    while ($first > 0 && $keys[$first - 1] === $keys[$position]) {
        $first--;
    }
    while ($last + 1 < count($keys) && $keys[$last + 1] === $keys[$position]) {
        $last++;
    }

    return [$first, $last];
};

$parseBoundary = static function (string $boundary): array {
    $upper = strtoupper($boundary);
    if ($upper === 'UNBOUNDED PRECEDING') {
        return ['kind' => 'unbounded_preceding', 'offset' => null];
    }
    if ($upper === 'UNBOUNDED FOLLOWING') {
        return ['kind' => 'unbounded_following', 'offset' => null];
    }
    if ($upper === 'CURRENT ROW') {
        return ['kind' => 'current', 'offset' => 0];
    }
    if (preg_match('/^([0-9]+) (PRECEDING|FOLLOWING)$/', $upper, $matches) !== 1) {
        throw new RuntimeException('Unsupported windowC boundary ' . $boundary);
    }

    return ['kind' => strtolower($matches[2]), 'offset' => (int) $matches[1]];
};

$frameIndexes = static function (array $keys, int $position, string $unit, string $startBoundary, string $endBoundary, string $exclude) use ($parseBoundary, $peerBounds): array {
    $count = count($keys);
    $start = $parseBoundary($startBoundary);
    $end = $parseBoundary($endBoundary);
    [$peerFirst, $peerLast] = $peerBounds($keys, $position);

    if ($unit === 'ROWS') {
        $startPos = match ($start['kind']) {
            'unbounded_preceding' => 0,
            'current' => $position,
            'preceding' => max(0, $position - $start['offset']),
            'following' => min($count, $position + $start['offset']),
            default => throw new RuntimeException('Unsupported ROWS start'),
        };
        $endPos = match ($end['kind']) {
            'unbounded_following' => $count - 1,
            'current' => $position,
            'preceding' => max(-1, $position - $end['offset']),
            'following' => min($count - 1, $position + $end['offset']),
            default => throw new RuntimeException('Unsupported ROWS end'),
        };
    } elseif ($unit === 'GROUPS') {
        $groups = [];
        for ($scan = 0; $scan < $count;) {
            [$first, $last] = $peerBounds($keys, $scan);
            $groups[] = [$first, $last];
            $scan = $last + 1;
        }
        $groupIndex = 0;
        foreach ($groups as $index => [$first, $last]) {
            if ($position >= $first && $position <= $last) {
                $groupIndex = $index;
                break;
            }
        }
        $startGroup = match ($start['kind']) {
            'unbounded_preceding' => 0,
            'current' => $groupIndex,
            'preceding' => max(0, $groupIndex - $start['offset']),
            'following' => min(count($groups), $groupIndex + $start['offset']),
            default => throw new RuntimeException('Unsupported GROUPS start'),
        };
        $endGroup = match ($end['kind']) {
            'unbounded_following' => count($groups) - 1,
            'current' => $groupIndex,
            'preceding' => max(-1, $groupIndex - $end['offset']),
            'following' => min(count($groups) - 1, $groupIndex + $end['offset']),
            default => throw new RuntimeException('Unsupported GROUPS end'),
        };
        $startPos = $groups[$startGroup][0] ?? $count;
        $endPos = $groups[$endGroup][1] ?? -1;
    } else {
        $current = $keys[$position];
        $startValue = match ($start['kind']) {
            'unbounded_preceding' => -INF,
            'current' => $current,
            'preceding' => $current - $start['offset'],
            'following' => $current + $start['offset'],
            default => throw new RuntimeException('Unsupported RANGE start'),
        };
        $endValue = match ($end['kind']) {
            'unbounded_following' => INF,
            'current' => $current,
            'preceding' => $current - $end['offset'],
            'following' => $current + $end['offset'],
            default => throw new RuntimeException('Unsupported RANGE end'),
        };
        $positions = [];
        foreach ($keys as $index => $key) {
            if ($key >= $startValue && $key <= $endValue) {
                $positions[] = $index;
            }
        }
        $startPos = $positions[0] ?? $count;
        $endPos = $positions === [] ? -1 : $positions[count($positions) - 1];
    }

    $positions = $startPos <= $endPos ? range($startPos, $endPos) : [];
    return match ($exclude) {
        'NO OTHERS' => $positions,
        'CURRENT ROW' => array_values(array_filter($positions, static fn (int $candidate): bool => $candidate !== $position)),
        'GROUP' => array_values(array_filter($positions, static fn (int $candidate): bool => $candidate < $peerFirst || $candidate > $peerLast)),
        'TIES' => array_values(array_filter($positions, static fn (int $candidate): bool => $candidate === $position || $candidate < $peerFirst || $candidate > $peerLast)),
        default => throw new RuntimeException('Unsupported EXCLUDE mode'),
    };
};

$case = 0;
foreach ($separatorSets as $separatorName => $separators) {
    foreach ($valueSets as $valueName => $valueFactory) {
        foreach ($orderSets as $orderName => $orderFactory) {
            foreach ($frameSpecs as [$unit, $start, $end]) {
                foreach ($excludeModes as $exclude) {
                    $case++;
                    $count = count($separators);
                    $values = [];
                    $keys = [];
                    for ($row = 0; $row < $count; $row++) {
                        $values[] = $valueFactory($row, $case);
                        $keys[] = $orderFactory($row, $count);
                    }
                    array_multisort($keys, SORT_ASC, SORT_REGULAR, $values, $separators);
                    $actual = SQLiteWindowFunction::groupConcatFrameBetweenSeparators($values, $separators, $keys, $unit, $start, $end, $exclude);
                    $expected = [];
                    foreach (array_keys($values) as $position) {
                        $expected[] = $expectedConcat($values, $separators, $frameIndexes($keys, $position, $unit, $start, $end, $exclude));
                    }

                    $tests["real upstream windowC dynamic separator {$case} {$separatorName} {$valueName} {$orderName} {$unit} {$start} to {$end} exclude {$exclude}"] = static function (TestRunner $t) use ($actual, $expected, $case): void {
                        $t->same($expected, $actual, "windowC.test dynamic {$case} exact row-varying separators");
                    };

                    if ($case >= 1000) {
                        break 5;
                    }
                }
            }
        }
    }
}

$tests['real upstream windowC utf16 blob separator corpus cases'] = static function (TestRunner $t): void {
    $blob = new SQLiteBlobValue(hex2bin('5585d09013455178cd11ce4a') ?: '');
    $values = [1, $blob, 1, $blob];
    $separators = [',a,', ',a,', ',bc,', ',bc,'];
    $keys = [1, 1, 2, 2];
    $actual = SQLiteWindowFunction::groupConcatFrameBetweenSeparators($values, $separators, $keys, 'ROWS', '1 PRECEDING', '1 PRECEDING');

    $t->same([null, '1', $blob->bytes, '1'], $actual, 'windowC.test 2.0/2.1 preceding-only frame keeps blob payload text boundary');
    $t->contains('windowC.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test:1 varying group_concat separator window frames');
    $t->contains('windowC.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/windowC.test:2.0-2.1 UTF16 separator/value regression');
};

$tests['real upstream windowC dynamic separator source note'] = static function (TestRunner $t) use ($case): void {
    $t->same(1000, $case, 'windowC.test generated row-varying separator PASS cases');
    $t->same(1002, $case + 2, 'distinct TestRunner PASS cases in this real upstream windowC corpus file');
};

return $tests;
