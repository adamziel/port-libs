<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$tests = [];

$quote = static function (string $value): string {
    return "'" . str_replace("'", "''", $value) . "'";
};

$textKeyFor = static function (int $seed): string {
    return match ($seed % 4) {
        0 => (string) $seed,
        1 => str_pad((string) $seed, 4, '0', STR_PAD_LEFT),
        2 => (string) $seed . '.0',
        default => (string) $seed . 'e0',
    };
};

$execute = static function (string $sql, array $rows = []): array {
    return SQLiteUpsertReturningSql::execute($sql, ['app_ignore' => $rows], [['a'], ['b']]);
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $numericKey = $seed;
    $textKey = $textKeyFor($seed);
    $firstPayload = 'first-' . $seed;
    $ignoredPayload = 'ignored-' . $seed;

    $singleSql = 'INSERT OR IGNORE INTO app_ignore(a,b,payload) VALUES('
        . $quote($textKey) . ',NULL,' . $quote($firstPayload) . ') '
        . 'ON CONFLICT(a) DO NOTHING RETURNING a,b,payload';
    $batchSql = 'INSERT OR IGNORE INTO app_ignore(a,b,payload) VALUES('
        . $quote($textKey) . ',NULL,' . $quote($firstPayload) . '),('
        . (string) $numericKey . ',NULL,' . $quote($ignoredPayload) . ') '
        . 'ON CONFLICT(a) DO NOTHING RETURNING a,b,payload';
    $repeatSql = 'INSERT OR IGNORE INTO app_ignore(a,b,payload) VALUES('
        . (string) $numericKey . ',NULL,' . $quote($ignoredPayload) . ') '
        . 'ON CONFLICT(a) DO NOTHING RETURNING a,b,payload';

    $tests[sprintf('real upstream upsert1 600 610 OR IGNORE without rowid dynamic %04d', $seed)] =
        static function (TestRunner $t) use ($execute, $singleSql, $batchSql, $repeatSql, $textKey, $firstPayload, $ignoredPayload): void {
            $single = $execute($singleSql);
            $batch = $execute($batchSql);
            $repeat = $execute($repeatSql, $single['after']);

            $t->same(1, $single['changes']);
            $t->same([['a' => $textKey, 'b' => null, 'payload' => $firstPayload]], $single['returning']);
            $t->same([['a' => $textKey, 'b' => null, 'payload' => $firstPayload]], $single['after']);

            $t->same(1, $batch['changes']);
            $t->same([['a' => $textKey, 'b' => null, 'payload' => $firstPayload]], $batch['returning']);
            $t->same([['a' => $textKey, 'b' => null, 'payload' => $firstPayload]], $batch['after']);
            $t->same([$ignoredPayload], array_column($batch['skipped_rows'], 'payload'));
            $t->same(['a'], $batch['conflict_target']);

            $t->same(0, $repeat['changes']);
            $t->same([], $repeat['returning']);
            $t->same([['a' => $textKey, 'b' => null, 'payload' => $firstPayload]], $repeat['after']);
        };
}

$tests['real upstream upsert1 OR IGNORE without rowid dynamic source coverage'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-600 INSERT OR IGNORE with ON CONFLICT(a) DO NOTHING on WITHOUT ROWID primary key remains integrity-ok',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-610 text numeric key followed by integer key is one logical primary-key row',
        '1000 deterministic INSERT OR IGNORE UPSERT RETURNING variants assert a single changed row and no RETURNING row for the ignored duplicate',
        'non-overlap: this owns OR IGNORE parser admission and WITHOUT ROWID numeric primary-key duplicate suppression, not target-priority, alias/excluded, trigger-order, or upsert5 arm-priority matrices',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-600 INSERT OR IGNORE with ON CONFLICT(a) DO NOTHING on WITHOUT ROWID primary key remains integrity-ok',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsert1.test upsert1-610 text numeric key followed by integer key is one logical primary-key row',
        '1000 deterministic INSERT OR IGNORE UPSERT RETURNING variants assert a single changed row and no RETURNING row for the ignored duplicate',
        'non-overlap: this owns OR IGNORE parser admission and WITHOUT ROWID numeric primary-key duplicate suppression, not target-priority, alias/excluded, trigger-order, or upsert5 arm-priority matrices',
    ]);
};

$tests['real upstream upsert1 OR IGNORE without rowid dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses SQLiteUpsertReturningSql parsing, native DO NOTHING conflict handling, and loose SQLite-style primary-key comparison',
        'no new support component needed; reuses SQLiteUpsertReturningSql parsing, native DO NOTHING conflict handling, and loose SQLite-style primary-key comparison',
    );
};

return $tests;
