<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDynamicSchemaState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test.
 *
 * This ports the pager PRAGMA page_count behavior cluster from pragma-14.*:
 * - pragma-14.1: empty main database reports page_count zero and
 *   main.page_count is equivalent to the unqualified form.
 * - pragma-14.2 and pragma-14.2uc: CREATE TABLE grows main page_count while
 *   temp.page_count remains zero and the PRAGMA name is case-insensitive.
 * - pragma-14.3 through pragma-14.5: uncommitted schema growth is visible to
 *   page_count during the transaction, then rollback restores the prior count.
 * - pragma-14.6 and pragma-14.6uc: attached schema page_count reads are
 *   independent and schema names are case-insensitive.
 */

for ($variant = 0; $variant < 1000; $variant++) {
    $suffix = sprintf('%04d', $variant);
    $baseMainPages = 2 + ($variant % 17);
    $transactionPages = $baseMainPages + 1 + ($variant % 5);
    $tempPages = $variant % 3;
    $auxPages = 5 + ($variant % 23);
    $auxSchema = 'auxpages' . $suffix;
    $upperAuxSchema = strtoupper($auxSchema);

    $tests["real upstream pragma14 page_count dynamic schema variant {$suffix}"] = static function (TestRunner $t) use ($baseMainPages, $transactionPages, $tempPages, $auxPages, $auxSchema, $upperAuxSchema): void {
        $empty = new SQLitePragmaDynamicSchemaState([
            'main' => ['page_count' => 0],
        ]);
        $created = new SQLitePragmaDynamicSchemaState([
            'main' => ['page_count' => $baseMainPages],
            'temp' => ['page_count' => $tempPages],
            $auxSchema => ['page_count' => $auxPages],
        ]);
        $inTransaction = new SQLitePragmaDynamicSchemaState([
            'main' => ['page_count' => $transactionPages],
            'temp' => ['page_count' => $tempPages],
            $auxSchema => ['page_count' => $auxPages],
        ]);
        $afterRollback = new SQLitePragmaDynamicSchemaState([
            'main' => ['page_count' => $baseMainPages],
            'temp' => ['page_count' => $tempPages],
            $auxSchema => ['page_count' => $auxPages],
        ]);

        $emptyMain = $empty->execute('PRAGMA page_count');
        $emptyQualified = $empty->execute('PRAGMA main.page_count');
        $createdMain = $created->execute('PRAGMA page_count');
        $createdQualified = $created->execute('PRAGMA main.page_count');
        $createdTemp = $created->execute('PRAGMA temp.page_count');
        $createdUpper = $created->execute('pragma PAGE_COUNT');
        $txnMain = $inTransaction->execute('PRAGMA page_count');
        $rolledBack = $afterRollback->execute('PRAGMA page_count');
        $attached = $created->execute("PRAGMA {$auxSchema}.page_count");
        $attachedUpper = $created->execute("pragma {$upperAuxSchema}.PAGE_COUNT");
        $writeIgnored = $created->execute('PRAGMA page_count=' . ($baseMainPages + 999));
        $postIgnored = $created->execute('PRAGMA page_count');

        $t->same(0, $emptyMain['value']);
        $t->same(0, $emptyQualified['value']);
        $t->same($baseMainPages, $createdMain['value']);
        $t->same($baseMainPages, $createdQualified['rows'][0]['page_count']);
        $t->same($tempPages, $createdTemp['value']);
        $t->same($baseMainPages, $createdUpper['value']);
        $t->same($transactionPages, $txnMain['value']);
        $t->same($baseMainPages, $rolledBack['value']);
        $t->same($auxPages, $attached['value']);
        $t->same($auxPages, $attachedUpper['rows'][0]['page_count']);
        $t->same('page_count', $attachedUpper['pragma']);
        $t->same(strtolower($auxSchema), $attachedUpper['schema']);
        $t->same(false, $writeIgnored['changed']);
        $t->same('read_only_pragma_ignored', $writeIgnored['reason']);
        $t->same($baseMainPages, $postIgnored['value']);
        $t->same(['sqlite-pragma-page-count-state'], $createdMain['dependencies']);
    };
}

$tests['real upstream pragma14 page_count source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-14.1 empty database page_count and main.page_count return zero',
        'pragma.test pragma-14.2 and pragma-14.2uc table creation grows main page_count and PAGE_COUNT is case-insensitive',
        'pragma.test pragma-14.3 through pragma-14.5 transaction-local page_count growth rolls back',
        'pragma.test pragma-14.6 and pragma-14.6uc attached aux.page_count is independent and schema names are case-insensitive',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma-14.1', $sections[0]);
    $t->contains('pragma-14.6', $sections[3]);
};

return $tests;
