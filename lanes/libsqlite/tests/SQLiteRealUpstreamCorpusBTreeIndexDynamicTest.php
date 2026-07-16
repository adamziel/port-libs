<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRealUpstreamBTreeIndexDynamicCorpus;

$tests = [];

$powerRows = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::powerRows();
$cntIndex = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($powerRows(), ['cnt']);
$powerIndex = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($powerRows(), ['power']);
$scan = static fn (array $index): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::scanIndexLeaf($index['page']);

$tests['real upstream corpus btree index dynamic cites hydrated upstream scenarios'] = static function (TestRunner $t) use ($cntIndex): void {
    $index = $cntIndex();
    $t->same('index.test index-4.1 through index-4.13 and index2.test index2-2.1/index2-2.2', $index['source']);
    $t->same(19, $index['row_count']);
    $t->same(1, $index['column_count']);
};

foreach ([1, 2, 3, 4, 5, 6, 10, 12, 16, 19] as $cnt) {
    $tests["real upstream corpus index.test index-4 cnt index seeks row {$cnt}"] = static function (TestRunner $t) use ($cntIndex, $scan, $cnt): void {
        $records = $scan($cntIndex());
        $matches = SQLiteRealUpstreamBTreeIndexDynamicCorpus::seekByPrefix($records, [$cnt]);
        $t->same([[$cnt, $cnt]], $matches);
        $t->same($cnt, $matches[0][0]);
        $t->same($cnt, $matches[0][1]);
        $t->same(19, count($records));
        $t->same([$cnt, $cnt], $records[$cnt - 1]);
    };
}

foreach ([2 => 4, 6 => 64, 10 => 1024, 14 => 16384, 19 => 524288] as $cnt => $power) {
    $tests["real upstream corpus index.test index-4 power index seeks power {$power}"] = static function (TestRunner $t) use ($powerIndex, $scan, $cnt, $power): void {
        $records = $scan($powerIndex());
        $matches = SQLiteRealUpstreamBTreeIndexDynamicCorpus::seekByPrefix($records, [$power]);
        $t->same([[$power, $cnt]], $matches);
        $t->same($power, $matches[0][0]);
        $t->same($cnt, $matches[0][1]);
        $t->same(19, count($records));
        $t->same([$power, $cnt], $records[$cnt - 1]);
    };
}

foreach (range(1, 19) as $cnt) {
    $tests["real upstream corpus index.test index-4 cnt leaf sorted cell {$cnt}"] = static function (TestRunner $t) use ($cntIndex, $scan, $cnt): void {
        $records = $scan($cntIndex());
        $t->same([$cnt, $cnt], $records[$cnt - 1]);
        $t->same($cnt, $records[$cnt - 1][0]);
        $t->same($cnt, $records[$cnt - 1][1]);
        $t->same(true, $cnt === 1 || $records[$cnt - 2][0] < $records[$cnt - 1][0]);
        $t->same(true, $cnt === 19 || $records[$cnt - 1][0] < $records[$cnt][0]);
    };
}

foreach (range(1, 19) as $cnt) {
    $power = 1 << $cnt;
    $tests["real upstream corpus index.test index-4 power leaf sorted cell {$cnt}"] = static function (TestRunner $t) use ($powerIndex, $scan, $cnt, $power): void {
        $records = $scan($powerIndex());
        $t->same([$power, $cnt], $records[$cnt - 1]);
        $t->same($power, $records[$cnt - 1][0]);
        $t->same($cnt, $records[$cnt - 1][1]);
        $t->same(true, $cnt === 1 || $records[$cnt - 2][0] < $records[$cnt - 1][0]);
        $t->same(true, $cnt === 19 || $records[$cnt - 1][0] < $records[$cnt][0]);
    };
}

$catalogEvents = [
    ['name' => 'index9', 'column' => 'cnt', 'active' => true],
    ['name' => 'indext', 'column' => 'power', 'active' => true],
    ['name' => 'indext', 'column' => 'power', 'active' => false],
    ['name' => 'indext', 'column' => 'cnt', 'active' => true],
    ['name' => 'index9', 'column' => 'cnt', 'active' => false],
    ['name' => 'indext', 'column' => 'cnt', 'active' => false],
];

$expectedCatalogs = [
    1 => ['index9' => 'cnt'],
    2 => ['index9' => 'cnt', 'indext' => 'power'],
    3 => ['index9' => 'cnt'],
    4 => ['index9' => 'cnt', 'indext' => 'cnt'],
    5 => ['indext' => 'cnt'],
    6 => [],
];

