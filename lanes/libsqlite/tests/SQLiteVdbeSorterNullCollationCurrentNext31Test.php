<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeSorterYieldCursor;

$rows = [
    ['rowid' => 1, 'autoload' => 'yes', 'option_name' => 'Plugin_A ', 'priority' => null, 'payload' => 'stale uppercase'],
    ['rowid' => 2, 'autoload' => 'YES', 'option_name' => 'plugin_a', 'priority' => '10', 'payload' => 'network active'],
    ['rowid' => 3, 'autoload' => 'yes', 'option_name' => 'plugin_b', 'priority' => '2', 'payload' => 'plugin beta'],
    ['rowid' => 4, 'autoload' => 'no', 'option_name' => 'cache ', 'priority' => null, 'payload' => 'transient null priority'],
    ['rowid' => 5, 'autoload' => 'no', 'option_name' => 'cache', 'priority' => 5, 'payload' => 'transient numeric'],
    ['rowid' => 6, 'autoload' => null, 'option_name' => 'network', 'priority' => 1, 'payload' => 'network null autoload'],
    ['rowid' => 7, 'autoload' => 'yes', 'option_name' => null, 'priority' => 7, 'payload' => 'nameless plugin'],
    ['rowid' => 8, 'autoload' => 'yes', 'option_name' => 'Plugin_A', 'priority' => 10, 'payload' => 'uppercase active'],
    ['rowid' => 9, 'autoload' => 'no', 'option_name' => 'Cache', 'priority' => 5, 'payload' => 'case sensitive cache'],
    ['rowid' => 10, 'autoload' => 'yes', 'option_name' => 'plugin_a  ', 'priority' => 10, 'payload' => 'stable duplicate'],
];

$cursor = static fn (): SQLiteVdbeSorterYieldCursor => new SQLiteVdbeSorterYieldCursor(
    $rows,
    ['autoload', 'option_name', 'priority'],
    'GGC',
    ['NOCASE', 'RTRIM', 'BINARY'],
    [false, false, true],
    ['LAST', 'FIRST', 'LAST']
);

$tests = [];

$orderedRowids = [9, 5, 4, 7, 8, 1, 2, 10, 3, 6];
$orderedRecords = [
    ['no', 'Cache', 5],
    ['no', 'cache', 5],
    ['no', 'cache ', null],
    ['yes', null, 7],
    ['yes', 'Plugin_A', 10],
    ['yes', 'Plugin_A ', null],
    ['YES', 'plugin_a', '10'],
    ['yes', 'plugin_a  ', 10],
    ['yes', 'plugin_b', '2'],
    [null, 'network', 1],
];
$previousSequences = [null, 8, 4, 3, 6, 7, 0, 1, 9, 2];
$comparisonSigns = [null, -1, -1, -1, -1, -1, -1, 0, -1, -1];
$decidingIndexes = [null, 1, 2, 0, 1, 2, 1, null, 1, 0];
$decidingCollations = [null, 'RTRIM', 'BINARY', 'NOCASE', 'RTRIM', 'BINARY', 'RTRIM', null, 'RTRIM', 'NOCASE'];
$decidingNulls = [null, 'FIRST', 'LAST', 'LAST', 'FIRST', 'LAST', 'FIRST', null, 'FIRST', 'LAST'];

$tests['vdbe sorter yield current starts at first sorted row'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $t->same(0, $c->position());
    $t->same(9, $c->currentValue('rowid'));
    $t->same(['no', 'Cache', 5], $c->currentRecord());
    $t->true(!$c->eof());
};

$tests['vdbe sorter yield next row returns then advances'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $row = $c->nextRow();
    $t->same(9, $row['rowid']);
    $t->same(1, $c->position());
    $t->same(5, $c->currentValue('rowid'));
};

$tests['vdbe sorter yield drains rowids in null collation order'] = static function (TestRunner $t) use ($cursor, $orderedRowids): void {
    $c = $cursor();
    $seen = [];
    while (!$c->eof()) {
        $seen[] = $c->currentValue('rowid');
        $c->next();
    }
    $t->same($orderedRowids, $seen);
};

$tests['vdbe sorter yield remaining rows expose current suffix'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $c->next();
    $c->next();
    $t->same([7, 8, 1, 2, 10, 3, 6], array_column($c->remainingRows(), 'rowid'));
};

$tests['vdbe sorter yield eof current values are null'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    for ($i = 0; $i < 20; $i++) {
        $c->next();
    }
    $t->true($c->eof());
    $t->same(null, $c->current());
    $t->same(null, $c->currentRecord());
    $t->same(null, $c->currentValue('rowid'));
};

$tests['vdbe sorter yield stable tie reports previous sequence'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    for ($i = 0; $i < 7; $i++) {
        $c->next();
    }
    $t->same(1, $c->previousSequence());
    $t->same(0, $c->comparisonFromPrevious());
    $t->true($c->stableTieFromPrevious());
};

