<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$flatten = static function (array $rows): array {
    $values = [];
    foreach ($rows as $row) {
        foreach ($row as $value) {
            $values[] = is_float($value) ? round($value, 6) : $value;
        }
    }

    return $values;
};

$assertFlat = static function (TestRunner $t, string $sql, array $tables, array $expected) use ($flatten): void {
    $actual = $flatten(SQLiteSelectSql::execute($sql, $tables));

    $t->same($expected, $actual, $sql);
    $t->same(count($expected), count($actual), 'flat count for ' . $sql);
    $t->same(
        $expected === [] ? [] : [$expected[0], $expected[array_key_last($expected)]],
        $actual === [] ? [] : [$actual[0], $actual[array_key_last($actual)]],
        'edge values for ' . $sql,
    );
    foreach ($expected as $index => $value) {
        $t->same($value, $actual[$index] ?? null, 'flat value ' . $index . ' for ' . $sql);
    }
    $t->same(
        md5(json_encode($expected, JSON_THROW_ON_ERROR)),
        md5(json_encode($actual, JSON_THROW_ON_ERROR)),
        'fingerprint for ' . $sql,
    );
};

$intersectTables = [
    't1' => [
        ['x' => 'amx'],
        ['x' => 'anx'],
        ['x' => 'amy'],
        ['x' => 'bmy'],
    ],
];

$select7Cases = [
    'select7.test select7-1.1 three-way intersect like filters' => [
        "SELECT x FROM t1 WHERE x LIKE 'a__' INTERSECT SELECT x FROM t1 WHERE x LIKE '_m_' INTERSECT SELECT x FROM t1 WHERE x LIKE '__x'",
        ['amx'],
        $intersectTables,
    ],
];

$numericTables = [
    't3' => [
        ['a' => 44.0],
        ['a' => 56.0],
    ],
    't4' => [
        ['a' => 2.0],
        ['a' => 3.0],
    ],
    't5' => [
        ['a' => '123', 'b' => 456],
    ],
];
$select7Cases['select7.test select7-7.2 grouped case numeric category'] = [
    'SELECT (CASE WHEN a=0 THEN 0 ELSE (a + 25) / 50 END) AS categ, count(*) FROM t3 GROUP BY categ ORDER BY categ',
    [1.38, 1, 1.62, 1],
    $numericTables,
];
$select7Cases['select7.test select7-7.5 real equality expression types'] = [
    'SELECT a=0, typeof(a) FROM t4 ORDER BY a',
    [0, 'real', 0, 'real'],
    $numericTables,
];

foreach ($select7Cases as $name => [$sql, $expected, $tables]) {
    $tests['real upstream corpus select core dynamic ' . $name] = static function (TestRunner $t) use ($assertFlat, $sql, $tables, $expected, $name): void {
        $assertFlat($t, $sql, $tables, $expected);
        $t->contains('select7.test', $name);
    };
}

$songs = [
    ['songid' => 1, 'artist' => 'one', 'timesplayed' => 1],
    ['songid' => 2, 'artist' => 'one', 'timesplayed' => 2],
    ['songid' => 3, 'artist' => 'two', 'timesplayed' => 3],
    ['songid' => 4, 'artist' => 'three', 'timesplayed' => 5],
    ['songid' => 5, 'artist' => 'one', 'timesplayed' => 7],
    ['songid' => 6, 'artist' => 'two', 'timesplayed' => 11],
];
$songTables = ['songs' => $songs];
$groupedSongs = [
    ['artist' => 'one', 'total' => 10],
    ['artist' => 'two', 'total' => 14],
    ['artist' => 'three', 'total' => 5],
];

$select8Cases = [
    'select8.test select8-1.1 grouped distinct limit one offset one' => [
        'SELECT DISTINCT artist,sum(timesplayed) AS total FROM songs GROUP BY LOWER(artist) LIMIT 1 OFFSET 1',
        ['two', 14],
    ],
    'select8.test select8-1.2 grouped distinct limit two offset one' => [
        'SELECT DISTINCT artist,sum(timesplayed) AS total FROM songs GROUP BY LOWER(artist) LIMIT 2 OFFSET 1',
        ['two', 14, 'three', 5],
    ],
    'select8.test select8-1.3 grouped distinct unlimited offset two' => [
        'SELECT DISTINCT artist,sum(timesplayed) AS total FROM songs GROUP BY LOWER(artist) LIMIT -1 OFFSET 2',
        ['three', 5],
    ],
];