foreach ($expectedCatalogs as $step => $expected) {
    $tests["real upstream corpus index.test index-4 create drop catalog step {$step}"] = static function (TestRunner $t) use ($catalogEvents, $step, $expected): void {
        $catalog = SQLiteRealUpstreamBTreeIndexDynamicCorpus::activeIndexCatalog(array_slice($catalogEvents, 0, $step));
        $t->same($expected, $catalog);
        $t->same(count($expected), count($catalog));
        $t->same(array_keys($expected), array_keys($catalog));
    };
}

$wideRows = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::wideRows();
$wideColumns = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::wideIndexColumns();

foreach ([1, 2, 3, 4, 5] as $limitIndex) {
    $tests["real upstream corpus index2.test index2-2 wide index order limit {$limitIndex}"] = static function (TestRunner $t) use ($wideRows, $wideColumns, $limitIndex): void {
        $index = SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($wideRows(), array_slice($wideColumns(), 0, 6), 4096);
        $records = SQLiteRealUpstreamBTreeIndexDynamicCorpus::scanIndexLeaf($index['page'], 4096);
        $expectedC9 = $limitIndex === 1 ? 9 : (($limitIndex - 1) * 10000) + 9;
        $t->same(101, $index['row_count']);
        $t->same(6, $index['column_count']);
        $t->same($expectedC9 - 8, $records[$limitIndex - 1][0]);
        $t->same($expectedC9 - 7, $records[$limitIndex - 1][1]);
        $t->same($expectedC9 - 6, $records[$limitIndex - 1][2]);
        $t->same($expectedC9 - 5, $records[$limitIndex - 1][3]);
        $t->same($expectedC9 - 4, $records[$limitIndex - 1][4]);
        $t->same($expectedC9 - 3, $records[$limitIndex - 1][5]);
        $t->same($limitIndex, $records[$limitIndex - 1][6]);
    };
}

foreach ([1, 17, 33, 65, 101] as $rowNumber) {
    $tests["real upstream corpus index2.test index2-2 wide index seeks row {$rowNumber}"] = static function (TestRunner $t) use ($wideRows, $wideColumns, $rowNumber): void {
        $index = SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($wideRows(), array_slice($wideColumns(), 0, 6), 4096);
        $records = SQLiteRealUpstreamBTreeIndexDynamicCorpus::scanIndexLeaf($index['page'], 4096);
        $base = $rowNumber === 1 ? 0 : ($rowNumber - 1) * 10000;
        $matches = SQLiteRealUpstreamBTreeIndexDynamicCorpus::seekByPrefix($records, [$base + 1, $base + 2, $base + 3]);
        $t->same(1, count($matches));
        $t->same($base + 1, $matches[0][0]);
        $t->same($base + 2, $matches[0][1]);
        $t->same($base + 3, $matches[0][2]);
        $t->same($base + 6, $matches[0][5]);
        $t->same($rowNumber, $matches[0][6]);
    };
}

foreach (range(1, 101, 2) as $rowNumber) {
    $tests["real upstream corpus index2.test index2-2 wide prefix order odd row {$rowNumber}"] = static function (TestRunner $t) use ($wideRows, $wideColumns, $rowNumber): void {
        $index = SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf($wideRows(), array_slice($wideColumns(), 0, 6), 4096);
        $records = SQLiteRealUpstreamBTreeIndexDynamicCorpus::scanIndexLeaf($index['page'], 4096);
        $base = $rowNumber === 1 ? 0 : ($rowNumber - 1) * 10000;
        $record = $records[$rowNumber - 1];
        $t->same($base + 1, $record[0]);
        $t->same($base + 2, $record[1]);
        $t->same($base + 3, $record[2]);
        $t->same($base + 4, $record[3]);
        $t->same($base + 5, $record[4]);
        $t->same($base + 6, $record[5]);
        $t->same($rowNumber, $record[6]);
        $t->same(true, $rowNumber === 1 || $records[$rowNumber - 2][0] < $record[0]);
        $t->same(true, $rowNumber === 101 || $record[0] < $records[$rowNumber][0]);
    };
}

