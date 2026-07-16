<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeCellPayloadSplitPlan;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLiteRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;

$tests = [];

$tableLengths = [
    [0, null],
    [1, null],
    [100, null],
    [477, null],
    [478, 17],
    [512, 18],
    [700, 19],
    [1020, 20],
    [1300, 21],
    [1530, 30],
    [2040, 40],
    [2600, 50],
];

$pagesFor = static function (int $payloadLength, int $localPayloadLength, ?int $firstPage): array {
    if ($firstPage === null) {
        return [];
    }

    $pageCount = SQLiteOverflowPage::requiredPageCount($payloadLength - $localPayloadLength);

    return range($firstPage, $firstPage + $pageCount - 1);
};

foreach ($tableLengths as $index => [$payloadLength, $firstPage]) {
    $tests['btree cell payload split current next36 table split case ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($payloadLength, $firstPage, $pagesFor): void {
        $expectedLocal = SQLiteTableLeafCell::localPayloadLength($payloadLength, 512);
        $pages = $pagesFor($payloadLength, $expectedLocal, $firstPage);
        $plan = SQLiteBTreeCellPayloadSplitPlan::tableLeaf($payloadLength, 512, $pages === [] ? null : $pages);
        $expectedOverflow = $payloadLength - $expectedLocal;

        $t->same('table-leaf', $plan->cellType);
        $t->same($expectedLocal, $plan->localPayloadLength);
        $t->same($expectedOverflow, $plan->overflowPayloadLength);
        $t->same($pages !== [], $plan->hasOverflow);
        $t->same($pages[0] ?? null, $plan->firstOverflowPage);
        $t->same($pages, $plan->overflowPageNumbers);
        $t->same(SQLiteOverflowPage::requiredPageCount($expectedOverflow), count($plan->overflowLinks));
        if ($plan->overflowLinks !== []) {
            $t->same(0, $plan->overflowLinks[count($plan->overflowLinks) - 1]['next_page']);
            $t->true($plan->overflowLinks[count($plan->overflowLinks) - 1]['terminal']);
        }
    };
}

$indexLengths = [
    [0, null],
    [1, null],
    [90, null],
    [102, null],
    [103, 80],
    [350, 81],
    [612, 82],
    [900, 84],
    [1200, 91],
    [1515, 94],
    [2035, 97],
    [2540, 101],
];

foreach ($indexLengths as $index => [$payloadLength, $firstPage]) {
    $tests['btree cell payload split current next36 index split case ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($payloadLength, $firstPage, $pagesFor): void {
        $expectedLocal = SQLiteIndexCell::localPayloadLength($payloadLength, 512);
        $pages = $pagesFor($payloadLength, $expectedLocal, $firstPage);
        $plan = SQLiteBTreeCellPayloadSplitPlan::index($payloadLength, 512, $pages === [] ? null : $pages);
        $expectedOverflow = $payloadLength - $expectedLocal;

        $t->same('index', $plan->cellType);
        $t->same($expectedLocal, $plan->localPayloadLength);
        $t->same($expectedOverflow, $plan->overflowPayloadLength);
        $t->same($pages !== [], $plan->hasOverflow);
        $t->same($pages[0] ?? null, $plan->firstOverflowPage);
        $t->same($pages, $plan->overflowPageNumbers);
        $t->same(SQLiteOverflowPage::requiredPageCount($expectedOverflow), count($plan->overflowLinks));
        if (count($pages) > 1) {
            $t->same($pages[1], $plan->overflowLinks[0]['next_page']);
        }
    };
}

$applicationPayloads = [
    ['siteurl', 'https://example.test', []],
    ['home', 'https://example.test/site', []],
    ['active_plugins', str_repeat('plugin-a/plugin.php;', 40), [130]],
    ['theme_mods_twenty', str_repeat('theme-mod:', 90), [131, 132]],
    ['rewrite_rules', str_repeat('rule:', 260), [133, 134, 135]],
    ['_transient_update_plugins', str_repeat('plugin-update:', 180), [136, 137, 138, 139]],
];

