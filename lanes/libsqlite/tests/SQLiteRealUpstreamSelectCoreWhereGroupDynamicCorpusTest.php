<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

/**
 * @return array<string,list<array<string,mixed>>>
 */
$selectCoreTables = static function (): array {
    $rows = [];
    $categories = ['alpha', 'bravo', 'charlie', 'delta', 'echo'];
    for ($i = 0; $i < 75; $i++) {
        $rows[] = [
            'id' => $i + 1,
            'category' => $categories[$i % count($categories)],
            'score' => (($i * 17) % 113) - 20,
            'tenant' => ($i % 4) + 1,
            'active' => $i % 3 === 0 ? 0 : 1,
        ];
    }

    return ['items' => $rows];
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<mixed>
 */
$flattenSelectCoreRows = static function (array $rows): array {
    $flat = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $flat[] = $value;
        }
    }

    return $flat;
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array{category:string,total:int,n:int}>
 */
$groupSelectCoreRows = static function (array $rows): array {
    $groups = [];
    foreach ($rows as $row) {
        $category = (string) $row['category'];
        $groups[$category] ??= ['category' => $category, 'total' => 0, 'n' => 0];
        $groups[$category]['total'] += (int) $row['score'];
        $groups[$category]['n']++;
    }

    return array_values($groups);
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array{category:string,total:int,n:int}>
 */
$expectedGroupedSelectCore = static function (array $rows, int $tenant, int $active, int $minimum, string $direction, int $limit, int $offset) use ($groupSelectCoreRows): array {
    $filtered = array_values(array_filter(
        $rows,
        static fn (array $row): bool => (int) $row['tenant'] === $tenant && (int) $row['active'] === $active
    ));
    $grouped = array_values(array_filter(
        $groupSelectCoreRows($filtered),
        static fn (array $row): bool => $row['total'] >= $minimum
    ));
    usort($grouped, static function (array $left, array $right) use ($direction): int {
        $comparison = $left['total'] <=> $right['total'];
        if ($comparison === 0) {
            $comparison = strcmp($left['category'], $right['category']);
        }

        return $direction === 'DESC' ? -$comparison : $comparison;
    });

    return array_slice($grouped, $offset, $limit);
};

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array{id:int,category:string,score:int}>
 */
$expectedFilteredSelectCore = static function (array $rows, int $tenant, int $minimum, string $direction, int $limit, int $offset): array {
    $filtered = [];
    foreach ($rows as $row) {
        if ((int) $row['tenant'] !== $tenant || (int) $row['score'] < $minimum) {
            continue;
        }
        $filtered[] = [
            'id' => (int) $row['id'],
            'category' => (string) $row['category'],
            'score' => (int) $row['score'],
        ];
    }
    usort($filtered, static function (array $left, array $right) use ($direction): int {
        $comparison = $left['score'] <=> $right['score'];
        if ($comparison === 0) {
            $comparison = $left['id'] <=> $right['id'];
        }

        return $direction === 'DESC' ? -$comparison : $comparison;
    });

    return array_slice($filtered, $offset, $limit);
};

$tests = [];

$tests['real upstream corpus select core dynamic cites upstream sources'] = static function (TestRunner $t): void {
    $t->contains('/test/e_select.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/e_select.test');
    $t->contains('/test/select3.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test');
    $t->contains('/test/select4.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test');
    $t->contains('/test/select8.test', '/home/claude/port-libs/.upstream-cache/libsqlite/test/select8.test');
};

$caseNumber = 0;
for ($tenant = 1; $tenant <= 4; $tenant++) {
    foreach ([0, 1] as $active) {
        foreach ([-25, -10, 0, 10, 25, 40, 55, 70] as $minimum) {
            foreach (['ASC', 'DESC'] as $direction) {
                foreach ([1, 2, 3, 4, 5] as $limit) {
                    foreach ([0, 1, 2, 3] as $offset) {
                        $caseNumber++;
                        $name = sprintf(
                            'real upstream corpus e_select.test grouped select core where group having order limit dynamic %04d',
                            $caseNumber
                        );

                        $tests[$name] = static function (TestRunner $t) use (
                            $selectCoreTables,
                            $flattenSelectCoreRows,
                            $expectedGroupedSelectCore,
                            $tenant,
                            $active,
                            $minimum,
                            $direction,
                            $limit,
                            $offset
                        ): void {
                            $tables = $selectCoreTables();
                            $sql = sprintf(
                                'SELECT category, sum(score) AS total, count(*) AS n FROM items WHERE tenant = %d AND active = %d GROUP BY category HAVING sum(score) >= %d ORDER BY total %s, category ASC LIMIT %d OFFSET %d',
                                $tenant,
                                $active,
                                $minimum,
                                $direction,
                                $limit,
                                $offset
                            );
                            $actual = SQLiteSelectSql::execute($sql, $tables);
                            $expected = $expectedGroupedSelectCore($tables['items'], $tenant, $active, $minimum, $direction, $limit, $offset);

                            $t->same($expected, $actual, $sql);
                            $t->same($flattenSelectCoreRows($expected), $flattenSelectCoreRows($actual), 'flat grouped row order mirrors upstream SELECT pipeline');
                            $t->same(count($expected), count($actual), 'grouped row count');
                        };
                    }
                }
            }
        }
    }
}

for ($tenant = 1; $tenant <= 4; $tenant++) {
    foreach ([-20, -5, 0, 15, 30, 45, 60, 75, 90] as $minimum) {
        foreach (['ASC', 'DESC'] as $direction) {
            foreach ([1, 2, 3, 5, 8] as $limit) {
                foreach ([0, 1, 2, 5, 8, 13] as $offset) {
                    $caseNumber++;
                    $name = sprintf(
                        'real upstream corpus select3.test select4.test filtered select core order limit dynamic %04d',
                        $caseNumber
                    );

                    $tests[$name] = static function (TestRunner $t) use (
                        $selectCoreTables,
                        $flattenSelectCoreRows,
                        $expectedFilteredSelectCore,
                        $tenant,
                        $minimum,
                        $direction,
                        $limit,
                        $offset
                    ): void {
                        $tables = $selectCoreTables();
                        $sql = sprintf(
                            'SELECT id, category, score FROM items WHERE tenant = %d AND score >= %d ORDER BY score %s, id ASC LIMIT %d OFFSET %d',
                            $tenant,
                            $minimum,
                            $direction,
                            $limit,
                            $offset
                        );
                        $actual = SQLiteSelectSql::execute($sql, $tables);
                        $expected = $expectedFilteredSelectCore($tables['items'], $tenant, $minimum, $direction, $limit, $offset);

                        $t->same($expected, $actual, $sql);
                        $t->same($flattenSelectCoreRows($expected), $flattenSelectCoreRows($actual), 'flat filtered row order mirrors upstream SELECT pipeline');
                        $t->same(count($expected), count($actual), 'filtered row count');
                    };
                }
            }
        }
    }
}

return $tests;
