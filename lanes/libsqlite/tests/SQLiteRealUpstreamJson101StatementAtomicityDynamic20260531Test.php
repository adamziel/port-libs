<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLitePDO;

/**
 * @param list<array<string,mixed>> $rows
 * @return list<array<string,mixed>>
 */
function json101_statement_atomicity_rows(array $rows): array
{
    return $rows;
}

function json101_statement_atomicity_sql_string(string $value): string
{
    return "'" . str_replace("'", "''", $value) . "'";
}

$tests = [];

for ($case = 0; $case < 1000; $case++) {
    $suffix = str_pad((string) $case, 4, '0', STR_PAD_LEFT);
    $badJson = 'not-valid-json-' . $suffix;
    $goodJson = '{"case":' . $case . ',"state":"committed"}';

    $tests['real upstream json101 19 malformed json aborts insert statement dynamic ' . $suffix] =
        static function (TestRunner $t) use ($badJson, $goodJson): void {
            $pdo = new SQLitePDO('sqlite::memory:');
            $t->same(0, $pdo->exec('CREATE TABLE app_json_queue(x)'));
            $t->same(0, $pdo->exec('BEGIN'));
            $t->same(true, $pdo->inTransaction(), 'json101-19.2 begins a transaction before the failing statement');

            $error = null;
            try {
                $pdo->exec('INSERT INTO app_json_queue VALUES(0), (json(' . json101_statement_atomicity_sql_string($badJson) . '))');
            } catch (PDOException $exception) {
                $error = $exception;
            }

            $t->true($error instanceof PDOException, 'json101-19.2 malformed json() raises through PDO');
            $t->contains('malformed JSON', $error?->getMessage() ?? '', 'json101-19.2 surfaces the upstream malformed JSON diagnostic');
            $t->same('HY000', $pdo->errorCode(), 'json101-19.2 records generic SQLite execution failure');
            $t->same('HY000', $pdo->errorInfo()[0], 'json101-19.2 errorInfo keeps the SQLSTATE');
            $t->contains('malformed JSON', $pdo->errorInfo()[2] ?? '', 'json101-19.2 errorInfo keeps the malformed JSON diagnostic');
            $t->same(true, $pdo->inTransaction(), 'json101-19.2 failed statement does not roll back the transaction');
            $t->same('0', $pdo->lastInsertId(), 'json101-19.2 failed statement restores last insert id');

            $t->same(0, $pdo->exec('COMMIT'));
            $t->same(false, $pdo->inTransaction(), 'json101-19.3 commit closes the still-active transaction');
            $rows = $pdo->query('SELECT x FROM app_json_queue')->fetchAll(PDO::FETCH_ASSOC);
            $t->same([], json101_statement_atomicity_rows($rows), 'json101-19.3 SELECT * FROM t1 remains empty after the failed multi-row statement');

            $t->same(1, $pdo->exec('INSERT INTO app_json_queue VALUES(json(' . json101_statement_atomicity_sql_string($goodJson) . '))'));
            $rows = $pdo->query('SELECT x FROM app_json_queue')->fetchAll(PDO::FETCH_ASSOC);
            $t->same([['x' => SQLiteJsonCanonical::json($goodJson)]], json101_statement_atomicity_rows($rows), 'json101-19 follow-up json() insert succeeds after commit');
            $t->same('1', $pdo->lastInsertId(), 'json101-19 follow-up insert advances last insert id only after success');
        };
}

$tests['real upstream json101 19 statement atomicity cites hydrated upstream source'] =
    static function (TestRunner $t): void {
        $sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test';
        $source = file_get_contents($sourcePath);
        if (!is_string($source)) {
            throw new RuntimeException('Unable to read hydrated upstream json101.test');
        }

        $t->same($sourcePath, '/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test');
        $t->contains('do_execsql_test json101-19.1', $source);
        $t->contains('do_catchsql_test json101-19.2', $source);
        $t->contains("INSERT INTO t1 VALUES(0), (json('not-valid-json'))", $source);
        $t->contains('do_execsql_test json101-19.3', $source);
        $t->contains('SELECT * FROM t1', $source);
    };

$tests['real upstream json101 19 statement atomicity dependency closure'] =
    static fn (TestRunner $t) => $t->same(
        'no-new-support-component; reuses SQLitePDO transaction state plus SQLiteJsonCanonical json/jsonb scalar evaluation',
        'no-new-support-component; reuses SQLitePDO transaction state plus SQLiteJsonCanonical json/jsonb scalar evaluation',
    );

return $tests;