foreach ($applicationPayloads as $index => [$optionName, $optionValue, $pages]) {
    $tests['btree cell payload split current next36 application table option ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($optionName, $optionValue, $pages): void {
        $payload = SQLiteRecord::encode([null, $optionName, $optionValue, 'yes']);
        $cell = new SQLiteTableLeafCell(
            strlen($payload),
            10,
            $payload,
            0,
            0,
            SQLiteTableLeafCell::localPayloadLength(strlen($payload), 512),
            $pages[0] ?? null,
        );
        $actualPages = $pages === [] ? [] : range($pages[0], $pages[0] + SQLiteOverflowPage::requiredPageCount(strlen($payload) - $cell->localPayloadLength) - 1);
        $plan = SQLiteBTreeCellPayloadSplitPlan::fromTableLeafCell($cell, 512, $actualPages);

        $t->same(strlen($payload), $plan->payloadLength);
        $t->same($cell->localPayloadLength, $plan->localPayloadLength);
        $t->same(count($actualPages), count($plan->overflowLinks));
        $t->same($actualPages, $plan->overflowPageNumbers);
        $t->same($pages !== [], $plan->hasOverflow);
    };
}

$applicationIndexPayloads = [
    ['autoload', 1, []],
    ['siteurl', 2, []],
    [str_repeat('_transient_timeout_', 18), 3, [180]],
    [str_repeat('_site_transient_update_', 22), 4, [181, 182]],
    [str_repeat('plugin_slug_with_locale_', 35), 5, [183, 184, 185]],
    [str_repeat('long_option_name_for_network_', 42), 6, [186, 187, 188, 189]],
];

foreach ($applicationIndexPayloads as $index => [$optionName, $rowId, $pages]) {
    $tests['btree cell payload split current next36 application index option ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($optionName, $rowId, $pages): void {
        $payload = SQLiteRecord::encode([$optionName, $rowId]);
        $cell = new SQLiteIndexCell(
            strlen($payload),
            $payload,
            0,
            0,
            null,
            SQLiteIndexCell::localPayloadLength(strlen($payload), 512),
            $pages[0] ?? null,
        );
        $actualPages = $pages === [] ? [] : range($pages[0], $pages[0] + SQLiteOverflowPage::requiredPageCount(strlen($payload) - $cell->localPayloadLength) - 1);
        $plan = SQLiteBTreeCellPayloadSplitPlan::fromIndexCell($cell, 512, $actualPages);

        $t->same(strlen($payload), $plan->payloadLength);
        $t->same($cell->localPayloadLength, $plan->localPayloadLength);
        $t->same(count($actualPages), count($plan->overflowLinks));
        $t->same($actualPages, $plan->overflowPageNumbers);
        $t->same($pages !== [], $plan->hasOverflow);
    };
}

for ($index = 0; $index < 14; $index++) {
    $payloadLength = 478 + ($index * 137);
    $localPayloadLength = SQLiteTableLeafCell::localPayloadLength($payloadLength, 512);
    $overflowLength = $payloadLength - $localPayloadLength;
    $pageCount = SQLiteOverflowPage::requiredPageCount($overflowLength);
    $pages = range(250 + ($index * 10), 250 + ($index * 10) + $pageCount - 1);

    $tests['btree cell payload split current next36 generated current next chain ' . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($payloadLength, $localPayloadLength, $overflowLength, $pages): void {
        $plan = SQLiteBTreeCellPayloadSplitPlan::tableLeaf($payloadLength, 512, $pages);
        $links = $plan->overflowLinks;

        $t->same($localPayloadLength, $plan->localPayloadLength);
        $t->same($overflowLength, $plan->overflowPayloadLength);
        $t->same($pages, array_column($links, 'page'));
        $t->same([...array_slice($pages, 1), 0], array_column($links, 'next_page'));
        $t->same($overflowLength, array_sum(array_column($links, 'payload_bytes')));
    };
}

$tests['btree cell payload split current next36 rejects overflow pages for local payload'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeCellPayloadSplitPlan::tableLeaf(20, 512, [9]));
};

$tests['btree cell payload split current next36 rejects missing overflow page list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeCellPayloadSplitPlan::tableLeaf(900));
};

$tests['btree cell payload split current next36 rejects short overflow page list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeCellPayloadSplitPlan::index(1400, 512, [9]));
};

$tests['btree cell payload split current next36 rejects duplicate overflow page numbers'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeCellPayloadSplitPlan::tableLeaf(1020, 512, [9, 9]));
};

return $tests;
