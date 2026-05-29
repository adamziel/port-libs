<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePointerMapEntry;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPointerMapCurrentSourceNext;

$pageSize119 = 512;
$pageCount119 = 88;

$putPointerMapEntry119 = static function (string $page, int $pageNumber, int $type, int $parent): string {
    return substr_replace($page, chr($type) . pack('N', $parent), 5 * ($pageNumber - 3), 5);
};

$makeDatabase119 = static function (array $invalidPages = [], array $freelistPages = []) use ($pageSize119, $pageCount119, $putPointerMapEntry119): string {
    $header = str_repeat("\0", $pageSize119);
    $header = substr_replace($header, "SQLite format 3\0", 0, 16);
    $header = substr_replace($header, pack('n', $pageSize119), 16, 2);
    $header[18] = "\x01";
    $header[19] = "\x01";
    $header = substr_replace($header, pack('N', $pageCount119), 28, 4);
    $header = substr_replace($header, pack('N', $freelistPages[0] ?? 0), 32, 4);
    $header = substr_replace($header, pack('N', count($freelistPages)), 36, 4);
    $header = substr_replace($header, pack('N', 3), 52, 4);
    $header = substr_replace($header, pack('N', 1), 56, 4);

    $pointerMap = str_repeat("\0", $pageSize119);
    $pointerMap = $putPointerMapEntry119($pointerMap, 3, SQLitePointerMapEntry::ROOT_PAGE, 0);
    for ($pageNumber = 4; $pageNumber <= $pageCount119; $pageNumber++) {
        $type = in_array($pageNumber, $freelistPages, true) ? SQLitePointerMapEntry::FREE_PAGE : SQLitePointerMapEntry::BTREE_PAGE;
        $parent = $type === SQLitePointerMapEntry::FREE_PAGE ? 0 : 3;
        if (in_array($pageNumber, $invalidPages, true)) {
            $parent = 0;
        }
        $pointerMap = $putPointerMapEntry119($pointerMap, $pageNumber, $type, $parent);
    }

    $pages = [$header, $pointerMap];
    for ($pageNumber = 3; $pageNumber <= $pageCount119; $pageNumber++) {
        $page = str_repeat("\0", $pageSize119);
        if ($pageNumber === ($freelistPages[0] ?? null)) {
            $page = substr_replace($page, pack('N', 0), 0, 4);
            $page = substr_replace($page, pack('N', max(0, count($freelistPages) - 1)), 4, 4);
            $offset = 8;
            foreach (array_slice($freelistPages, 1) as $freePage) {
                $page = substr_replace($page, pack('N', $freePage), $offset, 4);
                $offset += 4;
            }
        }
        $pages[] = $page;
    }

    return implode('', $pages);
};

$current119 = $makeDatabase119([4, 5, 6, 7, 8, 9, 10, 11]);
$nextClean119 = $makeDatabase119([]);
$nextPartial119 = $makeDatabase119([8, 9, 10, 11]);
$nextIntroduced119 = $makeDatabase119([4, 5, 6, 7, 8, 9, 10, 11, 12, 13]);
$currentSource119 = '6b824ac24854056466145761d32a9f27720d286a';
$nextSource119 = 'pragma-integrity-check-pointermap-current-source-next119';

$compareClean119 = static fn (): array => SQLitePragmaIntegrityPointerMapCurrentSourceNext::compare($current119, $nextClean119, $currentSource119, $nextSource119);
$comparePartial119 = static fn (): array => SQLitePragmaIntegrityPointerMapCurrentSourceNext::compare($current119, $nextPartial119, $currentSource119, $nextSource119);
$compareIntroduced119 = static fn (): array => SQLitePragmaIntegrityPointerMapCurrentSourceNext::compare($current119, $nextIntroduced119, $currentSource119, $nextSource119);
$pageClean119 = static fn (): array => SQLitePragmaIntegrityPointerMapCurrentSourceNext::page($current119, $nextClean119, $currentSource119, $nextSource119, 0, 119);
$pagePartial119 = static fn (): array => SQLitePragmaIntegrityPointerMapCurrentSourceNext::page($current119, $nextPartial119, $currentSource119, $nextSource119, 3, 4);