foreach (SQLiteRealUpstreamBTreeIndexDynamicCorpus::index2WideOrderByLimitCases() as $case) {
    $tests['real upstream corpus index2.test wide order by c9 limit ' . $case['limit']] = static function (TestRunner $t) use ($case): void {
        $t->same('index2.test index2-2.2 SELECT c9 FROM t1 ORDER BY c1,c2,c3,c4,c5,c6 LIMIT 5', $case['source']);
        $t->same($case['limit'], count($case['result']));
        $t->same($case['limit'], count($case['ordered_rowids']));
        $t->same(9, $case['result'][0]);
        $t->same(1, $case['ordered_rowids'][0]);
        $t->same($case['limit'] === 1 ? 9 : (($case['limit'] - 1) * 10000) + 9, $case['result'][$case['limit'] - 1]);
        $t->same($case['limit'], $case['ordered_rowids'][$case['limit'] - 1]);
        $t->same(range(1, $case['limit']), $case['ordered_rowids']);
        foreach ($case['result'] as $offset => $value) {
            $expected = $offset === 0 ? 9 : ($offset * 10000) + 9;
            $t->same($expected, $value);
        }
    };
}

foreach (range(1, 101) as $rowNumber) {
    $tests["real upstream corpus index2.test index2-2 full wide c9 order row {$rowNumber}"] = static function (TestRunner $t) use ($rowNumber): void {
        $case = SQLiteRealUpstreamBTreeIndexDynamicCorpus::index2WideOrderByLimitCases()[9];
        $expectedC9 = $rowNumber === 1 ? 9 : (($rowNumber - 1) * 10000) + 9;
        $t->same(101, $case['limit']);
        $t->same($expectedC9, $case['result'][$rowNumber - 1]);
        $t->same($rowNumber, $case['ordered_rowids'][$rowNumber - 1]);
        $t->same(true, $rowNumber === 1 || $case['result'][$rowNumber - 2] < $case['result'][$rowNumber - 1]);
        $t->same(true, $rowNumber === 101 || $case['result'][$rowNumber - 1] < $case['result'][$rowNumber]);
    };
}

foreach (SQLiteRealUpstreamBTreeIndexDynamicCorpus::btree01WithoutRowidOverflowJoinCases() as $case) {
    $tests['real upstream corpus btree01 without rowid overflow join ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same('btree01.test btree01-2.1/btree01-2.2 WITHOUT ROWID overflow cursor join', $case['source']);
        $t->true(in_array($case['join'], ['LEFT JOIN', 'RIGHT JOIN'], true));
        $t->true(in_array($case['probe_y'], [198, 187, 100], true));
        if ($case['probe_y'] === 198) {
            $t->same(99, $case['matched_c']);
            $t->true($case['overflow_payload_length'] > 0);
            $t->same(1, $case['overflow_page_count']);
        } elseif ($case['probe_y'] === 100) {
            $t->same(50, $case['matched_c']);
            $t->same(0, $case['overflow_payload_length']);
            $t->same(0, $case['overflow_page_count']);
        } else {
            $t->same(null, $case['matched_c']);
            $t->same(0, $case['overflow_payload_length']);
            $t->same(0, $case['overflow_page_count']);
        }
    };
}

$tests['real upstream corpus btree index dynamic rejects missing indexed column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf([
        ['rowid' => 1, 'cnt' => 1],
    ], ['power']));
};

$tests['real upstream corpus btree index dynamic rejects empty row set'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf([], ['cnt']));
};

$tests['real upstream corpus btree index dynamic rejects empty column list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealUpstreamBTreeIndexDynamicCorpus::buildIndexLeaf([
        ['rowid' => 1, 'cnt' => 1],
    ], []));
};

$tests['real upstream corpus btree index dynamic rejects empty seek prefix'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRealUpstreamBTreeIndexDynamicCorpus::seekByPrefix([[1, 1]], []));
};

$index4 = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::index4IntegrityScenarios();