$tests['vdbe sorter yield comparison steps include null placement decision'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    for ($i = 0; $i < 3; $i++) {
        $c->next();
    }
    $step = $c->comparisonStepsFromPrevious()[0];
    $t->same(0, $step['index']);
    $t->same('NOCASE', $step['collation']);
    $t->same('LAST', $step['nulls']);
    $t->true($step['decided']);
};

$tests['vdbe sorter yield summary reports stable tie without deciding index'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    for ($i = 0; $i < 7; $i++) {
        $c->next();
    }
    $summary = $c->currentSummary();
    $t->same(null, $summary['decidingIndex']);
    $t->same(null, $summary['decidingCollation']);
    $t->true($summary['stableTie']);
};

foreach ($orderedRowids as $position => $rowid) {
    $tests["vdbe sorter yield position {$position} rowid"] = static function (TestRunner $t) use ($cursor, $position, $rowid): void {
        $c = $cursor();
        for ($i = 0; $i < $position; $i++) {
            $c->next();
        }
        $t->same($rowid, $c->currentValue('rowid'));
    };
}

foreach ($orderedRecords as $position => $record) {
    $tests["vdbe sorter yield position {$position} record"] = static function (TestRunner $t) use ($cursor, $position, $record): void {
        $c = $cursor();
        for ($i = 0; $i < $position; $i++) {
            $c->next();
        }
        $t->same($record, $c->currentRecord());
    };
}

foreach ($previousSequences as $position => $previousSequence) {
    $tests["vdbe sorter yield position {$position} previous sequence"] = static function (TestRunner $t) use ($cursor, $position, $previousSequence): void {
        $c = $cursor();
        for ($i = 0; $i < $position; $i++) {
            $c->next();
        }
        $t->same($previousSequence, $c->previousSequence());
    };
}

foreach ($comparisonSigns as $position => $comparisonSign) {
    $tests["vdbe sorter yield position {$position} comparison sign from previous"] = static function (TestRunner $t) use ($cursor, $position, $comparisonSign): void {
        $c = $cursor();
        for ($i = 0; $i < $position; $i++) {
            $c->next();
        }
        $comparison = $c->comparisonFromPrevious();
        $t->same($comparisonSign, $comparison === null ? null : ($comparison <=> 0));
    };
}

foreach ($decidingIndexes as $position => $decidingIndex) {
    $tests["vdbe sorter yield position {$position} deciding index"] = static function (TestRunner $t) use ($cursor, $position, $decidingIndex): void {
        $c = $cursor();
        for ($i = 0; $i < $position; $i++) {
            $c->next();
        }
        $t->same($decidingIndex, $c->currentSummary()['decidingIndex']);
    };
}

foreach ($decidingCollations as $position => $decidingCollation) {
    $tests["vdbe sorter yield position {$position} deciding collation"] = static function (TestRunner $t) use ($cursor, $position, $decidingCollation): void {
        $c = $cursor();
        for ($i = 0; $i < $position; $i++) {
            $c->next();
        }
        $t->same($decidingCollation, $c->currentSummary()['decidingCollation']);
    };
}

foreach ($decidingNulls as $position => $decidingNull) {
    $tests["vdbe sorter yield position {$position} deciding null placement"] = static function (TestRunner $t) use ($cursor, $position, $decidingNull): void {
        $c = $cursor();
        for ($i = 0; $i < $position; $i++) {
            $c->next();
        }
        $t->same($decidingNull, $c->currentSummary()['decidingNulls']);
    };
}

$tests['vdbe sorter yield drain summaries reports positions'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(range(0, 9), array_column($cursor()->drainSummaries(), 'position'));
};

$tests['vdbe sorter yield drain summaries reports sequences'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([8, 4, 3, 6, 7, 0, 1, 9, 2, 5], array_column($cursor()->drainSummaries(), 'sequence'));
};

$tests['vdbe sorter yield drain summaries reports deciding indexes'] = static function (TestRunner $t) use ($cursor, $decidingIndexes): void {
    $t->same($decidingIndexes, array_column($cursor()->drainSummaries(), 'decidingIndex'));
};

$tests['vdbe sorter yield drain leaves cursor at eof'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->drainSummaries();
    $t->true($c->eof());
    $t->same(10, $c->position());
};

$tests['vdbe sorter yield missing yielded column throws'] = static function (TestRunner $t) use ($cursor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $cursor()->currentValue('missing'));
};

$tests['vdbe sorter yield empty columns throw'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterYieldCursor($rows, []));
};

$tests['vdbe sorter yield non-list rows throw through comparator'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterYieldCursor(['bad' => ['rowid' => 1]], ['rowid']));
};

$tests['vdbe sorter yield missing sort column throws'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterYieldCursor($rows, ['missing']));
};

$tests['vdbe sorter yield invalid null placement throws'] = static function (TestRunner $t) use ($rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeSorterYieldCursor($rows, ['autoload'], 'G', ['NOCASE'], [], ['MIDDLE']));
};

return $tests;
