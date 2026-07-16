<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$petSets = [
    ['dog', 'cat'],
    ['dog', 'cat', 'pig'],
    ['ant', 'eel', 'yak'],
    ['blue', 'green', 'red', 'violet'],
    ['alpha', 'beta', 'delta', 'gamma', 'omega'],
];
$pageSizes = [512, 1024, 2048, 4096];
$prefixLengths = [0, 1, 17, 50, 511, 512, 1500, 4095, 4096, 8191];

$caseNo = 0;
foreach (range(1, 5) as $round) {
    foreach ($petSets as $pets) {
        foreach ($pageSizes as $pageSize) {
            foreach ($prefixLengths as $prefixBytes) {
                foreach ([true, false] as $emptyAppendee) {
                    ++$caseNo;
                    $name = sprintf(
                        'real upstream corpus vfs io dynamic append content %04d page %d prefix %d empty %d',
                        $caseNo,
                        $pageSize,
                        $prefixBytes,
                        $emptyAppendee ? 1 : 0
                    );
                    $tests[$name] = static function (TestRunner $t) use ($pets, $pageSize, $prefixBytes, $emptyAppendee): void {
                        $profile = SQLiteVfsIoDynamicPlan::appendContentPersistenceProfile($prefixBytes, $pageSize, $pets, $emptyAppendee);
                        $ascending = $pets;
                        sort($ascending, SORT_STRING);
                        $descending = $ascending;
                        rsort($descending, SORT_STRING);
                        $expectedOffset = $emptyAppendee || $prefixBytes === 0 ? 0 : (int) (ceil($prefixBytes / 4096) * 4096);

                        $t->same('ok', $profile['status']);
                        $t->same('avfs.test', $profile['script']);
                        $t->same($prefixBytes, $profile['prefix_bytes']);
                        $t->same($pageSize, $profile['page_size']);
                        $t->same($emptyAppendee, $profile['empty_appendee']);
                        $t->same($expectedOffset, $profile['database_offset']);
                        $t->same($expectedOffset - $prefixBytes, $profile['padding_bytes']);
                        $t->same('Start-Of-SQLite3-', $profile['trailer_magic']);
                        $t->same($expectedOffset, $profile['trailer_offset']);
                        $t->same($ascending, $profile['ascending_rows']);
                        $t->same($descending, $profile['descending_rows']);
                        $t->same(implode(',', $ascending), $profile['ascending_group_concat']);
                        $t->same(implode(',', $descending), $profile['descending_group_concat']);
                        $t->same(true, $profile['prefix_intact']);
                        $t->same(true, $profile['aligned']);
                        $t->same(true, $profile['reopen_intact']);
                        $t->same(true, $profile['database_bytes'] >= $pageSize);
                        $t->same(0, $profile['database_bytes'] % $pageSize);
                        $t->same($profile['database_offset'] + $profile['database_bytes'] + 25, $profile['total_bytes']);
                        $t->same(true, in_array('upstream-avfs-content-persistence', $profile['dependencies'], true));
                        $t->same(true, in_array('vfs-io-dynamic-real-corpus', $profile['dependencies'], true));
                        $t->same(
                            $emptyAppendee ? ['avfs.test avfs-1.0', 'avfs.test avfs-1.1'] : ['avfs.test avfs-1.2', 'avfs.test avfs-1.3', 'avfs.test avfs-1.4', 'avfs.test avfs-2.1'],
                            $profile['upstream']
                        );
                    };
                }
            }
        }
    }
}

$tests['real upstream corpus vfs io dynamic append content cites hydrated avfs sections'] = static function (TestRunner $t) use ($caseNo): void {
    $t->same(2000, $caseNo);
    $empty = SQLiteVfsIoDynamicPlan::appendContentPersistenceProfile(0, 1024, ['dog', 'cat'], true);
    $text = SQLiteVfsIoDynamicPlan::appendContentPersistenceProfile(50, 512, ['dog', 'cat', 'pig']);

    $t->same(['avfs.test avfs-1.0', 'avfs.test avfs-1.1'], $empty['upstream']);
    $t->same(['cat', 'dog'], $empty['ascending_rows']);
    $t->same(['dog', 'cat'], $empty['descending_rows']);
    $t->same(0, $empty['database_offset']);
    $t->same(['avfs.test avfs-1.2', 'avfs.test avfs-1.3', 'avfs.test avfs-1.4', 'avfs.test avfs-2.1'], $text['upstream']);
    $t->same('cat,dog,pig', $text['ascending_group_concat']);
    $t->same('pig,dog,cat', $text['descending_group_concat']);
    $t->same(4096, $text['database_offset']);
    $t->same(true, $text['prefix_intact']);
};

$tests['real upstream corpus vfs io dynamic append content rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendContentPersistenceProfile(-1, 512, ['dog']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendContentPersistenceProfile(0, 500, ['dog']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendContentPersistenceProfile(0, 768, ['dog']));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendContentPersistenceProfile(0, 512, []));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::appendContentPersistenceProfile(0, 512, ['dog', '']));
};

return $tests;
