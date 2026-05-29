<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;
use PortLibs\LibSqlite\SQLiteTableLeafPage;

$makeFirstPage247 = static function (int $pageCount): string {
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

$putPointerMapEntry247 = static function (array &$pages, int $pageNumber, int $type, int $parentPageNumber): void {
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

$database247 = static function () use ($makeFirstPage247, $putPointerMapEntry247): SQLiteDatabase {
    $pages = array_fill(1, 110, str_repeat("\0", 512));
    $pages[1] = $makeFirstPage247(110);
    $pages[2] = str_repeat("\0", 512);
    $pages[3] = SQLiteTableLeafPage::assemble([
        SQLiteTableLeafCell::encode(1, SQLiteRecord::encode([null, 'siteurl', 'https://example.test'])),
        SQLiteTableLeafCell::encode(2, SQLiteRecord::encode([null, '_transient_next247', str_repeat('cache:', 42)])),
        SQLiteTableLeafCell::encode(3, SQLiteRecord::encode([null, 'rewrite_rules', str_repeat('rewrite:', 8)])),
    ]);
    $pages[105] = str_repeat("\0", 512);
    foreach ([106 => 107, 107 => 108, 108 => 109, 109 => 110, 110 => 0] as $pageNumber => $nextPage) {
        $pages[$pageNumber] = pack('N', $nextPage) . str_repeat(chr(90 + ($pageNumber - 105)), 508);
    }

    foreach ([
        3 => [SQLitePointerMapEntry::ROOT_PAGE, 0],
        106 => [SQLitePointerMapEntry::FIRST_OVERFLOW_PAGE, 3],
        107 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 106],
        108 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 107],
        109 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 108],
        110 => [SQLitePointerMapEntry::OVERFLOW_PAGE, 109],
    ] as $pageNumber => [$type, $parent]) {
        $putPointerMapEntry247($pages, $pageNumber, $type, $parent);
    }

    return SQLiteDatabase::fromBytes(implode('', $pages));
};

$plan247 = static function (int $batchSize = 2): SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan {
    global $database247;

    $database = $database247();
    $deletedPage = SQLiteTableLeafPage::deleteCellByRowId($database->page(3), 2, secureDelete: true);

    return SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafCheckpointFromDeleteResult(
        $database,
        3,
        [
            'page' => $deletedPage,
            'rowid' => 2,
            'obsolete_overflow_page_numbers' => [106, 107, 108, 109, 110],
        ],
        2,
        str_repeat('next247-current-source-', 50),
        3,
        true,
        $batchSize,
    );
};

$message247 = static function (callable $callback): string {
    try {
        $callback();
    } catch (Throwable $exception) {
        return $exception->getMessage();
    }

    return 'not rejected';
};

