<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIntegrityRecoveryVacuumYield;

$rows = [
    ['kind' => 'integrity_check', 'source' => 'header', 'page' => 1, 'pointer_map_page' => null, 'message' => 'database header page count 91 does not match file page count 90'],
    ['kind' => 'integrity_check', 'source' => 'freelist', 'page' => 17, 'pointer_map_page' => 2, 'message' => 'freelist trunk chain loops at page 17'],
    ['kind' => 'integrity_check', 'source' => 'freelist', 'page' => 22, 'pointer_map_page' => 2, 'message' => 'freelist page 22 appears more than once'],
    ['kind' => 'integrity_check', 'source' => 'pointer_map', 'page' => 40, 'pointer_map_page' => 2, 'message' => 'pointer-map parent page 0 for btree-page page 40 is not valid'],
    ['kind' => 'integrity_check', 'source' => 'pointer_map', 'page' => 41, 'pointer_map_page' => 2, 'message' => 'freelist page 41 pointer-map type btree-page does not match expected free-page'],
    ['kind' => 'integrity_check', 'source' => 'schema_root', 'page' => 88, 'pointer_map_page' => null, 'message' => 'largest root btree page 88 is beyond the database image'],
    ['kind' => 'integrity_check', 'source' => 'btree', 'page' => 55, 'pointer_map_page' => 2, 'message' => 'btree page 55 cell content area is corrupt'],
    ['kind' => 'foreign_key_check', 'source' => 'foreign_key', 'page' => null, 'pointer_map_page' => null, 'message' => 'foreign key mismatch in main.wp_options rowid 101 references wp_blogs fkid 3'],
    ['kind' => 'integrity_check', 'message' => 'page 63 pointer-map entry is stale after interrupted incremental vacuum'],
    ['kind' => 'integrity_check', 'message' => 'cell 4 on page 64 extends past end of btree page'],
    ['kind' => 'integrity_check', 'message' => 'freelist header count 8 does not match reachable freelist page count 6'],
    ['kind' => 'integrity_check', 'message' => 'database image needs manual operator review before vacuum'],
];