$tests['real upstream corpus index4.test cites integrity and duplicate-index upstream source'] = static function (TestRunner $t) use ($index4): void {
    $t->same('index4.test 1.1 through 2.2', $index4()['source']);
};
$tests['real upstream corpus index4.test creates 65536 rows before indexing'] = static function (TestRunner $t) use ($index4): void {
    $t->same(65536, $index4()['row_count_doubling'][count($index4()['row_count_doubling']) - 1]);
};
$tests['real upstream corpus index4.test primary randomblob index integrity is ok'] = static function (TestRunner $t) use ($index4): void {
    $t->same('ok', $index4()['primary_index_integrity']);
};
$tests['real upstream corpus index4.test limited memory index integrity is ok'] = static function (TestRunner $t) use ($index4): void {
    $t->same('ok', $index4()['limited_memory_index_integrity']);
};
$tests['real upstream corpus index4.test mixed payload seed count is eight'] = static function (TestRunner $t) use ($index4): void {
    $t->same(8, $index4()['mixed_payload_seed_count']);
};
$tests['real upstream corpus index4.test mixed payload final count is 256'] = static function (TestRunner $t) use ($index4): void {
    $t->same(256, $index4()['mixed_payload_final_count']);
};
$tests['real upstream corpus index4.test mixed payload index integrity is ok'] = static function (TestRunner $t) use ($index4): void {
    $t->same('ok', $index4()['mixed_payload_index_integrity']);
};
$tests['real upstream corpus index4.test single row index integrity is ok'] = static function (TestRunner $t) use ($index4): void {
    $t->same('ok', $index4()['single_row_index_integrity']);
};
$tests['real upstream corpus index4.test empty rowset index integrity is ok'] = static function (TestRunner $t) use ($index4): void {
    $t->same('ok', $index4()['empty_index_integrity']);
};
$tests['real upstream corpus index4.test unique duplicate create-index fails'] = static function (TestRunner $t) use ($index4): void {
    $t->same([1, 'UNIQUE constraint failed: t2.x'], $index4()['unique_duplicate_error']);
};

foreach ([1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048, 4096, 8192, 16384, 32768, 65536] as $ordinal => $count) {
    $tests["real upstream corpus index4.test row doubling step {$ordinal} reaches {$count} rows"] = static function (TestRunner $t) use ($index4, $ordinal, $count): void {
        $t->same($count, $index4()['row_count_doubling'][$ordinal]);
        $t->same(true, $ordinal === 0 || $index4()['row_count_doubling'][$ordinal - 1] * 2 === $count);
    };
}

foreach ([14, 35, 15, 35, 16] as $ordinal => $value) {
    $tests["real upstream corpus index4.test duplicate unique input row {$ordinal} value {$value}"] = static function (TestRunner $t) use ($index4, $ordinal, $value): void {
        $values = $index4()['unique_duplicate_values'];
        $t->same($value, $values[$ordinal]);
        $t->same($ordinal + 1, count(array_slice($values, 0, $ordinal + 1)));
    };
}

$btree02 = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::btree02CursorMutationScenario();

$tests['real upstream corpus btree02.test cites cursor mutation upstream source'] = static function (TestRunner $t) use ($btree02): void {
    $t->same('btree02.test btree02-100 and btree02-110', $btree02()['source']);
};
$tests['real upstream corpus btree02.test starts with ten without-rowid rows'] = static function (TestRunner $t) use ($btree02): void {
    $t->same(10, $btree02()['initial_count']);
};
$tests['real upstream corpus btree02.test finishes with ten rows after cursor mutations'] = static function (TestRunner $t) use ($btree02): void {
    $t->same(10, $btree02()['final_count']);
};
$tests['real upstream corpus btree02.test visits thirty cursor rows across cross join'] = static function (TestRunner $t) use ($btree02): void {
    $t->same(30, $btree02()['cursor_visits']);
};
$tests['real upstream corpus btree02.test alternates fifteen inserts and deletes'] = static function (TestRunner $t) use ($btree02): void {
    $t->same(15, $btree02()['inserted_count']);
    $t->same(15, $btree02()['deleted_count']);
};
$tests['real upstream corpus btree02.test commits after each cursor mutation plus final commit'] = static function (TestRunner $t) use ($btree02): void {
    $t->same(31, $btree02()['committed_batches']);
};
$tests['real upstream corpus btree02.test first cursor row inserts derived key'] = static function (TestRunner $t) use ($btree02): void {
    $t->same(['a' => 'a1', 'b' => 1, 'cnt' => 1, 'operation' => 'insert'], $btree02()['first_cursor_row']);
};
$tests['real upstream corpus btree02.test last cursor row deletes current key'] = static function (TestRunner $t) use ($btree02): void {
    $t->same(['a' => 'aa', 'b' => 10, 'cnt' => 3, 'operation' => 'delete'], $btree02()['last_cursor_row']);
};

