<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage251 = static function (int $pageCount): string {
    $page = str_repeat("\0", 512);
    $page = substr_replace($page, "SQLite format 3\0", 0, 16);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page[18] = "\x01";
    $page[19] = "\x01";
    $page[21] = "\x40";
    $page[22] = "\x20";
    $page[23] = "\x20";
    $page = substr_replace($page, pack('N', $pageCount), 28, 4);
    $page = substr_replace($page, pack('N', 0), 32, 4);
    $page = substr_replace($page, pack('N', 0), 36, 4);
    $page = substr_replace($page, pack('N', 3), 52, 4);
    $page = substr_replace($page, pack('N', 1), 56, 4);

    return $page;
};

$putPointerMapEntry251 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
    if ($pageNumber === 2 || $pageNumber === 105) {
        return;
    }

    $pointerMapPage = $pageNumber >= 106 ? 105 : 2;
    $pages[$pointerMapPage] = substr_replace(
        $pages[$pointerMapPage],
        chr($type) . pack('N', $parentPageNumber),
        5 * ($pageNumber - $pointerMapPage - 1),
        5,
    );
};

$database251 = static function () use ($makeFirstPage251, $putPointerMapEntry251): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage251(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next251', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(70 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry251($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan251 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan {
    global $database251;

    $database = $database251();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafAdmissionFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next251-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message251 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases251 = [
    'action label' => static fn (): mixed => $plan251()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan251()->admissionSummary()['status'],
    'admission row count' => static fn (): mixed => $plan251()->admissionSummary()['admission_row_count'],
    'admitted pages' => static fn (): mixed => $plan251()->admittedPages(),
    'summary admitted pages' => static fn (): mixed => $plan251()->admissionSummary()['admitted_pages'],
    'summary sealed pages' => static fn (): mixed => $plan251()->admissionSummary()['sealed_pages'],
    'admitted pages match seals' => static fn (): mixed => $plan251()->admissionSummary()['admitted_pages_match_seals'],
    'pointer map admission pages' => static fn (): mixed => $plan251()->pointerMapAdmissionPages(),
    'summary pointer map admission pages' => static fn (): mixed => $plan251()->admissionSummary()['pointer_map_admission_pages'],
    'reusable payload admission pages' => static fn (): mixed => $plan251()->reusablePayloadAdmissionPages(),
    'summary reusable payload admission pages' => static fn (): mixed => $plan251()->admissionSummary()['reusable_payload_admission_pages'],
    'held payload pages' => static fn (): mixed => $plan251()->heldPayloadPages(),
    'summary held payload pages' => static fn (): mixed => $plan251()->admissionSummary()['held_payload_pages'],
    'admission errors' => static fn (): mixed => $plan251()->admissionErrors(),
    'summary admission errors' => static fn (): mixed => $plan251()->admissionSummary()['admission_errors'],
    'all seal tokens match' => static fn (): mixed => $plan251()->admissionSummary()['all_seal_tokens_match'],
    'all pointer maps visible before cursor advance' => static fn (): mixed => $plan251()->admissionSummary()['all_pointer_maps_visible_before_cursor_advance'],
    'all freeblock receipts required' => static fn (): mixed => $plan251()->admissionSummary()['all_freeblock_receipts_required'],
    'all payload cursor advances are safe' => static fn (): mixed => $plan251()->admissionSummary()['all_payload_cursor_advances_are_safe'],
    'all tail pages remain fenced' => static fn (): mixed => $plan251()->admissionSummary()['all_tail_pages_remain_fenced'],
    'token count' => static fn (): mixed => count($plan251()->admissionTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan251()->admissionTokens()),
    'signature length' => static fn (): mixed => strlen($plan251()->admissionSummary()['admission_signature']),
    'current token length' => static fn (): mixed => strlen($plan251()->admissionSummary()['current_source_next251_token']),
    'first row state' => static fn (): mixed => $plan251()->admissionRows()[0]['admission_state'],
    'first channel' => static fn (): mixed => $plan251()->admissionRows()[0]['admission_channel'],
    'first admitted page' => static fn (): mixed => $plan251()->admissionRows()[0]['admitted_page'],
    'first cursor advances' => static fn (): mixed => $plan251()->admissionRows()[0]['source_cursor_can_advance'],
    'second channel' => static fn (): mixed => $plan251()->admissionRows()[1]['admission_channel'],
    'second admitted page' => static fn (): mixed => $plan251()->admissionRows()[1]['admitted_page'],
    'second cursor advances' => static fn (): mixed => $plan251()->admissionRows()[1]['source_cursor_can_advance'],
    'second published freeblocks' => static fn (): mixed => $plan251()->admissionRows()[1]['published_freeblock_pages'],
    'third visible pointer maps' => static fn (): mixed => $plan251()->admissionRows()[2]['visible_pointer_map_pages'],
    'last row admitted page' => static fn (): mixed => $plan251()->admissionRows()[6]['admitted_page'],
    'ordinals' => static fn (): mixed => array_column($plan251()->admissionRows(), 'admission_ordinal'),
    'seal ordinals' => static fn (): mixed => array_column($plan251()->admissionRows(), 'seal_ordinal'),
    'row states' => static fn (): mixed => array_column($plan251()->admissionRows(), 'admission_state'),
    'row seal token flags' => static fn (): mixed => array_column($plan251()->admissionRows(), 'seal_token_matches'),
    'row pointer visibility flags' => static fn (): mixed => array_column($plan251()->admissionRows(), 'pointer_maps_visible_before_cursor_advance'),
    'row freeblock receipt flags' => static fn (): mixed => array_column($plan251()->admissionRows(), 'freeblock_receipt_required'),
    'row cursor advance flags' => static fn (): mixed => array_column($plan251()->admissionRows(), 'source_cursor_can_advance'),
    'row payload safe flags' => static fn (): mixed => array_column($plan251()->admissionRows(), 'payload_cursor_advance_safe'),
    'row tail fence flags' => static fn (): mixed => array_column($plan251()->admissionRows(), 'tail_pages_remain_fenced'),
    'batch size three row count' => static fn (): mixed => $plan251(3)->admissionSummary()['admission_row_count'],
    'batch size three pages' => static fn (): mixed => $plan251(3)->admittedPages(),
    'batch size three reusable payload pages' => static fn (): mixed => $plan251(3)->reusablePayloadAdmissionPages(),
    'dependency closure' => static fn (): mixed => $plan251()->admissionSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan251()->admissionSummary()['non_overlap'], 'does not repeat next248'),
    'seal action' => static fn (): mixed => $plan251()->sealPlan->toArray()['action'],
    'seal row count' => static fn (): mixed => $plan251()->sealPlan->sealSummary()['seal_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message251(static fn () => $plan251(0)),
];

$expected251 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next251',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next251-ready',
    'admission row count' => 7,
    'admitted pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary admitted pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary sealed pages' => [2, 3, 105, 106, 105, 107, 108],
    'admitted pages match seals' => true,
    'pointer map admission pages' => [2, 105],
    'summary pointer map admission pages' => [2, 105],
    'reusable payload admission pages' => [3, 106, 107, 108],
    'summary reusable payload admission pages' => [3, 106, 107, 108],
    'held payload pages' => [],
    'summary held payload pages' => [],
    'admission errors' => [],
    'summary admission errors' => [],
    'all seal tokens match' => true,
    'all pointer maps visible before cursor advance' => true,
    'all freeblock receipts required' => true,
    'all payload cursor advances are safe' => true,
    'all tail pages remain fenced' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first row state' => 'current-source-next251-cursor-advance-admitted',
    'first channel' => 'pointer-map',
    'first admitted page' => 2,
    'first cursor advances' => true,
    'second channel' => 'payload',
    'second admitted page' => 3,
    'second cursor advances' => true,
    'second published freeblocks' => [2, 3],
    'third visible pointer maps' => [2, 105],
    'last row admitted page' => 108,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'seal ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => array_fill(0, 7, 'current-source-next251-cursor-advance-admitted'),
    'row seal token flags' => [true, true, true, true, true, true, true],
    'row pointer visibility flags' => [true, true, true, true, true, true, true],
    'row freeblock receipt flags' => [true, true, true, true, true, true, true],
    'row cursor advance flags' => [true, true, true, true, true, true, true],
    'row payload safe flags' => [true, true, true, true, true, true, true],
    'row tail fence flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three reusable payload pages' => [3, 106, 107, 108],
    'dependency closure' => 'no new support component needed; next251 reuses next248 seal rows and adds current-source cursor advancement admission for pointer-map/freeblock visibility',
    'non overlap' => true,
    'seal action' => 'btree-vacuum-pointermap-freeblock-current-source-next248',
    'seal row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases251 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next251 ' . $name] = static function (TestRunner $t) use ($callback, $expected251, $name): void {
        $t->same($expected251[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next251 admission invariant ' . $index] = static function (TestRunner $t) use ($plan251): void {
        $plan = $plan251();
        $summary = $plan->admissionSummary();

        $t->same([], $plan->admissionErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->admittedPages());
        $t->same([2, 105], $plan->pointerMapAdmissionPages());
        $t->same([3, 106, 107, 108], $plan->reusablePayloadAdmissionPages());
        $t->same([], $plan->heldPayloadPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->admissionRows(), 'admission_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->admissionRows(), 'seal_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->admissionRows(), 'pointer_maps_visible_before_cursor_advance'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->admissionRows(), 'freeblock_receipt_required'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->admissionRows(), 'source_cursor_can_advance'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->admissionRows(), 'payload_cursor_advance_safe'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->admissionRows(), 'tail_pages_remain_fenced'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->admissionTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next251-ready', $summary['status']);
        $t->same(true, $summary['admitted_pages_match_seals']);
        $t->same(true, $summary['all_payload_cursor_advances_are_safe']);
    };
}

return $tests;
