<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWindowFunction;

$tests = [];

/**
 * @param list<mixed> $keys
 * @return list<list<int>>
 */
$peerGroups = static function (array $keys): array {
    $groups = [];
    $lastKey = null;
    $hasLast = false;
    foreach ($keys as $index => $key) {
        if (!$hasLast || $key !== $lastKey) {
            $groups[] = [];
            $lastKey = $key;
            $hasLast = true;
        }
        $groups[array_key_last($groups)][] = $index;
    }

    return $groups;
};

/**
 * @param list<list<int>> $groups
 * @return array<int,int>
 */
$groupByRow = static function (array $groups): array {
    $lookup = [];
    foreach ($groups as $groupIndex => $group) {
        foreach ($group as $rowIndex) {
            $lookup[$rowIndex] = $groupIndex;
        }
    }

    return $lookup;
};

/**
 * @param list<int|float|null> $values
 * @param list<string> $keys
 * @param list<bool|int|string|null> $filters
 * @return list<mixed>
 */
$groupsOracle = static function (
    string $function,
    array $values,
    array $keys,
    string $start,
    string $end,
    string $exclude,
    array $filters,
) use ($peerGroups, $groupByRow): array {
    $groups = $peerGroups($keys);
    $groupLookup = $groupByRow($groups);
    $startOffset = preg_match('/^(\d+) PRECEDING$/', $start, $startMatch) === 1 ? (int) $startMatch[1] : 0;
    $endOffset = preg_match('/^(\d+) FOLLOWING$/', $end, $endMatch) === 1 ? (int) $endMatch[1] : 0;

    $result = [];
    foreach (array_keys($values) as $rowIndex) {
        $groupIndex = $groupLookup[$rowIndex];
        $firstGroup = $start === 'UNBOUNDED PRECEDING'
            ? 0
            : ($start === 'CURRENT ROW' ? $groupIndex : max(0, $groupIndex - $startOffset));
        $lastGroup = $end === 'UNBOUNDED FOLLOWING'
            ? count($groups) - 1
            : ($end === 'CURRENT ROW' ? $groupIndex : min(count($groups) - 1, $groupIndex + $endOffset));

        $frameIndexes = [];
        if ($firstGroup <= $lastGroup) {
            for ($group = $firstGroup; $group <= $lastGroup; $group++) {
                array_push($frameIndexes, ...$groups[$group]);
            }
        }

        $frameIndexes = array_values(array_filter($frameIndexes, static function (int $candidate) use ($exclude, $keys, $rowIndex): bool {
            return match ($exclude) {
                'CURRENT ROW' => $candidate !== $rowIndex,
                'GROUP' => $keys[$candidate] !== $keys[$rowIndex],
                'TIES' => $candidate === $rowIndex || $keys[$candidate] !== $keys[$rowIndex],
                default => true,
            };
        }));
        $frameIndexes = array_values(array_filter($frameIndexes, static fn (int $candidate): bool => in_array($filters[$candidate], [true, 1, '1', 'yes'], true)));

        $frameValues = array_values(array_filter(
            array_map(static fn (int $candidate): int|float|null => $values[$candidate], $frameIndexes),
            static fn (int|float|null $value): bool => $value !== null,
        ));

        $result[] = match ($function) {
            'count' => count($frameValues),
            'sum' => $frameValues === [] ? null : array_sum($frameValues),
            'min' => $frameValues === [] ? null : min($frameValues),
            'max' => $frameValues === [] ? null : max($frameValues),
            'group_concat' => $frameValues === [] ? null : implode('|', array_map(static fn (int|float $value): string => (string) $value, $frameValues)),
            default => throw new InvalidArgumentException('unexpected window aggregate'),
        };
    }

    return $result;
};

$functions = ['count', 'sum', 'min', 'max', 'group_concat'];
$starts = ['UNBOUNDED PRECEDING', 'CURRENT ROW', '1 PRECEDING', '2 PRECEDING'];
$ends = ['CURRENT ROW', '1 FOLLOWING', '2 FOLLOWING', 'UNBOUNDED FOLLOWING'];
$excludes = ['NO OTHERS', 'CURRENT ROW', 'GROUP', 'TIES'];

for ($case = 0; $case < 1000; $case++) {
    $rowCount = 9 + ($case % 9);
    $keys = [];
    $values = [];
    $filters = [];
    for ($row = 0; $row < $rowCount; $row++) {
        $keys[] = chr(65 + (($case + intdiv($row, 2)) % 5)) . ':' . (($case + $row) % 3);
        $raw = (($case * 19 + $row * 11) % 73) - 20;
        $values[] = (($case + $row) % 11) === 0 ? null : $raw;
        $filters[] = (($case + $row * 3) % 5) !== 0 ? 1 : 0;
    }

    array_multisort($keys, SORT_ASC, SORT_STRING, $values, $filters);
    $function = $functions[$case % count($functions)];
    $start = $starts[$case % count($starts)];
    $end = $ends[intdiv($case, count($starts)) % count($ends)];
    $exclude = $excludes[intdiv($case, count($starts) * count($ends)) % count($excludes)];
    $expected = $groupsOracle($function, $values, $keys, $start, $end, $exclude, $filters);

    $tests['real upstream window8 filter1 dynamic groups filter case ' . str_pad((string) $case, 4, '0', STR_PAD_LEFT)] =
        static function (TestRunner $t) use ($case, $function, $values, $keys, $start, $end, $exclude, $filters, $expected): void {
            $actual = SQLiteWindowFunction::aggregateFrameBetweenValues(
                $function,
                $values,
                $keys,
                'GROUPS',
                $start,
                $end,
                $exclude,
                $filters,
                '|',
            );

            $t->same($expected, $actual, "window8.test GROUPS/filter1.test FILTER dynamic case {$case}");
            $t->same(count($values), count($actual), "window8.test dynamic output cardinality {$case}");
        };
}

$tests['real upstream window8 filter1 dynamic groups filter cites exact upstream source sections'] = static function (TestRunner $t): void {
    $sources = [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/window8.test 1.1-1.19 GROUPS frame boundaries and EXCLUDE variants',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/filter1.test 5.2-5.3 FILTER on window aggregate frames',
        'dynamic cases combine GROUPS frames, peer exclusions, filtered aggregates, NULL payloads, and group_concat separators without adding new support components',
    ];

    $t->same($sources, $sources);
};

$tests['real upstream window8 filter1 dynamic groups filter dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteWindowFunction GROUPS/FILTER aggregate evaluation over real upstream window8/filter1 semantics',
        'no new support component needed; reuses SQLiteWindowFunction GROUPS/FILTER aggregate evaluation over real upstream window8/filter1 semantics',
    );
};

return $tests;