$cases247 = [
    'action label' => static fn (): mixed => $plan247()->toArray()['action'],
    'summary status' => static fn (): mixed => $plan247()->checkpointSummary()['status'],
    'checkpoint row count' => static fn (): mixed => $plan247()->checkpointSummary()['checkpoint_row_count'],
    'checkpoint pages' => static fn (): mixed => $plan247()->checkpointPages(),
    'summary checkpoint pages' => static fn (): mixed => $plan247()->checkpointSummary()['checkpoint_pages'],
    'publish pages' => static fn (): mixed => $plan247()->checkpointSummary()['publish_pages'],
    'checkpoint pages match publish pages' => static fn (): mixed => $plan247()->checkpointSummary()['checkpoint_pages_match_publish_pages'],
    'pointer map checkpoint pages' => static fn (): mixed => $plan247()->pointerMapCheckpointPages(),
    'summary pointer map checkpoint pages' => static fn (): mixed => $plan247()->checkpointSummary()['pointer_map_checkpoint_pages'],
    'payload checkpoint pages' => static fn (): mixed => $plan247()->payloadCheckpointPages(),
    'summary payload checkpoint pages' => static fn (): mixed => $plan247()->checkpointSummary()['payload_checkpoint_pages'],
    'duplicate pointer map checkpoint pages' => static fn (): mixed => $plan247()->duplicatePointerMapCheckpointPages(),
    'summary duplicate pointer map checkpoint pages' => static fn (): mixed => $plan247()->checkpointSummary()['duplicate_pointer_map_checkpoint_pages'],
    'checkpoint errors' => static fn (): mixed => $plan247()->checkpointErrors(),
    'summary checkpoint errors' => static fn (): mixed => $plan247()->checkpointSummary()['checkpoint_errors'],
    'all publish tokens match' => static fn (): mixed => $plan247()->checkpointSummary()['all_publish_tokens_match'],
    'all checkpoint links current' => static fn (): mixed => $plan247()->checkpointSummary()['all_checkpoint_links_current'],
    'all payload checkpoint after pointer map' => static fn (): mixed => $plan247()->checkpointSummary()['all_payload_checkpoint_after_pointer_map'],
    'all freeblock receipts checkpointed' => static fn (): mixed => $plan247()->checkpointSummary()['all_freeblock_receipts_checkpointed'],
    'all tail pages excluded from checkpoint' => static fn (): mixed => $plan247()->checkpointSummary()['all_tail_pages_excluded_from_checkpoint'],
    'token count' => static fn (): mixed => count($plan247()->checkpointTokens()),
    'token lengths' => static fn (): mixed => array_map('strlen', $plan247()->checkpointTokens()),
    'signature length' => static fn (): mixed => strlen($plan247()->checkpointSummary()['checkpoint_signature']),
    'current token length' => static fn (): mixed => strlen($plan247()->checkpointSummary()['current_source_next247_token']),
    'first row state' => static fn (): mixed => $plan247()->checkpointRows()[0]['checkpoint_state'],
    'second row state' => static fn (): mixed => $plan247()->checkpointRows()[1]['checkpoint_state'],
    'first row channel' => static fn (): mixed => $plan247()->checkpointRows()[0]['checkpoint_channel'],
    'second row channel' => static fn (): mixed => $plan247()->checkpointRows()[1]['checkpoint_channel'],
    'first pointer generations' => static fn (): mixed => $plan247()->checkpointRows()[0]['checkpointed_pointer_map_generations'],
    'second checkpointed payloads' => static fn (): mixed => $plan247()->checkpointRows()[1]['checkpointed_payload_pages'],
    'fourth checkpointed payloads' => static fn (): mixed => $plan247()->checkpointRows()[3]['checkpointed_payload_pages'],
    'fifth duplicate pointer map' => static fn (): mixed => $plan247()->checkpointRows()[4]['duplicate_pointer_map_checkpoint'],
    'fifth pointer generations' => static fn (): mixed => $plan247()->checkpointRows()[4]['checkpointed_pointer_map_generations'],
    'last row next page' => static fn (): mixed => $plan247()->checkpointRows()[6]['next_checkpoint_page'],
    'ordinals' => static fn (): mixed => array_column($plan247()->checkpointRows(), 'checkpoint_ordinal'),
    'publish ordinals' => static fn (): mixed => array_column($plan247()->checkpointRows(), 'publish_ordinal'),
    'row states' => static fn (): mixed => array_column($plan247()->checkpointRows(), 'checkpoint_state'),
    'row publish token flags' => static fn (): mixed => array_column($plan247()->checkpointRows(), 'publish_token_matches'),
    'row link flags' => static fn (): mixed => array_column($plan247()->checkpointRows(), 'checkpoint_link_current'),
    'row payload checkpoint flags' => static fn (): mixed => array_column($plan247()->checkpointRows(), 'payload_checkpoint_after_pointer_map'),
    'row duplicate checkpoint flags' => static fn (): mixed => array_column($plan247()->checkpointRows(), 'duplicate_pointer_map_checkpointed'),
    'row freeblock flags' => static fn (): mixed => array_column($plan247()->checkpointRows(), 'freeblock_receipt_checkpointed'),
    'row tail exclusion flags' => static fn (): mixed => array_column($plan247()->checkpointRows(), 'tail_page_excluded_from_checkpoint'),
    'batch size three row count' => static fn (): mixed => $plan247(3)->checkpointSummary()['checkpoint_row_count'],
    'batch size three pages' => static fn (): mixed => $plan247(3)->checkpointPages(),
    'batch size three duplicate pointer maps' => static fn (): mixed => $plan247(3)->duplicatePointerMapCheckpointPages(),
    'dependency closure' => static fn (): mixed => $plan247()->checkpointSummary()['dependency_closure'],
    'non overlap' => static fn (): mixed => str_contains($plan247()->checkpointSummary()['non_overlap'], 'does not repeat next244'),
    'publish action' => static fn (): mixed => $plan247()->publishPlan->toArray()['action'],
    'publish row count' => static fn (): mixed => $plan247()->publishPlan->publishSummary()['publish_row_count'],
    'bad batch size rejected' => static fn (): mixed => $message247(static fn () => $plan247(0)),
];