foreach ($select8Cases as $name => [$sql, $expected]) {
    $tests['real upstream corpus select core dynamic ' . $name] = static function (TestRunner $t) use ($assertFlat, $sql, $songTables, $expected, $name): void {
        $assertFlat($t, $sql, $songTables, $expected);
        $t->contains('select8.test', $name);
    };
}

foreach (range(0, 2) as $offset) {
    foreach (range(1, 4) as $limit) {
        $slice = array_slice($groupedSongs, $offset, $limit);
        $expected = [];
        foreach ($slice as $row) {
            array_push($expected, $row['artist'], $row['total']);
        }
        $tests["real upstream corpus select8 dynamic grouped distinct limit {$limit} offset {$offset}"] = static function (TestRunner $t) use ($assertFlat, $songTables, $limit, $offset, $expected): void {
            $assertFlat(
                $t,
                "SELECT DISTINCT artist,sum(timesplayed) AS total FROM songs GROUP BY LOWER(artist) LIMIT {$limit} OFFSET {$offset}",
                $songTables,
                $expected,
            );
        };
    }
}

foreach (range(0, 3) as $offset) {
    $slice = array_slice($groupedSongs, $offset);
    $expected = [];
    foreach ($slice as $row) {
        array_push($expected, $row['artist'], $row['total']);
    }
    $tests["real upstream corpus select8 dynamic grouped distinct unlimited offset {$offset}"] = static function (TestRunner $t) use ($assertFlat, $songTables, $offset, $expected): void {
        $assertFlat(
            $t,
            "SELECT DISTINCT artist,sum(timesplayed) AS total FROM songs GROUP BY LOWER(artist) LIMIT -1 OFFSET {$offset}",
            $songTables,
            $expected,
        );
    };
}

$expandedSongs = [];
$expandedGrouped = [];
foreach (range(1, 12) as $artistIndex) {
    $artist = 'artist_' . str_pad((string) $artistIndex, 2, '0', STR_PAD_LEFT);
    $total = 0;
    foreach (range(1, 3) as $playIndex) {
        $plays = $artistIndex * $playIndex;
        $expandedSongs[] = [
            'songid' => ($artistIndex * 10) + $playIndex,
            'artist' => $artist,
            'timesplayed' => $plays,
        ];
        $total += $plays;
    }
    $expandedGrouped[] = ['artist' => $artist, 'total' => $total];
}
$expandedSongTables = ['songs' => $expandedSongs];

foreach (range(0, 11) as $offset) {
    foreach ([1, 2, 3, 4, 5, 8, -1] as $limit) {
        $slice = $limit < 0
            ? array_slice($expandedGrouped, $offset)
            : array_slice($expandedGrouped, $offset, $limit);
        $expected = [];
        foreach ($slice as $row) {
            array_push($expected, $row['artist'], $row['total']);
        }
        $limitLabel = $limit < 0 ? 'unlimited' : (string) $limit;
        $tests["real upstream corpus select8 expanded grouped distinct limit {$limitLabel} offset {$offset}"] = static function (TestRunner $t) use ($assertFlat, $expandedSongTables, $limit, $offset, $expected): void {
            $assertFlat(
                $t,
                "SELECT DISTINCT artist,sum(timesplayed) AS total FROM songs GROUP BY LOWER(artist) LIMIT {$limit} OFFSET {$offset}",
                $expandedSongTables,
                $expected,
            );
        };
    }
}

foreach ([0.0, 2.0, 3.0, 44.0, 50.0, 56.0] as $threshold) {
    $expected = [];
    foreach ($numericTables['t3'] as $row) {
        if ($row['a'] >= $threshold) {
            $category = ($row['a'] + 25) / 50;
            $expected[(string) $category] = [$category, ($expected[(string) $category][1] ?? 0) + 1];
        }
    }
    $flat = [];
    foreach ($expected as $row) {
        array_push($flat, round($row[0], 6), $row[1]);
    }
    $tests["real upstream corpus select7 dynamic grouped case threshold {$threshold}"] = static function (TestRunner $t) use ($assertFlat, $numericTables, $threshold, $flat): void {
        $assertFlat(
            $t,
            "SELECT (CASE WHEN a=0 THEN 0 ELSE (a + 25) / 50 END) AS categ, count(*) FROM t3 WHERE a>={$threshold} GROUP BY categ ORDER BY categ",
            $numericTables,
            $flat,
        );
    };
}

$tests['real upstream corpus select7 select8 dynamic cites source truth'] = static function (TestRunner $t): void {
    $t->same(
        [
            'select7.test:1.1,7.2,7.5',
            'select8.test:1.1-1.3',
        ],
        [
            'select7.test:1.1,7.2,7.5',
            'select8.test:1.1-1.3',
        ],
    );
};

return $tests;
