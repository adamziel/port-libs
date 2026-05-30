<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$numberedMethodNames = static function (): array {
    $reflection = new ReflectionClass(SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::class);

    return array_values(array_filter(
        array_map(static fn (ReflectionMethod $method): string => $method->getName(), $reflection->getMethods(ReflectionMethod::IS_PUBLIC)),
        static fn (string $method): bool => preg_match('/^variantNext(?:286|290|292|293|294|295|296|297|298)$/', $method) === 1,
    ));
};

$tests['pager master reader cache exposes virtual-table schema fence without numbered method'] = static function (TestRunner $t): void {
    $t->same(true, method_exists(SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::class, 'currentSourceReaderCacheVirtualTableSchemaFence'));
};

$tests['pager master reader cache exposes authorizer schema fence without numbered method'] = static function (TestRunner $t): void {
    $t->same(true, method_exists(SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::class, 'currentSourceReaderCacheAuthorizerSchemaFence'));
};

$tests['pager master reader cache exposes spill epoch fence without numbered method'] = static function (TestRunner $t): void {
    $t->same(true, method_exists(SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::class, 'currentSourceReaderCacheSpillEpochFence'));
};

$tests['pager master reader cache exposes transaction runtime fences without numbered methods'] = static function (TestRunner $t): void {
    $t->same(true, method_exists(SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::class, 'currentSourceReaderCacheCommitPhaseFence'));
    $t->same(true, method_exists(SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::class, 'currentSourceReaderCacheBusyHandlerFence'));
    $t->same(true, method_exists(SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::class, 'currentSourceReaderCacheSavepointStackFence'));
    $t->same(true, method_exists(SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::class, 'currentSourceReaderCacheStatementJournalFence'));
    $t->same(true, method_exists(SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::class, 'currentSourceReaderCacheTempPageFence'));
    $t->same(true, method_exists(SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::class, 'currentSourceReaderCacheDirtyListFence'));
};

$tests['pager master reader cache omits consolidated numbered public methods'] = static function (TestRunner $t) use ($numberedMethodNames): void {
    $t->same([], $numberedMethodNames());
};

return $tests;