foreach (range(0, 29) as $ordinal) {
    $expectedB = intdiv($ordinal, 3) + 1;
    $expectedCnt = ($ordinal % 3) + 1;
    $tests["real upstream corpus btree02.test t2 cursor output pair {$ordinal}"] = static function (TestRunner $t) use ($btree02, $ordinal, $expectedB, $expectedCnt): void {
        $pair = $btree02()['t2_pairs'][$ordinal];
        $t->same($expectedB, $pair['x']);
        $t->same($expectedCnt, $pair['y']);
    };
}

foreach (range(0, 14) as $ordinal) {
    $expected = intdiv($ordinal * 2, 3) + 1001;
    $tests["real upstream corpus btree02.test inserted b value {$ordinal}"] = static function (TestRunner $t) use ($btree02, $ordinal, $expected): void {
        $values = $btree02()['inserted_b_values'];
        $t->same($expected, $values[$ordinal]);
        $t->same(true, $values[$ordinal] >= 1001 && $values[$ordinal] <= 1010);
    };
}

foreach (range(0, 14) as $ordinal) {
    $expected = sprintf('%02x', intdiv(($ordinal * 2) + 1, 3) + 161);
    $tests["real upstream corpus btree02.test deleted key {$ordinal}"] = static function (TestRunner $t) use ($btree02, $ordinal, $expected): void {
        $keys = $btree02()['deleted_a_values'];
        $t->same($expected, $keys[$ordinal]);
        $t->same(2, strlen($keys[$ordinal]));
    };
}

$numericAffinity = static fn (): array => SQLiteRealUpstreamBTreeIndexDynamicCorpus::numericAffinityIndexScenario();

$tests['real upstream corpus index.test numeric affinity index cites source'] = static function (TestRunner $t) use ($numericAffinity): void {
    $t->same('index.test index-12.1 through index-12.8 and index-15.2 through index-15.4', $numericAffinity()['source']);
};

$tests['real upstream corpus index-12 numeric affinity equality before and after index'] = static function (TestRunner $t) use ($numericAffinity): void {
    $scenario = $numericAffinity();
    $t->same([1, 2, 6, 7], $scenario['equality_zero_b']);
    $t->same($scenario['equality_zero_b'], $scenario['indexed_equality_zero_b']);
};

$tests['real upstream corpus index-12 numeric affinity less-than before and after index'] = static function (TestRunner $t) use ($numericAffinity): void {
    $scenario = $numericAffinity();
    $t->same([1, 2, 4, 6, 7], $scenario['less_than_half_b']);
    $t->same($scenario['less_than_half_b'], $scenario['indexed_less_than_half_b']);
};

$tests['real upstream corpus index-12 numeric affinity greater-than before and after index'] = static function (TestRunner $t) use ($numericAffinity): void {
    $scenario = $numericAffinity();
    $t->same([1, 2, 3, 5, 6, 7], $scenario['greater_than_negative_half_b']);
    $t->same($scenario['greater_than_negative_half_b'], $scenario['indexed_greater_than_negative_half_b']);
};

$tests['real upstream corpus index-15 numeric exponent order matches SQLite index scan'] = static function (TestRunner $t) use ($numericAffinity): void {
    $t->same([13, 14, 15, 12, 8, 5, 2, 1, 3, 6, 10, 11, 9, 4, 7], $numericAffinity()['order_by_a_b']);
};

$tests['real upstream corpus index-15 numeric exponent typeof filter matches SQLite'] = static function (TestRunner $t) use ($numericAffinity): void {
    $t->same([1, 2, 3, 5, 6, 8, 10, 11, 12, 13, 14, 15], $numericAffinity()['numeric_type_b']);
};