$database = 'not-a-sqlite-database';
$page = static fn (int $offset = 0, int $limit = 77): array => SQLitePragmaIntegrityRecoveryVacuumYield::page($database, $offset, $limit, 'PRAGMA integrity_check', $rows);
$collect = static fn (): array => SQLitePragmaIntegrityRecoveryVacuumYield::collect($database, 'PRAGMA integrity_check', $rows);

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'page status blocked' => [$page, 'status', 'blocked'],
    'page offset' => [$page, 'offset', 0],
    'page limit current next77' => [$page, 'limit', 77],
    'page count' => [$page, 'count', 12],
    'page total' => [$page, 'total', 12],
    'page next offset null' => [$page, 'next_offset', null],
    'page complete' => [$page, 'complete', true],
    'current header count' => [$page, 'current.header', 1],
    'current freelist count' => [$page, 'current.freelist', 3],
    'current pointer map count' => [$page, 'current.pointer_map', 3],
    'current schema root count' => [$page, 'current.schema_root', 1],
    'current btree count' => [$page, 'current.btree', 2],
    'current foreign key count' => [$page, 'current.foreign_key', 1],
    'current integrity fallback count' => [$page, 'current.integrity', 1],
    'current recovery blockers' => [$page, 'current.recovery_blockers', 12],
    'current vacuum blockers' => [$page, 'current.vacuum_blockers', 12],
    'next not ready for vacuum' => [$page, 'next.ready_for_vacuum', false],
    'next recovery required' => [$page, 'next.recovery_required', true],
    'next action count' => [$page, 'next.actions.count', 7],
    'next first action header' => [$page, 'next.actions.0', 'repair_header_before_vacuum'],
    'next second action freelist' => [$page, 'next.actions.1', 'rebuild_freelist_before_incremental_vacuum'],
    'next third action pointer map' => [$page, 'next.actions.2', 'rewrite_pointer_map_before_auto_vacuum'],
    'next schema action' => [$page, 'next.actions.3', 'rebuild_schema_root_before_vacuum'],
    'next btree action' => [$page, 'next.actions.4', 'rebalance_btree_before_vacuum'],
    'next foreign key action' => [$page, 'next.actions.5', 'defer_vacuum_until_foreign_key_check_clears'],
    'next manual action for fallback integrity' => [$page, 'next.actions.6', 'review_integrity_finding_before_vacuum'],
    'next blocking count' => [$page, 'next.blocking.count', 7],
    'next blocking header' => [$page, 'next.blocking.0', 'header'],
    'next blocking freelist' => [$page, 'next.blocking.1', 'freelist'],
    'next blocking pointer map' => [$page, 'next.blocking.2', 'pointer_map'],
    'next blocking schema root' => [$page, 'next.blocking.3', 'schema_root'],
    'next blocking btree' => [$page, 'next.blocking.4', 'btree'],
    'next blocking foreign key' => [$page, 'next.blocking.5', 'foreign_key'],
    'next blocking integrity fallback' => [$page, 'next.blocking.6', 'integrity'],
    'row0 source header' => [$page, 'rows.0.source', 'header'],
    'row0 page one' => [$page, 'rows.0.page', 1],
    'row0 action header repair' => [$page, 'rows.0.action', 'repair_header_before_vacuum'],
    'row0 recovery header reload' => [$page, 'rows.0.recovery', 'reload_header_and_reject_vacuum_until_consistent'],
    'row0 vacuum blocked' => [$page, 'rows.0.vacuum', 'blocked'],
    'row0 blocks vacuum' => [$page, 'rows.0.blocks_vacuum', true],
    'row1 source freelist' => [$page, 'rows.1.source', 'freelist'],
    'row1 pointer map page preserved' => [$page, 'rows.1.pointer_map_page', 2],
    'row1 recovery page specific' => [$page, 'rows.1.recovery', 'repair_freelist_chain_at_page_17'],
    'row1 vacuum recount' => [$page, 'rows.1.vacuum', 'recount_freelist_then_resume'],
    'row3 pointer action' => [$page, 'rows.3.action', 'rewrite_pointer_map_before_auto_vacuum'],
    'row3 pointer recovery page' => [$page, 'rows.3.recovery', 'rewrite_pointer_map_page_2'],
    'row3 pointer vacuum resume' => [$page, 'rows.3.vacuum', 'resume_auto_vacuum_after_pointer_map_rewrite'],
    'row5 schema action' => [$page, 'rows.5.action', 'rebuild_schema_root_before_vacuum'],
    'row5 schema recovery' => [$page, 'rows.5.recovery', 'recover_schema_root_page_chain'],
    'row6 btree action' => [$page, 'rows.6.action', 'rebalance_btree_before_vacuum'],
    'row6 btree recovery page' => [$page, 'rows.6.recovery', 'recover_btree_page_55'],
    'row6 btree vacuum resume' => [$page, 'rows.6.vacuum', 'resume_vacuum_after_btree_rebalance'],
    'row7 foreign key action' => [$page, 'rows.7.action', 'defer_vacuum_until_foreign_key_check_clears'],
    'row7 foreign key recovery' => [$page, 'rows.7.recovery', 'repair_foreign_key_rows_or_parent_indexes'],
    'row8 inferred pointer source' => [$page, 'rows.8.source', 'pointer_map'],
    'row8 inferred page' => [$page, 'rows.8.page', 63],
    'row8 inferred pointer recovery without db map' => [$page, 'rows.8.recovery', 'rewrite_pointer_map_entry'],
    'row9 inferred btree source' => [$page, 'rows.9.source', 'btree'],
    'row9 inferred btree page' => [$page, 'rows.9.page', 64],
    'row10 inferred freelist source' => [$page, 'rows.10.source', 'freelist'],
    'row10 inferred freelist scan' => [$page, 'rows.10.recovery', 'scan_freelist_trunks'],
    'offset two count' => [static fn (): array => $page(2, 4), 'count', 4],
    'offset two next offset' => [static fn (): array => $page(2, 4), 'next_offset', 6],
    'offset two first row page' => [static fn (): array => $page(2, 4), 'rows.0.page', 22],
    'offset two incomplete' => [static fn (): array => $page(2, 4), 'complete', false],
    'offset nine count' => [static fn (): array => $page(9, 4), 'count', 3],
    'offset nine complete' => [static fn (): array => $page(9, 4), 'complete', true],
    'offset nine next null' => [static fn (): array => $page(9, 4), 'next_offset', null],
    'row11 fallback source' => [$page, 'rows.11.source', 'integrity'],
    'row11 fallback action' => [$page, 'rows.11.action', 'review_integrity_finding_before_vacuum'],
    'row11 fallback recovery' => [$page, 'rows.11.recovery', 'manual_integrity_review'],
    'tail offset empty count' => [static fn (): array => $page(12, 3), 'count', 0],
    'tail offset complete' => [static fn (): array => $page(11, 3), 'complete', true],
    'collect count' => [$collect, 'count', 12],
    'collect first action' => [$collect, '0.action', 'repair_header_before_vacuum'],
    'collect last source' => [$collect, '11.source', 'integrity'],
];

$tests = [];
foreach ($cases as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity recovery vacuum current next77 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($callback(), $path));
    };
}

$tests['pragma integrity recovery vacuum current next77 clean page is vacuum ready'] = static function (TestRunner $t) use ($database): void {
    $page = SQLitePragmaIntegrityRecoveryVacuumYield::page($database, 0, 77, 'PRAGMA integrity_check', []);
    $t->same(['status' => 'ok', 'ready' => true, 'required' => false, 'count' => 0], [
        'status' => $page['status'],
        'ready' => $page['next']['ready_for_vacuum'],
        'required' => $page['next']['recovery_required'],
        'count' => $page['count'],
    ]);
};

$tests['pragma integrity recovery vacuum current next77 rejects malformed row'] = static function (TestRunner $t) use ($database): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityRecoveryVacuumYield::page($database, 0, 77, 'PRAGMA integrity_check', [['source' => 'freelist']]));
};

$tests['pragma integrity recovery vacuum current next77 rejects negative offset'] = static function (TestRunner $t) use ($database, $rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityRecoveryVacuumYield::page($database, -1, 77, 'PRAGMA integrity_check', $rows));
};

$tests['pragma integrity recovery vacuum current next77 rejects zero limit'] = static function (TestRunner $t) use ($database, $rows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityRecoveryVacuumYield::page($database, 0, 0, 'PRAGMA integrity_check', $rows));
};

return $tests;