$valueAt119 = static function (mixed $value, string $path): mixed {
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

$cases119 = [
    'clean status resolved' => [$compareClean119, 'status', 'next_resolved_pointer_map_integrity_findings'],
    'clean reason' => [$compareClean119, 'reason', 'pragma_integrity_check_pointermap_current_source_next119'],
    'clean normalized sql' => [$compareClean119, 'integrity_sql', 'pragma integrity_check'],
    'clean current source id' => [$compareClean119, 'current_source', $currentSource119],
    'clean next source id' => [$compareClean119, 'next_source', $nextSource119],
    'clean current total' => [$compareClean119, 'current.total', 8],
    'clean current pointer map count' => [$compareClean119, 'current.pointer_map', 8],
    'clean current freelist count' => [$compareClean119, 'current.freelist', 0],
    'clean next total' => [$compareClean119, 'next.total', 0],
    'clean next pointer map count' => [$compareClean119, 'next.pointer_map', 0],
    'clean next ready' => [$compareClean119, 'next.ready', true],
    'clean next blocking empty' => [$compareClean119, 'next.blocking.count', 0],
    'clean resolved count' => [$compareClean119, 'resolved_count', 8],
    'clean persisting count' => [$compareClean119, 'persisting_count', 0],
    'clean introduced count' => [$compareClean119, 'introduced_count', 0],
    'clean must not block commit' => [$compareClean119, 'must_block_commit', false],
    'clean first resolved snapshot' => [$compareClean119, 'resolved.0.snapshot', 'current'],
    'clean first resolved page' => [$compareClean119, 'resolved.0.page', 4],
    'clean first resolved source' => [$compareClean119, 'resolved.0.source', 'pointer_map'],
    'clean first resolved pointer map page' => [$compareClean119, 'resolved.0.pointer_map_page', 2],
    'clean first resolved type' => [$compareClean119, 'resolved.0.pointer_map_type', 'btree-page'],
    'clean first resolved parent' => [$compareClean119, 'resolved.0.pointer_map_parent', 0],
    'clean final resolved page' => [$compareClean119, 'resolved.7.page', 11],
    'clean dependency count' => [$compareClean119, 'dependencies.count', 3],
    'partial status' => [$comparePartial119, 'status', 'next_partially_resolved_pointer_map_integrity_findings'],
    'partial next total' => [$comparePartial119, 'next.total', 4],
    'partial resolved count' => [$comparePartial119, 'resolved_count', 4],
    'partial persisting count' => [$comparePartial119, 'persisting_count', 4],
    'partial introduced count' => [$comparePartial119, 'introduced_count', 0],
    'partial blocks commit' => [$comparePartial119, 'must_block_commit', true],
    'partial blocking count' => [$comparePartial119, 'next.blocking.count', 1],
    'partial blocking reason' => [$comparePartial119, 'next.blocking.0', 'persisting_pointer_map_integrity'],
    'partial first resolved page' => [$comparePartial119, 'resolved.0.page', 4],
    'partial last resolved page' => [$comparePartial119, 'resolved.3.page', 7],
    'partial first persisting page' => [$comparePartial119, 'persisting.0.page', 8],
    'partial final persisting page' => [$comparePartial119, 'persisting.3.page', 11],
    'introduced status' => [$compareIntroduced119, 'status', 'next_introduced_pointer_map_integrity_findings'],
    'introduced next total' => [$compareIntroduced119, 'next.total', 10],
    'introduced resolved count' => [$compareIntroduced119, 'resolved_count', 0],
    'introduced persisting count' => [$compareIntroduced119, 'persisting_count', 8],
    'introduced introduced count' => [$compareIntroduced119, 'introduced_count', 2],
    'introduced blocking count' => [$compareIntroduced119, 'next.blocking.count', 2],
    'introduced blocking persistent' => [$compareIntroduced119, 'next.blocking.0', 'persisting_pointer_map_integrity'],
    'introduced blocking introduced' => [$compareIntroduced119, 'next.blocking.1', 'introduced_pointer_map_integrity'],
    'introduced first new page' => [$compareIntroduced119, 'introduced.0.page', 12],
    'introduced final new page' => [$compareIntroduced119, 'introduced.1.page', 13],
    'page clean status' => [$pageClean119, 'status', 'next_resolved_pointer_map_integrity_findings'],
    'page clean offset' => [$pageClean119, 'offset', 0],
    'page clean limit' => [$pageClean119, 'limit', 119],
    'page clean count' => [$pageClean119, 'count', 8],
    'page clean total' => [$pageClean119, 'total', 8],
    'page clean complete' => [$pageClean119, 'complete', true],
    'page clean next offset null' => [$pageClean119, 'next_offset', null],
    'page clean first transition' => [$pageClean119, 'rows.0.transition', 'resolved'],
    'page clean final transition' => [$pageClean119, 'rows.7.transition', 'resolved'],
    'page clean final page' => [$pageClean119, 'rows.7.page', 11],
    'page partial offset' => [$pagePartial119, 'offset', 3],
    'page partial count' => [$pagePartial119, 'count', 4],
    'page partial total' => [$pagePartial119, 'total', 8],
    'page partial next offset' => [$pagePartial119, 'next_offset', 7],
    'page partial complete false' => [$pagePartial119, 'complete', false],
    'page partial first row page' => [$pagePartial119, 'rows.0.page', 7],
    'page partial first transition resolved' => [$pagePartial119, 'rows.0.transition', 'resolved'],
    'page partial second transition persisting' => [$pagePartial119, 'rows.1.transition', 'persisting'],
    'page partial second row page' => [$pagePartial119, 'rows.1.page', 8],
    'page partial final row page' => [$pagePartial119, 'rows.3.page', 10],
];

$tests = [];
foreach ($cases119 as $name => [$callback, $path, $expected]) {
    $tests['pragma integrity pointermap current source next119 ' . $name] = static function (TestRunner $t) use ($callback, $valueAt119, $path, $expected): void {
        $t->same($expected, $valueAt119($callback(), $path));
    };
}

$tests['pragma integrity pointermap current source next119 database hashes change between current and next bytes'] = static function (TestRunner $t) use ($compareClean119): void {
    $comparison = $compareClean119();
    $t->same(64, strlen($comparison['current']['database_hash']));
    $t->same(64, strlen($comparison['next']['database_hash']));
    $t->same(true, $comparison['current']['database_hash'] !== $comparison['next']['database_hash']);
};

$tests['pragma integrity pointermap current source next119 quick check skips pointer map diagnostics'] = static function (TestRunner $t) use ($current119, $nextClean119, $currentSource119, $nextSource119): void {
    $comparison = SQLitePragmaIntegrityPointerMapCurrentSourceNext::compare($current119, $nextClean119, $currentSource119, $nextSource119, 'PRAGMA quick_check');
    $t->same('pointer_map_integrity_clean', $comparison['status']);
    $t->same('pragma quick_check', $comparison['integrity_sql']);
    $t->same(0, $comparison['current']['total']);
    $t->same(0, $comparison['resolved_count']);
    $t->same(true, $comparison['next']['ready']);
};

$tests['pragma integrity pointermap current source next119 rejects negative offset'] = static function (TestRunner $t) use ($current119, $nextClean119, $currentSource119, $nextSource119): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPointerMapCurrentSourceNext::page($current119, $nextClean119, $currentSource119, $nextSource119, -1, 119));
};

$tests['pragma integrity pointermap current source next119 rejects zero limit'] = static function (TestRunner $t) use ($current119, $nextClean119, $currentSource119, $nextSource119): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPointerMapCurrentSourceNext::page($current119, $nextClean119, $currentSource119, $nextSource119, 0, 0));
};

$tests['pragma integrity pointermap current source next119 rejects blank sources'] = static function (TestRunner $t) use ($current119, $nextClean119, $nextSource119): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPointerMapCurrentSourceNext::compare($current119, $nextClean119, ' ', $nextSource119));
};

return $tests;