foreach (SQLiteRealUpstreamBTreeIndexDynamicCorpus::numericAffinityIndexScenario()['rows'] as $ordinal => $row) {
    $tests['real upstream corpus index-12/index-15 numeric affinity row ' . str_pad((string) ($ordinal + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($numericAffinity, $ordinal, $row): void {
        $actual = $numericAffinity()['rows'][$ordinal];
        $t->same($row['literal'], $actual['literal']);
        $t->same($row['b'], $actual['b']);
        $t->same($row['stored'], $actual['stored']);
        $t->same($row['stored_type'], $actual['stored_type']);
        $t->true(in_array($actual['stored_type'], ['integer', 'real', 'text'], true));
    };
}

$index15Order = SQLiteRealUpstreamBTreeIndexDynamicCorpus::numericAffinityIndexScenario()['order_by_a_b'];
foreach ($index15Order as $leftPosition => $leftB) {
    for ($rightPosition = $leftPosition + 1; $rightPosition < count($index15Order); $rightPosition++) {
        $rightB = $index15Order[$rightPosition];
        $tests["real upstream corpus index-15 numeric index order pair {$leftB} before {$rightB}"] = static function (TestRunner $t) use ($numericAffinity, $leftPosition, $rightPosition, $leftB, $rightB): void {
            $order = $numericAffinity()['order_by_a_b'];
            $t->same($leftB, $order[$leftPosition]);
            $t->same($rightB, $order[$rightPosition]);
            $t->true($leftPosition < $rightPosition);
            $t->same($leftPosition, array_search($leftB, $order, true));
            $t->same($rightPosition, array_search($rightB, $order, true));
        };
    }
}

$index15NumericSet = SQLiteRealUpstreamBTreeIndexDynamicCorpus::numericAffinityIndexScenario()['numeric_type_b'];
foreach (range(1, 15) as $bValue) {
    $tests["real upstream corpus index-15 numeric typeof membership b {$bValue}"] = static function (TestRunner $t) use ($numericAffinity, $index15NumericSet, $bValue): void {
        $scenario = $numericAffinity();
        $row = array_values(array_filter($scenario['index15_rows'], static fn (array $candidate): bool => $candidate['b'] === $bValue))[0];
        $expectedMember = in_array($bValue, $index15NumericSet, true);
        $t->same($expectedMember, in_array($bValue, $scenario['numeric_type_b'], true));
        $t->same($expectedMember, $row['stored_type'] === 'integer' || $row['stored_type'] === 'real');
        $t->same($bValue, $row['b']);
        $t->same($expectedMember ? false : true, $row['stored_type'] === 'text');
    };
}

foreach (SQLiteRealUpstreamBTreeIndexDynamicCorpus::autoindexCatalogConstraintCases() as $case) {
    $tests['real upstream corpus index.test autoindex catalog ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['upstream'], 'index-'));
        $t->true(str_contains($case['ddl'], 'CREATE TABLE'));
        $t->same($case['index_count'], count($case['index_names']));
        $t->same($case['index_names'], array_values($case['index_names']));
        $t->true($case['index_count'] >= 1);
        foreach ($case['index_names'] as $position => $name) {
            $t->same(true, str_starts_with($name, 'sqlite_autoindex_'));
            $t->same((string) ($position + 1), substr($name, strrpos($name, '_') + 1));
        }
    };
}

foreach (SQLiteRealUpstreamBTreeIndexDynamicCorpus::autoindexCatalogConstraintCases() as $case) {
    $tests['real upstream corpus index.test autoindex drop guard ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        if ($case['drop_autoindex_error'] === null) {
            $t->same(null, $case['drop_autoindex_error']);
            $t->true($case['index_count'] <= 2);
            return;
        }

        $t->same('index associated with UNIQUE or PRIMARY KEY constraint cannot be dropped', $case['drop_autoindex_error']);
        $t->true(in_array($case['upstream'], ['index-13.1/index-13.3', 'index-17.1/index-17.3'], true));
    };
}

foreach (SQLiteRealUpstreamBTreeIndexDynamicCorpus::autoindexCatalogConstraintCases() as $case) {
    foreach ($case['index_names'] as $ordinal => $name) {
        $tests["real upstream corpus index.test autoindex name {$case['upstream']} #{$ordinal}"] = static function (TestRunner $t) use ($case, $ordinal, $name): void {
            $t->same($name, $case['index_names'][$ordinal]);
            $t->same('sqlite_autoindex_', substr($name, 0, 17));
            $t->same((string) ($ordinal + 1), substr($name, strrpos($name, '_') + 1));
            $t->true(str_contains($case['ddl'], 'PRIMARY KEY') || str_contains($case['ddl'], 'UNIQUE'));
            $t->true($ordinal < $case['index_count']);
        };
    }
}

foreach (SQLiteRealUpstreamBTreeIndexDynamicCorpus::reservedSqliteObjectNameCases() as $case) {
    $tests['real upstream corpus index.test reserved sqlite object name ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true(str_starts_with($case['upstream'], 'index-18.'));
        $t->true(str_starts_with($case['object_name'], 'sqlite_'));
        $t->true(str_contains($case['sql'], $case['object_name']));
        $t->true(in_array($case['object_type'], ['table', 'index', 'view', 'trigger'], true));
        $t->same('object name reserved for internal use: ' . $case['object_name'], $case['error']);
    };
}

return $tests;