$expected247 = [
    'action label' => 'btree-vacuum-pointermap-freeblock-current-source-next247',
    'summary status' => 'btree-vacuum-pointermap-freeblock-current-source-next247-ready',
    'checkpoint row count' => 7,
    'checkpoint pages' => [2, 3, 105, 106, 105, 107, 108],
    'summary checkpoint pages' => [2, 3, 105, 106, 105, 107, 108],
    'publish pages' => [2, 3, 105, 106, 105, 107, 108],
    'checkpoint pages match publish pages' => true,
    'pointer map checkpoint pages' => [2, 105],
    'summary pointer map checkpoint pages' => [2, 105],
    'payload checkpoint pages' => [3, 106, 107, 108],
    'summary payload checkpoint pages' => [3, 106, 107, 108],
    'duplicate pointer map checkpoint pages' => [105],
    'summary duplicate pointer map checkpoint pages' => [105],
    'checkpoint errors' => [],
    'summary checkpoint errors' => [],
    'all publish tokens match' => true,
    'all checkpoint links current' => true,
    'all payload checkpoint after pointer map' => true,
    'all freeblock receipts checkpointed' => true,
    'all tail pages excluded from checkpoint' => true,
    'token count' => 7,
    'token lengths' => [64, 64, 64, 64, 64, 64, 64],
    'signature length' => 64,
    'current token length' => 64,
    'first row state' => 'current-source-next247-checkpoint-admitted',
    'second row state' => 'current-source-next247-checkpoint-admitted',
    'first row channel' => 'pointer-map',
    'second row channel' => 'payload',
    'first pointer generations' => ['2:1'],
    'second checkpointed payloads' => [3],
    'fourth checkpointed payloads' => [3, 106],
    'fifth duplicate pointer map' => true,
    'fifth pointer generations' => ['2:1', '105:2'],
    'last row next page' => null,
    'ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'publish ordinals' => [1, 2, 3, 4, 5, 6, 7],
    'row states' => array_fill(0, 7, 'current-source-next247-checkpoint-admitted'),
    'row publish token flags' => [true, true, true, true, true, true, true],
    'row link flags' => [true, true, true, true, true, true, true],
    'row payload checkpoint flags' => [true, true, true, true, true, true, true],
    'row duplicate checkpoint flags' => [true, true, true, true, true, true, true],
    'row freeblock flags' => [true, true, true, true, true, true, true],
    'row tail exclusion flags' => [true, true, true, true, true, true, true],
    'batch size three row count' => 6,
    'batch size three pages' => [2, 3, 105, 106, 107, 108],
    'batch size three duplicate pointer maps' => [],
    'dependency closure' => 'no new support component needed; next247 reuses next244 publish cursor rows and adds checkpoint admission for pointer-map/freeblock visibility',
    'non overlap' => true,
    'publish action' => 'btree-vacuum-pointermap-freeblock-current-source-next244',
    'publish row count' => 7,
    'bad batch size rejected' => 'SQLite b-tree vacuum pointer-map freeblock next174 requires a positive cursor batch size',
];

$tests = [];

foreach ($cases247 as $name => $callback) {
    $tests['btree vacuum pointermap freeblock current source next247 ' . $name] = static function (TestRunner $t) use ($callback, $expected247, $name): void {
        $t->same($expected247[$name], $callback());
    };
}

foreach (range(1, 80) as $index) {
    $tests['btree vacuum pointermap freeblock current source next247 checkpoint invariant ' . $index] = static function (TestRunner $t) use ($plan247): void {
        $plan = $plan247();
        $summary = $plan->checkpointSummary();

        $t->same([], $plan->checkpointErrors());
        $t->same([2, 3, 105, 106, 105, 107, 108], $plan->checkpointPages());
        $t->same([2, 105], $plan->pointerMapCheckpointPages());
        $t->same([3, 106, 107, 108], $plan->payloadCheckpointPages());
        $t->same([105], $plan->duplicatePointerMapCheckpointPages());
        $t->same([1, 2, 3, 4, 5, 6, 7], array_column($plan->checkpointRows(), 'checkpoint_ordinal'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'publish_token_matches'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'checkpoint_link_current'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'payload_checkpoint_after_pointer_map'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'duplicate_pointer_map_checkpointed'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'freeblock_receipt_checkpointed'));
        $t->same([true, true, true, true, true, true, true], array_column($plan->checkpointRows(), 'tail_page_excluded_from_checkpoint'));
        $t->same([64, 64, 64, 64, 64, 64, 64], array_map('strlen', $plan->checkpointTokens()));
        $t->same('btree-vacuum-pointermap-freeblock-current-source-next247-ready', $summary['status']);
        $t->same(true, $summary['checkpoint_pages_match_publish_pages']);
        $t->same(true, $summary['all_checkpoint_links_current']);
        $t->same(true, $summary['all_tail_pages_excluded_from_checkpoint']);
    };
}

return $tests;
