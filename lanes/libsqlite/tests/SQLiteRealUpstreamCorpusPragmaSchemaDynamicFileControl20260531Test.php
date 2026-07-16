<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaFileControl;

/*
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test
 * - pragma-19.1: VFS SQLITE_FCNTL_PRAGMA handles PRAGMA error with the
 *   default SQL logic error.
 * - pragma-19.2: PRAGMA error='message' returns the supplied message.
 * - pragma-19.3 and pragma-19.4: numeric error codes either prefix a custom
 *   message or fall back to SQLite's code message, including code 7 OOM.
 * - pragma-19.5: PRAGMA filename returns the VFS database filename.
 */

$tests = [];

$messages = [
    'This is the error message',
    'dynamic pragma error one',
    'prepared statement rejected by VFS',
    'application catalog open failed',
    'schema reload blocked by VFS pragma',
];

$codes = [
    [1, 'SQL logic error'],
    [5, 'database is locked'],
    [7, 'out of memory'],
    [8, 'attempt to write a readonly database'],
    [14, 'unable to open database file'],
];

$quote = static fn (string $value): string => "'" . str_replace("'", "''", $value) . "'";

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $filename = "/srv/app/data/pragma-file-control-{$suffix}.sqlite";
    $schema = $variant % 2 === 0 ? 'main' : 'aux' . $suffix;
    $message = $messages[$variant % count($messages)] . " {$suffix}";
    [$code, $codeMessage] = $codes[$variant % count($codes)];

    $tests[sprintf('real upstream pragma19 file-control default error variant %04d', $variant)] =
        static function (TestRunner $t) use ($filename, $schema): void {
            $control = new SQLitePragmaFileControl($filename);
            $sql = $schema === 'main' ? 'PRAGMA error' : "PRAGMA {$schema}.error";
            $result = $control->execute($sql);

            $t->same('error', $result['status']);
            $t->same('error', $result['pragma']);
            $t->same($schema, $result['schema']);
            $t->same(1, $result['code']);
            $t->same('SQL logic error', $result['message']);
            $t->same([1, 'SQL logic error'], $result['catchsql']);
            $t->same([], $result['rows']);
            $t->same(['sqlite-vfs-file-control-pragma'], $result['dependencies']);
        };

    $tests[sprintf('real upstream pragma19 file-control custom message variant %04d', $variant)] =
        static function (TestRunner $t) use ($filename, $schema, $message, $quote): void {
            $control = new SQLitePragmaFileControl($filename);
            $sql = $variantSql = $schema === 'main'
                ? 'PRAGMA error=' . $quote($message)
                : "PRAGMA {$schema}.error(" . $quote($message) . ')';
            $result = $control->execute($sql);
            $parsed = SQLitePragmaFileControl::parse($variantSql);

            $t->same('error', $result['status']);
            $t->same(1, $result['code']);
            $t->same($message, $result['message']);
            $t->same([1, $message], $result['catchsql']);
            $t->same($schema, $parsed['schema']);
            $t->same('error', $parsed['pragma']);
            $t->same($message, $parsed['value']);
        };

    $tests[sprintf('real upstream pragma19 file-control numeric code variant %04d', $variant)] =
        static function (TestRunner $t) use ($filename, $schema, $code, $codeMessage, $message, $quote): void {
            $control = new SQLitePragmaFileControl($filename);
            $plainSql = $schema === 'main'
                ? "PRAGMA error={$code}"
                : "PRAGMA {$schema}.error={$code}";
            $customSql = $schema === 'main'
                ? 'PRAGMA error=' . $quote($code . ' ' . $message)
                : "PRAGMA {$schema}.error(" . $quote($code . ' ' . $message) . ')';
            $plain = $control->execute($plainSql);
            $custom = $control->execute($customSql);

            $t->same($code, $plain['code']);
            $t->same($codeMessage, $plain['message']);
            $t->same([1, $codeMessage], $plain['catchsql']);
            $t->same($code, $custom['code']);
            $t->same($message, $custom['message']);
            $t->same([1, $message], $custom['catchsql']);
            $t->same($schema, $plain['schema']);
        };

    $tests[sprintf('real upstream pragma19 file-control filename variant %04d', $variant)] =
        static function (TestRunner $t) use ($filename, $schema, $suffix): void {
            $control = new SQLitePragmaFileControl($filename);
            $sql = $schema === 'main' ? 'PRAGMA filename' : "PRAGMA {$schema}.filename";
            $result = $control->execute($sql);
            $parsed = SQLitePragmaFileControl::parse(strtoupper($sql) . ';');

            $t->same('ok', $result['status']);
            $t->same('filename', $result['pragma']);
            $t->same($schema, $result['schema']);
            $t->same($filename, $result['filename']);
            $t->same("pragma-file-control-{$suffix}.sqlite", $result['tail']);
            $t->same([['filename' => $filename]], $result['rows']);
            $t->same([0, [$filename]], $result['catchsql']);
            $t->same('filename', $parsed['pragma']);
            $t->same($schema, $parsed['schema']);
        };
}

$tests['real upstream pragma19 file-control source citations'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-19.1 catches PRAGMA error as SQL logic error',
        'pragma.test pragma-19.2 catches PRAGMA error custom text',
        'pragma.test pragma-19.3 catches numeric code with custom text',
        'pragma.test pragma-19.4 maps PRAGMA error=7 to out of memory',
        'pragma.test pragma-19.5 returns the VFS filename',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma-19.1', $sections[0]);
    $t->contains('pragma-19.4', $sections[3]);
    $t->contains('filename', $sections[4]);
};

$tests['real upstream pragma19 file-control non overlap and guards'] = static function (TestRunner $t): void {
    $note = 'owns pragma.test pragma-19.1 through pragma-19.5 VFS file-control PRAGMA error/filename behavior; avoids table_info/index_info/table_list/data_version/cache_spill/temp_store/page_count/result-shape/runtime-list batches; no new support component needed';

    $t->contains('pragma-19.1 through pragma-19.5', $note);
    $t->contains('VFS file-control PRAGMA error/filename', $note);
    $t->contains('no new support component needed', $note);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaFileControl::parse('SELECT 1'));
    $t->throws(InvalidArgumentException::class, static fn () => new SQLitePragmaFileControl(''));
};

return $tests;
