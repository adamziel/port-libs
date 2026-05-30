<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexDynamicCorpusPlan;

$tests = [];

foreach (SQLiteBTreeIndexDynamicCorpusPlan::btree01BalanceStressCases() as $case) {
    $tests['real upstream btree01 balance stress ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->same(65536, $case['page_size']);
        $t->true($case['target_row'] >= 1);
        $t->true($case['target_row'] <= $case['row_count']);
        $t->true($case['expanded_blob'] > $case['shrink_blob']);
        $t->true($case['initial_blob'] >= $case['shrink_blob']);
        $t->true($case['local_payload_length'] > 0);
        $t->true($case['local_payload_length'] <= $case['expanded_blob'] + 8);
        $t->true($case['overflow_payload_length'] >= 0);
        $t->same($case['overflow_payload_length'] === 0 ? 0 : 1, min(1, $case['overflow_page_count']));
        $t->same('ok', $case['integrity']);
    };
}

foreach (SQLiteBTreeIndexDynamicCorpusPlan::indexTestDynamicLookupCases() as $case) {
    $tests['real upstream index dynamic lookup ' . $case['upstream']] = static function (TestRunner $t) use ($case): void {
        $t->true($case['active_indexes'] === array_values($case['active_indexes']));
        $t->true($case['lookup_column'] === 'cnt' || $case['lookup_column'] === 'power');
        $t->true($case['result_column'] === 'cnt' || $case['result_column'] === 'power');
        $t->true($case['lookup_value'] > 0);
        $t->true($case['result_value'] > 0);
        $t->same('ok', $case['integrity']);
    };
}

return $tests;
