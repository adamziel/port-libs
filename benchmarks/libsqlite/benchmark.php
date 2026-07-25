#!/usr/bin/env php
<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePDO;

require dirname(__DIR__, 2) . '/tools/bootstrap.php';

const BENCHMARK_SCHEMA_VERSION = 1;
const ENGINE_PHP = 'php_userland';
const ENGINE_NATIVE = 'native_pdo';

/**
 * @return array<string, mixed>
 */
function parseOptions(array $argv): array
{
    $options = [
        'quick' => false,
        'samples' => 15,
        'target_ms' => 200.0,
        'warmup' => 3,
        'cases' => [],
        'list' => false,
        'open' => false,
        'help' => false,
        'output_dir' => dirname(__DIR__) . '/libsqlite/results',
    ];

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--quick') {
            $options['quick'] = true;
            continue;
        }
        if ($argument === '--list') {
            $options['list'] = true;
            continue;
        }
        if ($argument === '--open') {
            $options['open'] = true;
            continue;
        }
        if ($argument === '--help' || $argument === '-h') {
            $options['help'] = true;
            continue;
        }
        if (preg_match('/^--samples=(\d+)$/', $argument, $match) === 1) {
            $options['samples'] = (int) $match[1];
            continue;
        }
        if (preg_match('/^--target-ms=(\d+(?:\.\d+)?)$/', $argument, $match) === 1) {
            $options['target_ms'] = (float) $match[1];
            continue;
        }
        if (preg_match('/^--warmup=(\d+)$/', $argument, $match) === 1) {
            $options['warmup'] = (int) $match[1];
            continue;
        }
        if (preg_match('/^--case=([a-z0-9-]+)$/', $argument, $match) === 1) {
            $options['cases'][] = $match[1];
            continue;
        }
        if (preg_match('/^--output-dir=(.+)$/', $argument, $match) === 1) {
            $path = $match[1];
            if (!str_starts_with($path, DIRECTORY_SEPARATOR)) {
                $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . $path;
            }
            $options['output_dir'] = $path;
            continue;
        }

        throw new InvalidArgumentException("Unknown benchmark option: {$argument}");
    }

    if ($options['quick']) {
        $options['samples'] = 5;
        $options['target_ms'] = 30.0;
        $options['warmup'] = 1;
    }
    if ($options['samples'] < 3 || $options['samples'] > 51) {
        throw new InvalidArgumentException('--samples must be between 3 and 51');
    }
    if ($options['target_ms'] < 5 || $options['target_ms'] > 5000) {
        throw new InvalidArgumentException('--target-ms must be between 5 and 5000');
    }
    if ($options['warmup'] < 0 || $options['warmup'] > 10) {
        throw new InvalidArgumentException('--warmup must be between 0 and 10');
    }

    return $options;
}

function helpText(): string
{
    return <<<'TEXT'
PHP SQLite implementation benchmark

Usage:
  php -d opcache.enable_cli=0 benchmarks/libsqlite/benchmark.php [options]

Options:
  --quick                 5 samples, 30 ms target (smoke test)
  --samples=N             measured samples per engine (default: 15)
  --target-ms=N           target duration per adaptive batch (default: 200)
  --warmup=N              warmup batches per engine (default: 3)
  --case=ID               run one case; may be repeated
  --list                   list case IDs
  --output-dir=PATH        artifact directory
  --open                   open the generated HTML report on macOS
  --help                   show this help

TEXT;
}

/**
 * @return array<string, array<string, mixed>>
 */
function engines(): array
{
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    return [
        ENGINE_PHP => [
            'label' => 'PHP implementation',
            'short_label' => 'PHP',
            'class' => SQLitePDO::class,
            'factory' => static fn (string $dsn = 'sqlite::memory:'): PDO => new SQLitePDO(
                $dsn,
                null,
                null,
                $options,
            ),
        ],
        ENGINE_NATIVE => [
            'label' => 'Native PDO SQLite',
            'short_label' => 'Native',
            'class' => PDO::class . ' (pdo_sqlite)',
            'factory' => static fn (string $dsn = 'sqlite::memory:'): PDO => new PDO(
                $dsn,
                null,
                null,
                $options,
            ),
        ],
    ];
}

function seedRecords(PDO $database, int $rows, int $payloadBytes = 48): void
{
    $database->exec(
        'CREATE TABLE records ('
        . 'id INTEGER PRIMARY KEY, '
        . 'group_id INTEGER, '
        . 'status TEXT, '
        . 'score INTEGER, '
        . 'name TEXT, '
        . 'payload TEXT, '
        . 'json_doc TEXT'
        . ')',
    );
    $insert = $database->prepare(
        'INSERT INTO records '
        . '(id, group_id, status, score, name, payload, json_doc) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
    );

    $database->beginTransaction();
    for ($id = 1; $id <= $rows; $id++) {
        $rank = ($id * 7919) % max(1, $rows);
        $prefix = sprintf('%06d:%06d:', $rank, $id);
        $fill = chr(97 + ($id % 26));
        $payload = str_pad($prefix, $payloadBytes, $fill);
        $json = json_encode(
            ['x' => $id, 'flag' => $id % 2, 'tag' => 'g' . (($id % 10) + 1)],
            JSON_THROW_ON_ERROR,
        );
        $insert->execute([
            $id,
            ($id % 10) + 1,
            's' . ($id % 4),
            ($id * 17) % 1009,
            sprintf('row-%06d', $id),
            $payload,
            $json,
        ]);
    }
    $database->commit();
}

/**
 * @return list<array<int, mixed>>
 */
function executeRows(PDOStatement $statement, array $parameters = []): array
{
    $statement->execute($parameters);

    return $statement->fetchAll(PDO::FETCH_NUM);
}

function rowsChecksum(array $rows): int
{
    $checksum = count($rows) * 17;
    if ($rows !== []) {
        foreach ($rows[0] as $value) {
            $checksum = (($checksum * 33) ^ scalarChecksum($value)) & 0x7fffffff;
        }
        foreach ($rows[count($rows) - 1] as $value) {
            $checksum = (($checksum * 33) ^ scalarChecksum($value)) & 0x7fffffff;
        }
    }

    return $checksum;
}

function scalarChecksum(mixed $value): int
{
    if ($value === null) {
        return 3;
    }
    if (is_bool($value)) {
        return $value ? 5 : 7;
    }
    if (is_int($value)) {
        return $value & 0x7fffffff;
    }
    if (is_float($value)) {
        return (int) round($value * 1000);
    }

    return strlen((string) $value) * 31 + (crc32((string) $value) & 0x7fffffff);
}

function canonicalDigest(mixed $value): string
{
    $normalize = static function (mixed $item) use (&$normalize): mixed {
        if (is_float($item)) {
            return ['__float__', sprintf('%.12g', $item)];
        }
        if (is_array($item)) {
            $normalized = [];
            foreach ($item as $key => $child) {
                $normalized[$key] = $normalize($child);
            }

            return $normalized;
        }
        if (is_object($item)) {
            return $normalize(get_object_vars($item));
        }

        return $item;
    };

    return hash(
        'sha256',
        json_encode($normalize($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
    );
}

/**
 * @return list<array<string, mixed>>
 */
function scenarios(): array
{
    $preparedQuerySetup = static function (
        callable $factory,
        int $rows,
        string $sql,
        int $payloadBytes = 48,
    ): array {
        $database = $factory();
        seedRecords($database, $rows, $payloadBytes);

        return ['database' => $database, 'statement' => $database->prepare($sql)];
    };

    return [
        [
            'id' => 'simple-connect-schema',
            'category' => 'Simple',
            'name' => 'Connect + create schema',
            'description' => 'Open an in-memory PDO connection and create one small table.',
            'dataset' => 'Empty in-memory database; one 3-column table.',
            'timed_scope' => 'Connection construction, PDO attributes, and CREATE TABLE.',
            'setup_scope' => 'No fixture setup is excluded.',
            'unit' => 'session',
            'sql' => ['CREATE TABLE probe (id INTEGER PRIMARY KEY, label TEXT, amount INTEGER)'],
            'setup' => static fn (callable $factory): array => ['factory' => $factory],
            'verify' => static function (array &$context): array {
                $database = ($context['factory'])();
                $database->exec('CREATE TABLE probe (id INTEGER PRIMARY KEY, label TEXT, amount INTEGER)');
                $rows = $database->query('SELECT count(*) FROM probe')->fetchAll(PDO::FETCH_NUM);

                return $rows;
            },
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $database = ($context['factory'])();
                    $changes = $database->exec(
                        'CREATE TABLE probe (id INTEGER PRIMARY KEY, label TEXT, amount INTEGER)',
                    );
                    $checksum += $changes === 0 ? 1 : 0;
                    unset($database);
                }

                return $checksum;
            },
        ],
        [
            'id' => 'simple-scalar-query',
            'category' => 'Simple',
            'name' => 'Scalar query() path',
            'description' => 'Parse, validate, execute, and fetch a constant scalar query.',
            'dataset' => 'One warm in-memory connection; no tables.',
            'timed_scope' => 'PDO::query() plus fetchColumn(); prepare is intentionally included.',
            'setup_scope' => 'Connection construction and class loading are excluded.',
            'unit' => 'query',
            'sql' => ['SELECT 7 * 6 AS answer'],
            'setup' => static fn (callable $factory): array => ['database' => $factory()],
            'verify' => static fn (array &$context): array => [
                $context['database']->query('SELECT 7 * 6 AS answer')->fetchColumn(),
            ],
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $checksum += (int) $context['database']
                        ->query('SELECT 7 * 6 AS answer')
                        ->fetchColumn();
                }

                return $checksum;
            },
        ],
        [
            'id' => 'simple-prepared-lookup-100',
            'category' => 'Simple',
            'name' => 'Prepared lookup · 100 rows',
            'description' => 'Execute and fully fetch a reusable positional statement.',
            'dataset' => '100 deterministic rows; INTEGER PRIMARY KEY, no explicit indexes.',
            'timed_scope' => 'Statement execute, row materialization, and FETCH_NUM consumption.',
            'setup_scope' => 'Connection, fixture insertion, and initial prepare are excluded.',
            'unit' => 'lookup',
            'sql' => ['SELECT name, score FROM records WHERE id = ?'],
            'parameters' => '[73]',
            'setup' => static fn (callable $factory): array => $preparedQuerySetup(
                $factory,
                100,
                'SELECT name, score FROM records WHERE id = ?',
            ),
            'verify' => static function (array &$context): array {
                $rows = [];
                foreach ([1, 2, 37, 73, 100] as $id) {
                    $rows[$id] = executeRows($context['statement'], [$id]);
                }

                return $rows;
            },
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $rows = executeRows($context['statement'], [73]);
                    $checksum += rowsChecksum($rows);
                }

                return $checksum;
            },
        ],
        [
            'id' => 'simple-prepared-update-100',
            'category' => 'Simple',
            'name' => 'Prepared update · 100 rows',
            'description' => 'Update one row selected by its integer primary key.',
            'dataset' => '100 deterministic rows; the same target row is safely toggled.',
            'timed_scope' => 'Prepared execute and rowCount().',
            'setup_scope' => 'Connection, fixture insertion, and initial prepare are excluded.',
            'unit' => 'update',
            'sql' => ['UPDATE records SET score = ? WHERE id = ?'],
            'parameters' => 'score alternates 700 ↔ 701; id is always 73',
            'setup' => static function (callable $factory): array {
                $database = $factory();
                seedRecords($database, 100);

                return [
                    'database' => $database,
                    'statement' => $database->prepare('UPDATE records SET score = ? WHERE id = ?'),
                    'next_score' => 700,
                ];
            },
            'verify' => static function (array &$context): array {
                $before = $context['database']
                    ->query('SELECT score FROM records WHERE id = 73')
                    ->fetchColumn();
                $context['statement']->execute([701, 73]);
                $changes = $context['statement']->rowCount();
                $after = $context['database']
                    ->query('SELECT score FROM records WHERE id = 73')
                    ->fetchColumn();
                $context['next_score'] = 700;

                return [$before, $changes, $after];
            },
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $score = $context['next_score'];
                    $context['statement']->execute([$score, 73]);
                    $context['next_score'] = $score === 700 ? 701 : 700;
                    $checksum += $context['statement']->rowCount();
                }

                return $checksum;
            },
        ],
        [
            'id' => 'complex-filter-sort-limit',
            'category' => 'Complex',
            'name' => 'Filter + sort + limit',
            'description' => 'Filter a 1,000-row table, order on two keys, and materialize the top 25.',
            'dataset' => '1,000 deterministic rows across 10 groups and 4 statuses.',
            'timed_scope' => 'Prepared execute, scan/filter, sort, limit, and full result fetch.',
            'setup_scope' => 'Connection, fixture insertion, and initial prepare are excluded.',
            'unit' => 'query',
            'sql' => [
                'SELECT id, name, score FROM records '
                . 'WHERE group_id = ? AND score >= ? '
                . 'ORDER BY score DESC, id ASC LIMIT 25',
            ],
            'parameters' => '[4, 100]',
            'setup' => static fn (callable $factory): array => $preparedQuerySetup(
                $factory,
                1000,
                'SELECT id, name, score FROM records '
                . 'WHERE group_id = ? AND score >= ? '
                . 'ORDER BY score DESC, id ASC LIMIT 25',
            ),
            'verify' => static function (array &$context): array {
                $rows = [];
                for ($group = 1; $group <= 10; $group++) {
                    $rows[$group] = executeRows($context['statement'], [$group, 100]);
                }

                return $rows;
            },
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $rows = executeRows($context['statement'], [4, 100]);
                    $checksum += rowsChecksum($rows);
                }

                return $checksum;
            },
        ],
        [
            'id' => 'complex-group-having',
            'category' => 'Complex',
            'name' => 'GROUP BY + HAVING',
            'description' => 'Compute count and sum aggregates, filter groups, then order the result.',
            'dataset' => '1,000 deterministic rows in 10 equally populated groups.',
            'timed_scope' => 'Prepared execute, grouping, aggregate evaluation, ordering, and fetch.',
            'setup_scope' => 'Connection, fixture insertion, and initial prepare are excluded.',
            'unit' => 'query',
            'sql' => [
                'SELECT group_id, count(*) AS n, sum(score) AS total '
                . 'FROM records GROUP BY group_id '
                . 'HAVING count(*) >= 50 ORDER BY total DESC, group_id',
            ],
            'setup' => static fn (callable $factory): array => $preparedQuerySetup(
                $factory,
                1000,
                'SELECT group_id, count(*) AS n, sum(score) AS total '
                . 'FROM records GROUP BY group_id '
                . 'HAVING count(*) >= 50 ORDER BY total DESC, group_id',
            ),
            'verify' => static fn (array &$context): array => executeRows($context['statement']),
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $checksum += rowsChecksum(executeRows($context['statement']));
                }

                return $checksum;
            },
        ],
        [
            'id' => 'complex-window-rank',
            'category' => 'Complex',
            'name' => 'Partitioned window rank',
            'description' => 'Rank rows inside groups and order the materialized window output.',
            'dataset' => '750 deterministic rows across 10 window partitions.',
            'timed_scope' => 'Prepared execute, window evaluation, two ordering stages, and fetch of 100 rows.',
            'setup_scope' => 'Connection, fixture insertion, and initial prepare are excluded.',
            'unit' => 'query',
            'sql' => [
                'SELECT id, group_id, score, '
                . 'row_number() OVER (PARTITION BY group_id ORDER BY score DESC, id ASC) AS rn '
                . 'FROM records ORDER BY group_id ASC, rn ASC LIMIT 100',
            ],
            'setup' => static fn (callable $factory): array => $preparedQuerySetup(
                $factory,
                750,
                'SELECT id, group_id, score, '
                . 'row_number() OVER (PARTITION BY group_id ORDER BY score DESC, id ASC) AS rn '
                . 'FROM records ORDER BY group_id ASC, rn ASC LIMIT 100',
            ),
            'verify' => static fn (array &$context): array => executeRows($context['statement']),
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $checksum += rowsChecksum(executeRows($context['statement']));
                }

                return $checksum;
            },
        ],
        [
            'id' => 'complex-json-filter',
            'category' => 'Complex',
            'name' => 'JSON extract + filter',
            'description' => 'Extract two JSON paths per candidate, filter, order, and materialize 100 rows.',
            'dataset' => '1,000 rows with deterministic compact JSON documents.',
            'timed_scope' => 'Prepared execute, JSON parsing/path evaluation, filter, order, and fetch.',
            'setup_scope' => 'Connection, fixture insertion, and initial prepare are excluded.',
            'unit' => 'query',
            'sql' => [
                "SELECT id, json_extract(json_doc, '$.x') AS x "
                . "FROM records WHERE json_extract(json_doc, '$.flag') = 1 "
                . 'ORDER BY id LIMIT 100',
            ],
            'setup' => static fn (callable $factory): array => $preparedQuerySetup(
                $factory,
                1000,
                "SELECT id, json_extract(json_doc, '$.x') AS x "
                . "FROM records WHERE json_extract(json_doc, '$.flag') = 1 "
                . 'ORDER BY id LIMIT 100',
            ),
            'verify' => static fn (array &$context): array => executeRows($context['statement']),
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $checksum += rowsChecksum(executeRows($context['statement']));
                }

                return $checksum;
            },
        ],
        [
            'id' => 'complex-transaction-50',
            'category' => 'Complex',
            'name' => '50-row transaction rollback',
            'description' => 'Insert 50 explicit-ID rows through a prepared statement and roll the batch back.',
            'dataset' => 'Empty events table restored after every transaction.',
            'timed_scope' => 'BEGIN, 50 prepared executes, and ROLLBACK.',
            'setup_scope' => 'Connection, schema creation, and initial prepare are excluded.',
            'unit' => 'transaction',
            'sql' => [
                'BEGIN',
                'INSERT INTO events (id, label, amount) VALUES (?, ?, ?)',
                'ROLLBACK',
            ],
            'parameters' => 'IDs 1…50; label event-{id}; amount id×3',
            'setup' => static function (callable $factory): array {
                $database = $factory();
                $database->exec(
                    'CREATE TABLE events (id INTEGER PRIMARY KEY, label TEXT, amount INTEGER)',
                );

                return [
                    'database' => $database,
                    'statement' => $database->prepare(
                        'INSERT INTO events (id, label, amount) VALUES (?, ?, ?)',
                    ),
                ];
            },
            'verify' => static function (array &$context): array {
                $context['database']->beginTransaction();
                for ($id = 1; $id <= 50; $id++) {
                    $context['statement']->execute([$id, 'event-' . $id, $id * 3]);
                }
                $context['database']->rollBack();

                return [
                    50,
                    (int) $context['database']->query('SELECT count(*) FROM events')->fetchColumn(),
                ];
            },
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $context['database']->beginTransaction();
                    for ($id = 1; $id <= 50; $id++) {
                        $context['statement']->execute([$id, 'event-' . $id, $id * 3]);
                        $checksum += $context['statement']->rowCount();
                    }
                    $context['database']->rollBack();
                }

                return $checksum;
            },
        ],
        [
            'id' => 'pathological-lookup-10000',
            'category' => 'Pathological',
            'name' => 'Primary-key lookup · 10k rows',
            'description' => 'Expose the algorithmic gap between a PHP row scan and native INTEGER PRIMARY KEY lookup.',
            'dataset' => '10,000 deterministic rows with an INTEGER PRIMARY KEY.',
            'timed_scope' => 'Prepared execute and full fetch of one matching row.',
            'setup_scope' => 'Connection, 10,000-row fixture, and initial prepare are excluded.',
            'unit' => 'lookup',
            'sql' => ['SELECT name, score FROM records WHERE id = ?'],
            'parameters' => '[9973]',
            'setup' => static fn (callable $factory): array => $preparedQuerySetup(
                $factory,
                10000,
                'SELECT name, score FROM records WHERE id = ?',
                24,
            ),
            'verify' => static function (array &$context): array {
                $rows = [];
                foreach ([1, 2, 5000, 9973, 10000] as $id) {
                    $rows[$id] = executeRows($context['statement'], [$id]);
                }

                return $rows;
            },
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $checksum += rowsChecksum(executeRows($context['statement'], [9973]));
                }

                return $checksum;
            },
        ],
        [
            'id' => 'pathological-recursive-cte-200',
            'category' => 'Pathological',
            'name' => 'Recursive CTE · depth 200',
            'description' => 'Generate and aggregate a 200-row recursive sequence inside each query.',
            'dataset' => 'No base tables; recursive rows exist only for the statement.',
            'timed_scope' => 'Prepared execute, recursive materialization, aggregation, and fetch.',
            'setup_scope' => 'Connection and initial prepare are excluded.',
            'unit' => 'query',
            'sql' => [
                'WITH RECURSIVE seq(n) AS ('
                . 'VALUES(1) UNION ALL SELECT n + 1 FROM seq WHERE n < 200'
                . ') SELECT sum(n) AS total FROM seq',
            ],
            'setup' => static function (callable $factory): array {
                $database = $factory();
                $sql = 'WITH RECURSIVE seq(n) AS ('
                    . 'VALUES(1) UNION ALL SELECT n + 1 FROM seq WHERE n < 200'
                    . ') SELECT sum(n) AS total FROM seq';

                return ['database' => $database, 'statement' => $database->prepare($sql)];
            },
            'verify' => static fn (array &$context): array => executeRows($context['statement']),
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $rows = executeRows($context['statement']);
                    $checksum += (int) ($rows[0][0] ?? 0);
                }

                return $checksum;
            },
        ],
        [
            'id' => 'pathological-full-sort-3000',
            'category' => 'Pathological',
            'name' => 'Full sort + materialize · 3k',
            'description' => 'Sort and fetch every wide row, stressing allocation and eager materialization.',
            'dataset' => '3,000 rows with 160-byte payloads and deterministic ordering keys.',
            'timed_scope' => 'Prepared execute, full sort, materialization, FETCH_NUM, and boundary checksum.',
            'setup_scope' => 'Connection, fixture insertion, and initial prepare are excluded.',
            'unit' => 'query',
            'sql' => ['SELECT id, payload FROM records ORDER BY payload DESC, id DESC'],
            'setup' => static fn (callable $factory): array => $preparedQuerySetup(
                $factory,
                3000,
                'SELECT id, payload FROM records ORDER BY payload DESC, id DESC',
                160,
            ),
            'verify' => static fn (array &$context): array => executeRows($context['statement']),
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $checksum += rowsChecksum(executeRows($context['statement']));
                }

                return $checksum;
            },
        ],
        [
            'id' => 'pathological-cross-join-150',
            'category' => 'Pathological',
            'name' => 'Cartesian product · 150²',
            'description' => 'Compute the 22,500-row logical cardinality with COUNT(*), then fetch the aggregate. '
                . 'The pure-PHP engine may multiply static source cardinalities instead of materializing every pair.',
            'dataset' => 'One 150-row table referenced twice; the logical Cartesian result contains 22,500 rows.',
            'timed_scope' => 'Prepared execute, COUNT(*) aggregate evaluation, and fetch.',
            'setup_scope' => 'Connection, fixture insertion, and initial prepare are excluded.',
            'unit' => 'query',
            'sql' => ['SELECT count(*) AS pairs FROM records AS a CROSS JOIN records AS b'],
            'setup' => static fn (callable $factory): array => $preparedQuerySetup(
                $factory,
                150,
                'SELECT count(*) AS pairs FROM records AS a CROSS JOIN records AS b',
                24,
            ),
            'verify' => static fn (array &$context): array => executeRows($context['statement']),
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $rows = executeRows($context['statement']);
                    $checksum += (int) ($rows[0][0] ?? 0);
                }

                return $checksum;
            },
        ],
        [
            'id' => 'pathological-auto-rowid-300',
            'category' => 'Pathological',
            'name' => 'Implicit rowid growth · 300',
            'description' => 'Insert 300 rows without IDs; the userland allocator scans prior rows for every new ID.',
            'dataset' => 'A fresh empty table is created inside every timed batch unit.',
            'timed_scope' => 'Connection, schema, prepare, BEGIN, 300 inserts, COMMIT, and lastInsertId.',
            'setup_scope' => 'Only process/class warmup is excluded; database construction is timed.',
            'unit' => 'batch',
            'sql' => [
                'CREATE TABLE auto_rows (id INTEGER PRIMARY KEY, payload TEXT)',
                'INSERT INTO auto_rows (payload) VALUES (?) × 300',
            ],
            'parameters' => 'payload-{id}; integer primary key intentionally omitted',
            'setup' => static fn (callable $factory): array => ['factory' => $factory],
            'verify' => static function (array &$context): array {
                $database = ($context['factory'])();
                $database->exec('CREATE TABLE auto_rows (id INTEGER PRIMARY KEY, payload TEXT)');
                $insert = $database->prepare('INSERT INTO auto_rows (payload) VALUES (?)');
                $database->beginTransaction();
                for ($id = 1; $id <= 300; $id++) {
                    $insert->execute(['payload-' . $id]);
                }
                $database->commit();

                return [
                    $database->lastInsertId(),
                    $database->query('SELECT count(*) FROM auto_rows')->fetchColumn(),
                ];
            },
            'run_batch' => static function (array &$context, int $iterations): int {
                $checksum = 0;
                for ($iteration = 0; $iteration < $iterations; $iteration++) {
                    $database = ($context['factory'])();
                    $database->exec(
                        'CREATE TABLE auto_rows (id INTEGER PRIMARY KEY, payload TEXT)',
                    );
                    $insert = $database->prepare(
                        'INSERT INTO auto_rows (payload) VALUES (?)',
                    );
                    $database->beginTransaction();
                    for ($id = 1; $id <= 300; $id++) {
                        $insert->execute(['payload-' . $id]);
                    }
                    $database->commit();
                    $checksum += (int) $database->lastInsertId();
                    unset($insert, $database);
                }

                return $checksum;
            },
        ],
    ];
}

/**
 * @param callable(array<string, mixed>&, int): int $runBatch
 */
function timedBatch(callable $runBatch, array &$context, int $iterations): array
{
    if (function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
    $start = hrtime(true);
    $checksum = $runBatch($context, $iterations);
    $elapsed = hrtime(true) - $start;
    if (!is_int($checksum)) {
        throw new RuntimeException('Benchmark batch must return an integer checksum');
    }
    if ($elapsed <= 0) {
        throw new RuntimeException('Benchmark clock returned a non-positive interval');
    }

    return ['elapsed_ns' => $elapsed, 'checksum' => $checksum];
}

/**
 * @param callable(array<string, mixed>&, int): int $runBatch
 */
function calibrateIterations(
    callable $runBatch,
    array &$context,
    int $targetNanoseconds,
): array {
    $iterations = 1;
    $history = [];
    for ($round = 0; $round < 16; $round++) {
        $measurement = timedBatch($runBatch, $context, $iterations);
        $elapsed = $measurement['elapsed_ns'];
        $history[] = [
            'iterations' => $iterations,
            'elapsed_ns' => $elapsed,
            'checksum' => $measurement['checksum'],
        ];

        if ($elapsed >= (int) ($targetNanoseconds * 0.8) || $iterations >= 1_000_000) {
            break;
        }

        $estimated = (int) ceil($iterations * ($targetNanoseconds / max(1, $elapsed)));
        $iterations = min(
            1_000_000,
            max($iterations + 1, min($iterations * 8, $estimated)),
        );
    }

    return ['iterations' => $iterations, 'history' => $history];
}

function median(array $values): float
{
    if ($values === []) {
        throw new InvalidArgumentException('Cannot compute median of an empty list');
    }
    sort($values, SORT_NUMERIC);
    $count = count($values);
    $middle = intdiv($count, 2);

    return ($count % 2) === 1
        ? (float) $values[$middle]
        : ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
}

function quantile(array $values, float $probability): float
{
    if ($values === []) {
        throw new InvalidArgumentException('Cannot compute quantile of an empty list');
    }
    sort($values, SORT_NUMERIC);
    $position = (count($values) - 1) * $probability;
    $lower = (int) floor($position);
    $upper = (int) ceil($position);
    if ($lower === $upper) {
        return (float) $values[$lower];
    }
    $weight = $position - $lower;

    return ((float) $values[$lower] * (1 - $weight))
        + ((float) $values[$upper] * $weight);
}

/**
 * @return array<string, float|int>
 */
function statistics(array $values): array
{
    $count = count($values);
    $mean = array_sum($values) / $count;
    $median = median($values);
    $sumSquares = 0.0;
    foreach ($values as $value) {
        $sumSquares += ($value - $mean) ** 2;
    }
    $standardDeviation = sqrt($sumSquares / $count);
    $deviations = array_map(
        static fn (float $value): float => abs($value - $median),
        $values,
    );
    $mad = median($deviations);

    return [
        'count' => $count,
        'min_ns' => min($values),
        'p10_ns' => quantile($values, 0.10),
        'median_ns' => $median,
        'mean_ns' => $mean,
        'p90_ns' => quantile($values, 0.90),
        'max_ns' => max($values),
        'mad_ns' => $mad,
        'relative_mad' => $median > 0 ? $mad / $median : 0.0,
        'standard_deviation_ns' => $standardDeviation,
        'coefficient_of_variation' => $mean > 0 ? $standardDeviation / $mean : 0.0,
        'operations_per_second' => 1_000_000_000 / $median,
    ];
}

/**
 * @return array{low: float, high: float}
 */
function bootstrapRatioInterval(
    array $phpSamples,
    array $nativeSamples,
    string $seedText,
    int $resamples = 2000,
): array {
    $ratios = [];
    $phpCount = count($phpSamples);
    $nativeCount = count($nativeSamples);
    if ($phpCount !== $nativeCount) {
        throw new InvalidArgumentException('Paired bootstrap needs equal sample counts');
    }
    $state = crc32($seedText) & 0xffffffff;
    $randomIndex = static function (int $count) use (&$state): int {
        $state = (int) (($state * 1664525 + 1013904223) & 0xffffffff);

        return $state % $count;
    };

    for ($sample = 0; $sample < $resamples; $sample++) {
        $phpResample = [];
        $nativeResample = [];
        for ($index = 0; $index < $phpCount; $index++) {
            $pairedIndex = $randomIndex($phpCount);
            $phpResample[] = $phpSamples[$pairedIndex];
            $nativeResample[] = $nativeSamples[$pairedIndex];
        }
        $ratios[] = median($phpResample) / median($nativeResample);
    }

    return [
        'low' => quantile($ratios, 0.025),
        'high' => quantile($ratios, 0.975),
    ];
}

/**
 * @return list<string>
 */
function measurementOrder(int $sampleIndex): array
{
    return match ($sampleIndex % 4) {
        0, 3 => [ENGINE_PHP, ENGINE_NATIVE],
        default => [ENGINE_NATIVE, ENGINE_PHP],
    };
}

/**
 * @return array<string, mixed>
 */
function runScenario(
    array $scenario,
    array $engines,
    int $samples,
    int $warmupBatches,
    int $targetNanoseconds,
): array {
    $contexts = [];
    $verification = [];
    $calibration = [];
    $sampleValues = [ENGINE_PHP => [], ENGINE_NATIVE => []];
    $batchChecksums = [ENGINE_PHP => [], ENGINE_NATIVE => []];
    $timedChecksumsPerUnit = [];

    try {
        foreach ($engines as $engineId => $engine) {
            $contexts[$engineId] = ($scenario['setup'])($engine['factory']);
            $value = ($scenario['verify'])($contexts[$engineId]);
            $verification[$engineId] = [
                'digest' => canonicalDigest($value),
                'preview' => verificationPreview($value),
            ];
        }

        if ($verification[ENGINE_PHP]['digest'] !== $verification[ENGINE_NATIVE]['digest']) {
            throw new RuntimeException(
                "Correctness verification failed for {$scenario['id']}: "
                . ENGINE_PHP . '=' . json_encode($verification[ENGINE_PHP]['preview'])
                . ', ' . ENGINE_NATIVE . '=' . json_encode($verification[ENGINE_NATIVE]['preview']),
            );
        }

        foreach ([ENGINE_PHP, ENGINE_NATIVE] as $engineId) {
            $calibration[$engineId] = calibrateIterations(
                $scenario['run_batch'],
                $contexts[$engineId],
                $targetNanoseconds,
            );
        }

        for ($warmup = 0; $warmup < $warmupBatches; $warmup++) {
            foreach (measurementOrder($warmup) as $engineId) {
                timedBatch(
                    $scenario['run_batch'],
                    $contexts[$engineId],
                    $calibration[$engineId]['iterations'],
                );
            }
        }

        for ($sample = 0; $sample < $samples; $sample++) {
            foreach (measurementOrder($sample) as $engineId) {
                $iterations = $calibration[$engineId]['iterations'];
                $measurement = timedBatch(
                    $scenario['run_batch'],
                    $contexts[$engineId],
                    $iterations,
                );
                $sampleValues[$engineId][] = $measurement['elapsed_ns'] / $iterations;
                $batchChecksums[$engineId][] = $measurement['checksum'];
            }
        }

        foreach ([ENGINE_PHP, ENGINE_NATIVE] as $engineId) {
            $iterations = $calibration[$engineId]['iterations'];
            $normalized = [];
            foreach ($batchChecksums[$engineId] as $checksum) {
                if (($checksum % $iterations) !== 0) {
                    throw new RuntimeException(
                        "Timed checksum is not linear per unit for {$scenario['id']} on {$engineId}",
                    );
                }
                $normalized[] = intdiv($checksum, $iterations);
            }
            $unique = array_values(array_unique($normalized, SORT_REGULAR));
            if (count($unique) !== 1) {
                throw new RuntimeException(
                    "Timed checksum changed between samples for {$scenario['id']} on {$engineId}",
                );
            }
            $timedChecksumsPerUnit[$engineId] = $unique[0];
        }
        if ($timedChecksumsPerUnit[ENGINE_PHP] !== $timedChecksumsPerUnit[ENGINE_NATIVE]) {
            throw new RuntimeException(
                "Timed checksum mismatch for {$scenario['id']}: "
                . ENGINE_PHP . '=' . $timedChecksumsPerUnit[ENGINE_PHP]
                . ', ' . ENGINE_NATIVE . '=' . $timedChecksumsPerUnit[ENGINE_NATIVE],
            );
        }
    } finally {
        foreach ($contexts as &$context) {
            if (isset($context['cleanup']) && is_callable($context['cleanup'])) {
                ($context['cleanup'])();
            }
        }
        unset($context);
    }

    $phpStatistics = statistics($sampleValues[ENGINE_PHP]);
    $nativeStatistics = statistics($sampleValues[ENGINE_NATIVE]);
    $ratio = $phpStatistics['median_ns'] / $nativeStatistics['median_ns'];
    $interval = bootstrapRatioInterval(
        $sampleValues[ENGINE_PHP],
        $sampleValues[ENGINE_NATIVE],
        $scenario['id'],
    );

    return [
        'id' => $scenario['id'],
        'category' => $scenario['category'],
        'name' => $scenario['name'],
        'description' => $scenario['description'],
        'dataset' => $scenario['dataset'],
        'timed_scope' => $scenario['timed_scope'],
        'setup_scope' => $scenario['setup_scope'],
        'unit' => $scenario['unit'],
        'sql' => $scenario['sql'],
        'parameters' => $scenario['parameters'] ?? 'None',
        'verification' => [
            'matched' => true,
            'digest' => $verification[ENGINE_PHP]['digest'],
            'preview' => $verification[ENGINE_PHP]['preview'],
            'timed_checksum_per_unit' => $timedChecksumsPerUnit[ENGINE_PHP],
        ],
        'results' => [
            ENGINE_PHP => [
                'iterations_per_sample' => $calibration[ENGINE_PHP]['iterations'],
                'calibration' => $calibration[ENGINE_PHP]['history'],
                'samples_ns_per_unit' => $sampleValues[ENGINE_PHP],
                'batch_checksums' => $batchChecksums[ENGINE_PHP],
                'statistics' => $phpStatistics,
            ],
            ENGINE_NATIVE => [
                'iterations_per_sample' => $calibration[ENGINE_NATIVE]['iterations'],
                'calibration' => $calibration[ENGINE_NATIVE]['history'],
                'samples_ns_per_unit' => $sampleValues[ENGINE_NATIVE],
                'batch_checksums' => $batchChecksums[ENGINE_NATIVE],
                'statistics' => $nativeStatistics,
            ],
        ],
        'comparison' => [
            'native_advantage' => $ratio,
            'bootstrap_95_percent_ci' => $interval,
            'faster_engine' => $ratio >= 1 ? ENGINE_NATIVE : ENGINE_PHP,
        ],
    ];
}

function verificationPreview(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (count($value) <= 4) {
        return $value;
    }
    $values = array_values($value);

    return [
        'count' => count($values),
        'first' => $values[0] ?? null,
        'last' => $values[count($values) - 1] ?? null,
    ];
}

function shellValue(array $command, string $workingDirectory): ?string
{
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = @proc_open($command, $descriptorSpec, $pipes, $workingDirectory);
    if (!is_resource($process)) {
        return null;
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        return null;
    }
    unset($stderr);

    return trim((string) $stdout);
}

function iniBoolean(string $name): bool
{
    $value = strtolower((string) ini_get($name));

    return !in_array($value, ['', '0', 'off', 'false', 'no'], true);
}

/**
 * @return array<string, mixed>
 */
function environmentMetadata(string $repositoryRoot): array
{
    $native = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $compileOptions = $native->query('PRAGMA compile_options')->fetchAll(PDO::FETCH_COLUMN);
    sort($compileOptions);
    $pdoSqliteVersion = (string) $native->query('SELECT sqlite_version()')->fetchColumn();
    $sourceId = $native->query('SELECT sqlite_source_id()')->fetchColumn();
    $versionParts = array_map('intval', explode('.', $pdoSqliteVersion));
    $versionNumber = (($versionParts[0] ?? 0) * 1_000_000)
        + (($versionParts[1] ?? 0) * 1_000)
        + ($versionParts[2] ?? 0);

    $status = shellValue(['git', 'status', '--porcelain'], $repositoryRoot) ?? '';
    $statusLines = $status === '' ? [] : preg_split('/\R/', $status);
    $libsqliteDirty = array_values(array_filter(
        $statusLines,
        static fn (string $line): bool => str_contains($line, 'lanes/libsqlite/'),
    ));

    $coreFiles = [
        'lanes/libsqlite/src/SQLitePDO.php',
        'lanes/libsqlite/src/SQLitePDOStatement.php',
        'lanes/libsqlite/src/SQLiteSelectSql.php',
        'lanes/libsqlite/src/SQLiteSelectQuery.php',
        'lanes/libsqlite/src/SQLiteSelectResult.php',
    ];
    $coreHashes = [];
    foreach ($coreFiles as $file) {
        $coreHashes[$file] = hash_file('sha256', $repositoryRoot . '/' . $file);
    }
    $sourceFiles = glob($repositoryRoot . '/lanes/libsqlite/src/*.php') ?: [];
    sort($sourceFiles);
    $aggregateHash = hash_init('sha256');
    foreach ($sourceFiles as $file) {
        hash_update($aggregateHash, basename($file) . "\0" . hash_file('sha256', $file) . "\n");
    }

    $memoryBytes = shellValue(['sysctl', '-n', 'hw.memsize'], $repositoryRoot);
    $logicalCpus = shellValue(['sysctl', '-n', 'hw.logicalcpu'], $repositoryRoot);
    $cpu = shellValue(['sysctl', '-n', 'machdep.cpu.brand_string'], $repositoryRoot)
        ?? shellValue(['sysctl', '-n', 'hw.model'], $repositoryRoot);
    $hypervisor = shellValue(['sysctl', '-n', 'kern.hv_vmm_present'], $repositoryRoot);
    $filesystemType = null;
    $diskInfo = shellValue(['diskutil', 'info', '/'], $repositoryRoot);
    if ($diskInfo !== null
        && preg_match('/^\s*File System Personality:\s*(.+)$/mi', $diskInfo, $match) === 1
    ) {
        $filesystemType = trim($match[1]);
    }

    return [
        'captured_at_utc' => gmdate('c'),
        'host' => [
            'operating_system' => php_uname('s') . ' ' . php_uname('r'),
            'macos_version' => shellValue(['sw_vers', '-productVersion'], $repositoryRoot),
            'architecture' => php_uname('m'),
            'cpu' => $cpu,
            'logical_cpus' => $logicalCpus === null ? null : (int) $logicalCpus,
            'memory_bytes' => $memoryBytes === null ? null : (int) $memoryBytes,
            'virtualized' => $hypervisor === null ? null : $hypervisor === '1',
            'load_average_at_start' => sys_getloadavg(),
            'filesystem_type' => $filesystemType,
            'disk_free_bytes' => disk_free_space($repositoryRoot),
        ],
        'php' => [
            'version' => PHP_VERSION,
            'binary' => PHP_BINARY,
            'sapi' => PHP_SAPI,
            'thread_safety' => PHP_ZTS,
            'debug_build' => PHP_DEBUG,
            'ini_file' => php_ini_loaded_file() ?: null,
            'memory_limit' => ini_get('memory_limit'),
            'opcache_cli_enabled' => iniBoolean('opcache.enable_cli'),
            'jit_mode' => ini_get('opcache.jit') ?: 'disabled',
            'jit_buffer_size' => ini_get('opcache.jit_buffer_size') ?: '0',
            'pdo_drivers' => PDO::getAvailableDrivers(),
            'extensions' => [
                'pdo_sqlite' => phpversion('pdo_sqlite') ?: true,
                'sqlite3' => phpversion('sqlite3') ?: true,
            ],
        ],
        'native_sqlite' => [
            'library_version' => $pdoSqliteVersion,
            'library_version_number' => $versionNumber,
            'source_id' => $sourceId,
            'compile_options' => $compileOptions,
            'binding' => 'PDO pdo_sqlite',
        ],
        'repository' => [
            'root' => $repositoryRoot,
            'branch' => shellValue(['git', 'branch', '--show-current'], $repositoryRoot),
            'commit' => shellValue(['git', 'rev-parse', 'HEAD'], $repositoryRoot),
            'dirty' => $statusLines !== [],
            'dirty_path_count' => count($statusLines),
            'libsqlite_dirty_entries' => $libsqliteDirty,
            'source_file_count' => count($sourceFiles),
            'libsqlite_source_sha256' => hash_final($aggregateHash),
            'core_file_sha256' => $coreHashes,
            'benchmark_harness_sha256' => hash_file('sha256', __FILE__),
            'benchmark_readme_sha256' => hash_file(
                'sha256',
                $repositoryRoot . '/benchmarks/libsqlite/README.md',
            ),
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function scenarioSpecification(array $scenario): array
{
    unset($scenario['setup'], $scenario['verify'], $scenario['run_batch']);

    return $scenario;
}

function geometricMean(array $values): float
{
    $logs = array_map(
        static fn (float $value): float => log(max(PHP_FLOAT_MIN, $value)),
        $values,
    );

    return exp(array_sum($logs) / count($logs));
}

function formatDuration(float $nanoseconds, bool $ascii = false): string
{
    if ($nanoseconds < 1_000) {
        return number_format($nanoseconds, $nanoseconds < 100 ? 1 : 0) . ' ns';
    }
    if ($nanoseconds < 1_000_000) {
        $suffix = $ascii ? ' us' : ' µs';

        return number_format($nanoseconds / 1_000, $nanoseconds < 10_000 ? 2 : 1) . $suffix;
    }
    if ($nanoseconds < 1_000_000_000) {
        return number_format($nanoseconds / 1_000_000, $nanoseconds < 10_000_000 ? 2 : 1) . ' ms';
    }

    return number_format($nanoseconds / 1_000_000_000, 2) . ' s';
}

function pluralUnit(string $unit): string
{
    return match ($unit) {
        'query' => 'queries',
        'batch' => 'batches',
        default => $unit . 's',
    };
}

function formatRate(float $rate, bool $ascii = false, ?string $unit = null): string
{
    unset($ascii);
    if ($rate >= 1_000_000) {
        $number = number_format($rate / 1_000_000, 2) . 'M';
    } elseif ($rate >= 1_000) {
        $number = number_format($rate / 1_000, 1) . 'k';
    } elseif ($rate >= 10) {
        $number = number_format($rate, 1);
    } else {
        $number = number_format($rate, 2);
    }
    $suffix = $unit === null ? 'units/s' : pluralUnit($unit) . '/s';

    return $number . ' ' . $suffix;
}

function formatRatio(float $ratio, bool $ascii = false): string
{
    $suffix = $ascii ? 'x' : '×';
    if ($ratio >= 1000) {
        return number_format($ratio, 0) . $suffix;
    }
    if ($ratio >= 100) {
        return number_format($ratio, 1) . $suffix;
    }
    if ($ratio >= 10) {
        return number_format($ratio, 2) . $suffix;
    }

    return number_format($ratio, 2) . $suffix;
}

function renderTextTable(array $cases): string
{
    $headers = ['Category', 'Case', 'PHP median', 'Native median', 'PHP rate', 'Native rate', 'Native advantage'];
    $widths = [12, 35, 13, 13, 20, 20, 17];
    $lines = [];
    $separator = '+-' . implode('-+-', array_map(
        static fn (int $width): string => str_repeat('-', $width),
        $widths,
    )) . '-+';
    $renderRow = static function (array $columns) use ($widths): string {
        $cells = [];
        foreach ($columns as $index => $column) {
            $text = str_replace(['·', '²', '×', 'µ'], ['-', '^2', 'x', 'u'], (string) $column);
            if (strlen($text) > $widths[$index]) {
                $text = substr($text, 0, $widths[$index] - 1) . '~';
            }
            $cells[] = str_pad($text, $widths[$index]);
        }

        return '| ' . implode(' | ', $cells) . ' |';
    };

    $lines[] = $separator;
    $lines[] = $renderRow($headers);
    $lines[] = $separator;
    foreach ($cases as $case) {
        $php = $case['results'][ENGINE_PHP]['statistics'];
        $native = $case['results'][ENGINE_NATIVE]['statistics'];
        $ratio = $case['comparison']['native_advantage'];
        $relative = $ratio >= 1
            ? formatRatio($ratio, true) . ' native'
            : formatRatio(1 / $ratio, true) . ' PHP';
        $lines[] = $renderRow([
            $case['category'],
            $case['name'],
            formatDuration($php['median_ns'], true),
            formatDuration($native['median_ns'], true),
            formatRate($php['operations_per_second'], true, $case['unit']),
            formatRate($native['operations_per_second'], true, $case['unit']),
            $relative,
        ]);
    }
    $lines[] = $separator;
    $lines[] = 'Latency is median time per named unit; lower is better. Rate is units/second; higher is better.';

    return implode(PHP_EOL, $lines) . PHP_EOL;
}

function h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function formatBytes(?int $bytes): string
{
    if ($bytes === null) {
        return 'Unavailable';
    }
    $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
    $value = (float) $bytes;
    $unit = 0;
    while ($value >= 1024 && $unit < count($units) - 1) {
        $value /= 1024;
        $unit++;
    }

    return number_format($value, $unit === 0 ? 0 : 1) . ' ' . $units[$unit];
}

function htmlDefinitionRows(array $rows): string
{
    $html = '';
    foreach ($rows as $label => $value) {
        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        } elseif (is_array($value)) {
            $value = implode(', ', array_map('strval', $value));
        } elseif ($value === null || $value === '') {
            $value = 'Unavailable';
        }
        $html .= '<div class="definition-row"><dt>' . h($label) . '</dt><dd>'
            . h($value) . '</dd></div>';
    }

    return $html;
}

function renderHtmlReport(array $payload, string $jsonFileName): string
{
    $cases = $payload['cases'];
    $summary = $payload['summary'];
    $environment = $payload['environment'];
    $config = $payload['benchmark'];
    $directionalRatios = array_map(
        static function (array $case): float {
            $ratio = $case['comparison']['native_advantage'];

            return max($ratio, 1 / $ratio);
        },
        $cases,
    );
    $maxDirectionalRatio = max($directionalRatios);
    $minimumRatioLabel = $summary['minimum_native_advantage'] >= 1
        ? formatRatio($summary['minimum_native_advantage']) . ' native'
        : formatRatio(1 / $summary['minimum_native_advantage']) . ' PHP';
    $maximumRatioLabel = $summary['maximum_native_advantage'] >= 1
        ? formatRatio($summary['maximum_native_advantage']) . ' native'
        : formatRatio(1 / $summary['maximum_native_advantage']) . ' PHP';
    $headlineRatioLabel = $summary['geometric_mean_native_advantage'] >= 1
        ? formatRatio($summary['geometric_mean_native_advantage']) . ' native'
        : formatRatio(1 / $summary['geometric_mean_native_advantage']) . ' PHP';
    $profileLabel = ucfirst($config['profile']) . ' profile';
    $profileBanner = $config['profile'] === 'full'
        ? ''
        : '<div class="profile-banner"><strong>' . h(ucfirst($config['profile']))
            . ' run—interpretation is provisional.</strong> This artifact validates the harness or a custom subset; '
            . 'it is not the default decision-grade 15-sample study.</div>';
    $classSummaries = '';
    foreach ($summary['by_category'] as $category => $categorySummary) {
        $categoryRatio = $categorySummary['geometric_mean_native_advantage'];
        $categoryLabel = $categoryRatio >= 1
            ? formatRatio($categoryRatio) . ' native'
            : formatRatio(1 / $categoryRatio) . ' PHP';
        $classSummaries .= '<div><span>' . h($category) . '</span><strong>'
            . h($categoryLabel) . '</strong><small>scenario-set geometric mean; '
            . h($categorySummary['native_wins']) . '/' . h($categorySummary['case_count'])
            . ' native wins</small></div>';
    }
    $resultRows = '';
    $gapRows = '';
    $detailSections = '';
    $rawSections = '';

    foreach ($cases as $case) {
        $php = $case['results'][ENGINE_PHP]['statistics'];
        $native = $case['results'][ENGINE_NATIVE]['statistics'];
        $ratio = $case['comparison']['native_advantage'];
        $ci = $case['comparison']['bootstrap_95_percent_ci'];
        $maxRate = max($php['operations_per_second'], $native['operations_per_second']);
        $phpWidth = ($php['operations_per_second'] / $maxRate) * 100;
        $nativeWidth = ($native['operations_per_second'] / $maxRate) * 100;
        $ratioLabel = $ratio >= 1
            ? formatRatio($ratio) . ' native'
            : formatRatio(1 / $ratio) . ' PHP';
        $ciLabel = $ratio >= 1
            ? formatRatio($ci['low']) . '–' . formatRatio($ci['high']) . ' native'
            : formatRatio(1 / $ci['high']) . '–' . formatRatio(1 / $ci['low']) . ' PHP';
        $stability = max($php['relative_mad'], $native['relative_mad']) * 100;
        $stabilityClass = $stability <= 3 ? 'low' : ($stability <= 8 ? 'medium' : 'high');
        $directionalRatio = max($ratio, 1 / $ratio);
        $gapWidth = max(
            3.0,
            (log($directionalRatio) / max(0.0001, log(max(1.01, $maxDirectionalRatio)))) * 100,
        );

        $resultRows .= '<tr>'
            . '<td><span class="category category-' . strtolower($case['category']) . '">'
            . h($case['category']) . '</span></td>'
            . '<th scope="row"><a href="#' . h($case['id']) . '">' . h($case['name']) . '</a>'
            . '<small>' . h($case['description']) . '</small></th>'
            . '<td class="engine-cell"><strong>' . h(formatDuration($php['median_ns'])) . '</strong>'
            . '<span>' . h(formatRate($php['operations_per_second'], false, $case['unit'])) . '</span>'
            . '<div class="rail" aria-hidden="true">'
            . '<i class="bar php-bar" style="width:' . number_format($phpWidth, 2, '.', '') . '%"></i></div></td>'
            . '<td class="engine-cell"><strong>' . h(formatDuration($native['median_ns'])) . '</strong>'
            . '<span>' . h(formatRate($native['operations_per_second'], false, $case['unit'])) . '</span>'
            . '<div class="rail" aria-hidden="true">'
            . '<i class="bar native-bar" style="width:' . number_format($nativeWidth, 2, '.', '') . '%"></i></div></td>'
            . '<td><strong class="ratio-chip">' . h($ratioLabel) . '</strong>'
            . '<small>paired within-run 95% ' . h($ciLabel) . '</small></td>'
            . '<td><span class="stability stability-' . $stabilityClass . '">' . h($stabilityClass) . '</span>'
            . '<small>max relative MAD ' . number_format($stability, 1) . '%</small></td>'
            . '</tr>';

        $gapRows .= '<div class="gap-row"><span>' . h($case['name']) . '</span>'
            . '<div class="gap-rail" aria-hidden="true"><i style="width:' . number_format($gapWidth, 2, '.', '') . '%"></i></div>'
            . '<strong>' . h($ratioLabel) . '</strong></div>';

        $sqlHtml = '';
        foreach ($case['sql'] as $sql) {
            $sqlHtml .= '<pre><code>' . h($sql) . '</code></pre>';
        }
        $detailSections .= '<article class="case-card" id="' . h($case['id']) . '">'
            . '<header><span class="category category-' . strtolower($case['category']) . '">'
            . h($case['category']) . '</span><h3>' . h($case['name']) . '</h3></header>'
            . '<p>' . h($case['description']) . '</p>'
            . '<dl>'
            . htmlDefinitionRows([
                'Fixture' => $case['dataset'],
                'Timed' => $case['timed_scope'],
                'Excluded setup' => $case['setup_scope'],
                'Unit' => $case['unit'],
                'Parameters' => $case['parameters'],
                'Verification' => 'Matched SHA-256 '
                    . substr($case['verification']['digest'], 0, 16)
                    . '…; timed checksum/unit '
                    . $case['verification']['timed_checksum_per_unit'],
            ])
            . '</dl>' . $sqlHtml . '</article>';

        $phpSamples = array_map(
            static fn (float $value): string => formatDuration($value),
            $case['results'][ENGINE_PHP]['samples_ns_per_unit'],
        );
        $nativeSamples = array_map(
            static fn (float $value): string => formatDuration($value),
            $case['results'][ENGINE_NATIVE]['samples_ns_per_unit'],
        );
        $rawSections .= '<details><summary><strong>' . h($case['name']) . '</strong>'
            . '<span>' . count($phpSamples) . ' samples per engine</span></summary>'
            . '<div class="raw-grid"><div><h4>PHP implementation</h4><p>'
            . h(implode(' · ', $phpSamples)) . '</p><p>p10–p90: '
            . h(formatDuration($php['p10_ns'])) . '–' . h(formatDuration($php['p90_ns']))
            . '; iterations/sample: ' . number_format($case['results'][ENGINE_PHP]['iterations_per_sample'])
            . '</p></div><div><h4>Native PDO SQLite</h4><p>'
            . h(implode(' · ', $nativeSamples)) . '</p><p>p10–p90: '
            . h(formatDuration($native['p10_ns'])) . '–' . h(formatDuration($native['p90_ns']))
            . '; iterations/sample: ' . number_format($case['results'][ENGINE_NATIVE]['iterations_per_sample'])
            . '</p></div></div></details>';
    }

    $compileOptions = implode(', ', $environment['native_sqlite']['compile_options']);
    $dirtyEntries = $environment['repository']['libsqlite_dirty_entries'] === []
        ? 'None'
        : implode('; ', $environment['repository']['libsqlite_dirty_entries']);
    $load = implode(', ', array_map(
        static fn (float $value): string => number_format($value, 2),
        $environment['host']['load_average_at_start'] ?? [],
    ));
    $virtualizationScopeNote = match ($environment['host']['virtualized']) {
        true => 'The host is virtualized.',
        false => 'The host does not report an active hypervisor.',
        default => 'Hypervisor status was unavailable.',
    };
    $generated = new DateTimeImmutable($payload['generated_at_utc']);
    $generatedLabel = $generated->format('Y-m-d H:i:s') . ' UTC';

    return '<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title>PHP SQLite benchmark report</title>
<style>
:root{--ink:#17202a;--muted:#65707d;--paper:#f3f0e9;--card:#fffdfa;--line:#ddd8ce;--php:#a94222;--native:#2463a7;--good:#14785b;--warn:#7b4b08;--bad:#9b3030;--shadow:0 14px 35px rgba(37,39,43,.08)}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:var(--paper);color:var(--ink);font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;font-variant-numeric:tabular-nums}a{color:#155590;text-underline-offset:3px}a:focus-visible,summary:focus-visible,.table-shell:focus-visible{outline:3px solid #b66714;outline-offset:3px}.skip-link{position:fixed;left:12px;top:-80px;z-index:20;background:white;color:#153d63;padding:10px 14px;border-radius:7px}.skip-link:focus{top:12px}main{max-width:1440px;margin:auto;padding:0 28px 72px}.hero{background:linear-gradient(118deg,#13263a,#254969 62%,#2d5f73);color:white;padding:64px max(28px,calc((100vw - 1384px)/2));border-bottom:5px solid #d5a448}.eyebrow{letter-spacing:.13em;text-transform:uppercase;font-weight:750;font-size:12px;color:#d9e7f2}.hero h1{font-family:Georgia,serif;font-size:clamp(36px,5vw,68px);line-height:1.02;margin:.25em 0 .3em;max-width:1000px}.hero p{font-size:18px;max-width:850px;color:#e8f0f6;margin:0}.hero-meta{display:flex;gap:20px;flex-wrap:wrap;margin-top:28px;color:#cddbe6;font-size:13px}.summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:-30px 0 38px;position:relative}.metric{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px;box-shadow:var(--shadow)}.metric span{display:block;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-size:11px;font-weight:800}.metric strong{display:block;font-family:Georgia,serif;font-size:31px;line-height:1.15;margin:8px 0 5px}.metric small{color:var(--muted)}section{margin-top:48px}.section-heading{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:18px}.section-heading h2{font-family:Georgia,serif;font-size:30px;margin:0}.section-heading p{max-width:670px;color:var(--muted);margin:0}.reading-guide{background:#fff8e7;border:1px solid #e4c987;border-left:5px solid #ca9232;border-radius:10px;padding:13px 16px;margin:18px 0}.table-shell{overflow:auto;background:var(--card);border:1px solid var(--line);border-radius:14px;box-shadow:var(--shadow)}table{width:100%;border-collapse:collapse;min-width:1080px}caption{text-align:left;padding:12px 14px;color:var(--muted);font-size:12px}thead th{position:sticky;top:0;background:#ebe7de;text-align:left;text-transform:uppercase;letter-spacing:.06em;font-size:11px;color:#56606c;padding:13px;border-bottom:1px solid #d3cdc1}tbody td,tbody th{padding:15px 13px;border-bottom:1px solid #e9e5dd;vertical-align:top;text-align:left}tbody tr:last-child td,tbody tr:last-child th{border-bottom:0}tbody tr:hover{background:#faf7f0}tbody th{font-size:15px;min-width:260px}tbody small{display:block;color:var(--muted);font-weight:400;margin-top:4px;line-height:1.35}.category{display:inline-flex;border-radius:999px;padding:3px 8px;font-size:10px;font-weight:850;letter-spacing:.06em;text-transform:uppercase;white-space:nowrap}.category-simple{background:#e2edf7;color:#245d8f}.category-complex{background:#e5f1e9;color:#266b4b}.category-pathological{background:#f7e5df;color:#91472f}.engine-cell{min-width:170px}.engine-cell strong{font-size:17px}.engine-cell>span{display:block;color:var(--muted);font-size:12px}.rail{height:7px;background:#ece8df;border-radius:8px;margin-top:9px;overflow:hidden}.bar{display:block;height:100%;border-radius:8px}.php-bar{background:var(--php)}.native-bar{background:var(--native)}.ratio-chip{display:inline-block;background:#e5f2ed;color:#126346;border-radius:7px;padding:4px 7px;white-space:nowrap}.stability{display:inline-block;border-radius:6px;padding:3px 7px;font-size:11px;font-weight:800;text-transform:uppercase}.stability-low{background:#e3f1ea;color:var(--good)}.stability-medium{background:#f8edd7;color:var(--warn)}.stability-high{background:#f6dfdf;color:var(--bad)}.gap-panel{background:#17283a;color:white;border-radius:16px;padding:24px;box-shadow:var(--shadow)}.gap-panel h3{margin:0 0 5px;font-family:Georgia,serif;font-size:23px}.gap-panel>p{color:#bac9d5;margin:0 0 20px}.gap-row{display:grid;grid-template-columns:minmax(190px,1.2fr) minmax(180px,2fr) 130px;gap:14px;align-items:center;border-top:1px solid rgba(255,255,255,.1);padding:10px 0}.gap-row span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.gap-row strong{text-align:right;color:#e9bf67}.gap-rail{height:9px;background:rgba(255,255,255,.1);border-radius:7px;overflow:hidden}.gap-rail i{display:block;height:100%;background:linear-gradient(90deg,#e29a59,#e9bf67);border-radius:7px}.case-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.case-card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px;box-shadow:0 8px 24px rgba(37,39,43,.05);scroll-margin-top:20px}.case-card header{display:flex;align-items:center;gap:10px}.case-card h3{font-family:Georgia,serif;font-size:21px;margin:0}.case-card>p{color:#4e5964}.case-card dl{margin:16px 0}.definition-row{display:grid;grid-template-columns:110px 1fr;gap:12px;padding:7px 0;border-top:1px solid #ece8df}.definition-row dt{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);font-weight:800}.definition-row dd{margin:0;overflow-wrap:anywhere}.case-card pre{white-space:pre-wrap;overflow-wrap:anywhere;background:#192938;color:#edf4f8;border-radius:9px;padding:13px;font:12px/1.5 ui-monospace,SFMono-Regular,Menlo,monospace}.method-grid,.environment-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.info-card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:21px}.info-card h3{font-family:Georgia,serif;margin:0 0 10px;font-size:21px}.info-card ul{padding-left:20px;margin-bottom:0}.info-card li+li{margin-top:8px}.environment-grid .info-card dl{margin:0}.raw-list details{background:var(--card);border:1px solid var(--line);border-radius:10px;margin:10px 0}.raw-list summary{cursor:pointer;padding:14px 16px;display:flex;justify-content:space-between;gap:20px}.raw-list summary span{color:var(--muted);font-size:12px}.raw-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;padding:0 16px 16px;border-top:1px solid var(--line)}.raw-grid p{overflow-wrap:anywhere;color:#4e5964}.raw-grid h4{margin-bottom:5px}.report-links{display:flex;gap:12px;flex-wrap:wrap}.button{display:inline-block;border:1px solid #326a9d;background:#fff;color:#205b8e;border-radius:8px;padding:8px 12px;text-decoration:none;font-weight:700}.footer-note{color:var(--muted);margin-top:40px;border-top:1px solid var(--line);padding-top:18px}
.profile-banner{background:#fff1cf;border:1px solid #d5a03b;border-left:6px solid #a9630d;border-radius:10px;padding:13px 16px;margin:18px 0 48px;color:#56370d}.class-summaries{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin:16px 0 22px}.class-summaries>div{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:9px;padding:10px 12px}.class-summaries span,.class-summaries small{display:block;color:#bac9d5;font-size:11px}.class-summaries strong{display:block;color:#f1c570;font-size:17px;margin:2px 0}
@media(max-width:900px){.summary-grid{grid-template-columns:1fr 1fr}.case-grid,.method-grid,.environment-grid{grid-template-columns:1fr}.gap-row{grid-template-columns:1fr 100px}.gap-rail{display:none}.raw-grid{grid-template-columns:1fr}}@media(max-width:560px){main{padding-left:16px;padding-right:16px}.summary-grid,.class-summaries{grid-template-columns:1fr}.hero{padding-top:44px;padding-bottom:55px}.gap-row{grid-template-columns:1fr auto}}@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}@media print{body{background:white}.hero{padding:30px}.summary-grid{margin:20px 0}.metric,.table-shell,.gap-panel,.case-card,.info-card{box-shadow:none}.case-grid{display:block}.case-card{break-inside:avoid;margin:12px 0}.raw-list{display:none}}
</style>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to benchmark results</a>
<header class="hero">
  <span class="eyebrow">Correctness-verified performance study</span>
  <h1>PHP SQLite implementation vs native SQLite</h1>
  <p>The same PDO surface, deterministic data, identical SQL, and fully consumed results—measured from simple calls through adversarial growth cases.</p>
  <div class="hero-meta"><span>' . h($generatedLabel) . '</span><span>' . h($profileLabel) . '</span><span>PHP ' . h($environment['php']['version']) . '</span><span>SQLite ' . h($environment['native_sqlite']['library_version']) . '</span><span>' . h($environment['host']['cpu']) . '</span></div>
</header>
<main id="main-content">
' . $profileBanner . '
<div class="summary-grid">
  <div class="metric"><span>Scenario-set geometric mean</span><strong>' . h($headlineRatioLabel) . '</strong><small>directional summary; not expected application speed</small></div>
  <div class="metric"><span>Case outcomes</span><strong>' . h($summary['native_wins']) . ' / ' . h($summary['case_count']) . ' native</strong><small>extremes ' . h($minimumRatioLabel) . ' ↔ ' . h($maximumRatioLabel) . '</small></div>
  <div class="metric"><span>Coverage</span><strong>' . count($cases) . ' / ' . count($cases) . '</strong><small>workloads passed exact output verification</small></div>
  <div class="metric"><span>Measurement depth</span><strong>' . h($config['samples_per_engine']) . ' samples</strong><small>' . h(number_format($config['target_batch_ms'])) . ' ms adaptive-batch target</small></div>
</div>

<section aria-labelledby="results-title">
  <div class="section-heading"><div><span class="eyebrow" style="color:#56616d">Primary result</span><h2 id="results-title">Case-by-case speed</h2></div><p>Median latency is the decision number. Throughput bars use exact within-row scaling; the faster engine in each row reaches the full rail.</p></div>
  <div class="reading-guide"><strong>How to read this:</strong> lower time is better, higher rate is better, and “native advantage” is PHP-implementation median ÷ native median. A 10× value means native completed the same named unit ten times faster.</div>
  <div class="table-shell" tabindex="0" role="region" aria-label="Scrollable benchmark result table"><table>
    <caption>Median latency and reciprocal throughput per named workload unit. Engine bars are scaled only within each row.</caption>
    <thead><tr><th scope="col">Class</th><th scope="col">Workload</th><th scope="col"><span style="color:var(--php)">PHP implementation</span></th><th scope="col"><span style="color:var(--native)">Native PDO SQLite</span></th><th scope="col">Relative speed</th><th scope="col">Within-run spread</th></tr></thead>
    <tbody>' . $resultRows . '</tbody>
  </table></div>
</section>

<section aria-labelledby="gap-title">
  <div class="gap-panel"><h3 id="gap-title">Speed gap at a glance</h3><p>Log-scaled bars preserve legibility when ratios span orders of magnitude. Longer means a larger directional gap; the label names the faster engine.</p><div class="class-summaries">' . $classSummaries . '</div>' . $gapRows . '</div>
</section>

<section aria-labelledby="cases-title">
  <div class="section-heading"><div><span class="eyebrow" style="color:#56616d">Workload definitions</span><h2 id="cases-title">What each number includes</h2></div><p>Each card defines the fixture, unit of work, timed boundary, excluded setup, SQL, and correctness proof so unlike scopes are not accidentally compared.</p></div>
  <div class="case-grid">' . $detailSections . '</div>
</section>

<section aria-labelledby="method-title">
  <div class="section-heading"><div><span class="eyebrow" style="color:#56616d">Experimental design</span><h2 id="method-title">Methodology and limits</h2></div><p>The protocol prioritizes reproducibility and robust central estimates over a single best-case stopwatch reading.</p></div>
  <div class="method-grid">
    <article class="info-card"><h3>Controls</h3><ul>
      <li>Both engines use PDO, the same SQL text, parameter shapes, fetch mode, deterministic fixtures, and one PHP process.</li>
      <li>Every workload runs on both engines before timing; canonical SHA-256 digests must match or the run aborts.</li>
      <li>Every result is fully fetched. Mutation cases verify final state or roll back to a repeatable state. Timed checksum-per-unit values must also remain constant and match across engines.</li>
      <li>Fast operations are adaptively batched to at least 80% of a ' . h(number_format($config['target_batch_ms'])) . ' ms target, then normalized per named unit.</li>
      <li>' . h($config['warmup_batches']) . ' warmup batches precede ' . h($config['samples_per_engine']) . ' retained samples. Engine order follows a balanced ABBA schedule.</li>
      <li><code>hrtime(true)</code> provides the monotonic clock. No sample or outlier is discarded.</li>
    </ul></article>
    <article class="info-card"><h3>Statistics</h3><ul>
      <li>Median latency is primary; rate is its reciprocal. p10–p90, median absolute deviation, mean, standard deviation, and CV remain in JSON.</li>
      <li>Each ratio is PHP median ÷ native median, with a deterministic 2,000-resample paired bootstrap interval over the interleaved within-run rounds.</li>
      <li>The headline is an equal-weight geometric mean of per-case ratios. It is directional—not a claim about a universal application mix.</li>
      <li>Spread labels use the larger engine relative MAD within this run: low ≤3%, medium ≤8%, high &gt;8%. They do not predict run-to-run or machine-to-machine variation.</li>
      <li>Function-call and PHP loop overhead remains in both sides because the study measures the PHP-facing integration, not bare C opcodes.</li>
    </ul></article>
    <article class="info-card"><h3>Fairness boundaries</h3><ul>
      <li>Native PDO SQLite is the baseline because <code>SQLitePDO</code> extends PDO. The system <code>sqlite3</code> CLI is not used.</li>
      <li>Fixture construction and initial prepare are outside query timings unless a card explicitly includes them.</li>
      <li>Userland prepared SELECT execution still reparses/replans internally; that is implementation behavior, not harness preparation overhead.</li>
      <li>No CREATE INDEX is used because the userland PDO facade does not support it. The 10k primary-key case deliberately exposes the native rowid index versus the PHP scan.</li>
    </ul></article>
    <article class="info-card"><h3>Scope limits</h3><ul>
      <li>These are in-memory, single-process, single-thread workloads. They do not measure concurrency, durability, lock contention, or cold filesystem cache.</li>
      <li>File persistence is excluded: userland autocommit rewrites a whole image and does not have durability-equivalent journal/fsync behavior.</li>
      <li>Memory/RSS is not reported because both engines coexist in one process; attribution would be misleading.</li>
      <li>' . h($virtualizationScopeNote) . ' Background load and thermal scheduling can add variance; interleaving and robust statistics reduce but cannot erase it.</li>
      <li>The results describe this exact source tree, PHP build, SQLite library, and scenario matrix—not all SQL or all deployments.</li>
    </ul></article>
  </div>
</section>

<section aria-labelledby="env-title">
  <div class="section-heading"><div><span class="eyebrow" style="color:#56616d">Reproducibility</span><h2 id="env-title">Environment and provenance</h2></div><div class="report-links"><a class="button" href="' . h($jsonFileName) . '">Full machine-readable JSON</a></div></div>
  <div class="environment-grid">
    <article class="info-card"><h3>Run identity</h3><dl>' . htmlDefinitionRows([
        'Profile' => $profileLabel,
        'PHP engine' => $payload['engines'][ENGINE_PHP]['class'],
        'Native engine' => $payload['engines'][ENGINE_NATIVE]['class'],
        'Command' => $config['command'],
        'Elapsed' => number_format($config['elapsed_seconds'], 1) . ' seconds',
        'Samples' => $config['samples_per_engine'] . ' per engine',
        'Warmups' => $config['warmup_batches'] . ' batches per engine',
        'Batch target' => number_format($config['target_batch_ms']) . ' ms',
        'Sample order' => $config['sample_order'],
    ]) . '</dl></article>
    <article class="info-card"><h3>Host</h3><dl>' . htmlDefinitionRows([
        'OS' => $environment['host']['operating_system'],
        'macOS' => $environment['host']['macos_version'],
        'Architecture' => $environment['host']['architecture'],
        'CPU' => $environment['host']['cpu'],
        'Logical CPUs' => $environment['host']['logical_cpus'],
        'Memory' => formatBytes($environment['host']['memory_bytes']),
        'Virtualized' => $environment['host']['virtualized'],
        'Filesystem' => $environment['host']['filesystem_type'],
        'Disk free' => formatBytes((int) $environment['host']['disk_free_bytes']),
        'Load average' => $load,
    ]) . '</dl></article>
    <article class="info-card"><h3>PHP and native SQLite</h3><dl>' . htmlDefinitionRows([
        'PHP' => $environment['php']['version'],
        'Binary' => $environment['php']['binary'],
        'SAPI' => $environment['php']['sapi'],
        'Memory limit' => $environment['php']['memory_limit'],
        'CLI OPcache' => $environment['php']['opcache_cli_enabled'],
        'JIT mode' => $environment['php']['jit_mode'],
        'JIT buffer' => $environment['php']['jit_buffer_size'],
        'SQLite library' => $environment['native_sqlite']['library_version'],
        'Binding' => $environment['native_sqlite']['binding'],
        'PDO drivers' => $environment['php']['pdo_drivers'],
    ]) . '</dl></article>
    <article class="info-card"><h3>Repository</h3><dl>' . htmlDefinitionRows([
        'Branch' => $environment['repository']['branch'],
        'Commit' => $environment['repository']['commit'],
        'Dirty tree' => $environment['repository']['dirty'],
        'Dirty paths' => $environment['repository']['dirty_path_count'],
        'libsqlite dirty' => $dirtyEntries,
        'Source files' => $environment['repository']['source_file_count'],
        'Source SHA-256' => $environment['repository']['libsqlite_source_sha256'],
        'Harness SHA-256' => $environment['repository']['benchmark_harness_sha256'],
        'Matrix SHA-256' => $config['scenario_matrix_sha256'],
    ]) . '</dl></article>
    <article class="info-card"><h3>Native SQLite build</h3><dl>' . htmlDefinitionRows([
        'Source ID' => $environment['native_sqlite']['source_id'],
        'Compile options' => $compileOptions,
    ]) . '</dl></article>
  </div>
</section>

<section class="raw-list" aria-labelledby="raw-title">
  <div class="section-heading"><div><span class="eyebrow" style="color:#56616d">Audit trail</span><h2 id="raw-title">Raw sample latencies</h2></div><p>Expand any case to inspect every retained normalized sample and its adaptive batch size.</p></div>
  ' . $rawSections . '
</section>

<p class="footer-note">Generated by <code>benchmarks/libsqlite/benchmark.php</code> from schema version ' . BENCHMARK_SCHEMA_VERSION . '. The adjacent JSON contains calibration history, batch checksums, all summary statistics, source hashes, and native SQLite compile options.</p>
</main>
</body>
</html>';
}

function copyArtifact(string $source, string $destination): void
{
    $bytes = file_get_contents($source);
    if ($bytes === false || file_put_contents($destination, $bytes) === false) {
        throw new RuntimeException("Unable to copy artifact to {$destination}");
    }
}

function openReport(string $path): void
{
    if (PHP_OS_FAMILY !== 'Darwin') {
        fwrite(STDERR, "--open is currently supported only on macOS; report is at {$path}\n");
        return;
    }
    $process = @proc_open(
        ['open', $path],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
    );
    if (!is_resource($process)) {
        fwrite(STDERR, "Unable to launch the browser for {$path}\n");
        return;
    }
    stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    if ($status !== 0) {
        fwrite(STDERR, "Browser launch command failed for {$path}\n");
    }
}

function main(array $argv): int
{
    try {
        $options = parseOptions($argv);
    } catch (Throwable $exception) {
        fwrite(STDERR, $exception->getMessage() . "\n\n" . helpText());

        return 2;
    }

    if ($options['help']) {
        fwrite(STDOUT, helpText());

        return 0;
    }

    $allScenarios = scenarios();
    if ($options['list']) {
        foreach ($allScenarios as $scenario) {
            fwrite(
                STDOUT,
                str_pad($scenario['id'], 38) . ' '
                . str_pad($scenario['category'], 13) . ' '
                . $scenario['name'] . PHP_EOL,
            );
        }

        return 0;
    }

    if ($options['cases'] !== []) {
        $selected = array_fill_keys($options['cases'], true);
        $known = array_column($allScenarios, null, 'id');
        foreach (array_keys($selected) as $id) {
            if (!isset($known[$id])) {
                fwrite(STDERR, "Unknown benchmark case: {$id}\n");

                return 2;
            }
        }
        $allScenarios = array_values(array_filter(
            $allScenarios,
            static fn (array $scenario): bool => isset($selected[$scenario['id']]),
        ));
    }

    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        fwrite(STDERR, "This benchmark needs PHP's pdo_sqlite extension.\n");

        return 2;
    }

    $repositoryRoot = dirname(__DIR__, 2);
    $profile = $options['quick']
        ? 'smoke'
        : (
            $options['cases'] === []
            && $options['samples'] === 15
            && abs($options['target_ms'] - 200.0) < 0.001
            && $options['warmup'] === 3
                ? 'full'
                : 'custom'
        );
    $environment = environmentMetadata($repositoryRoot);
    $scenarioMatrixHash = canonicalDigest(
        array_map('scenarioSpecification', $allScenarios),
    );
    $engineDefinitions = engines();
    $targetNanoseconds = (int) round($options['target_ms'] * 1_000_000);

    fwrite(STDOUT, 'PHP SQLite benchmark (' . $profile . " profile)\n");
    fwrite(
        STDOUT,
        sprintf(
            "%d cases; %d retained samples/engine; %.0f ms target; %d warmups\n",
            count($allScenarios),
            $options['samples'],
            $options['target_ms'],
            $options['warmup'],
        ),
    );
    fwrite(
        STDOUT,
        "Baseline: PortLibs\\LibSqlite\\SQLitePDO vs native PDO pdo_sqlite "
        . $environment['native_sqlite']['library_version'] . "\n\n",
    );

    $caseResults = [];
    $started = hrtime(true);
    foreach ($allScenarios as $index => $scenario) {
        fwrite(
            STDOUT,
            sprintf(
                '[%d/%d] %-12s %s ... ',
                $index + 1,
                count($allScenarios),
                $scenario['category'],
                $scenario['name'],
            ),
        );
        try {
            $result = runScenario(
                $scenario,
                $engineDefinitions,
                $options['samples'],
                $options['warmup'],
                $targetNanoseconds,
            );
        } catch (Throwable $exception) {
            fwrite(STDOUT, "FAILED\n");
            fwrite(
                STDERR,
                "{$scenario['id']}: {$exception->getMessage()}\n"
                . $exception->getTraceAsString() . "\n",
            );

            return 1;
        }
        $caseResults[] = $result;
        $ratio = $result['comparison']['native_advantage'];
        fwrite(
            STDOUT,
            'verified; '
            . ($ratio >= 1
                ? formatRatio($ratio, true) . " native\n"
                : formatRatio(1 / $ratio, true) . " PHP\n"),
        );
    }
    $elapsedSeconds = (hrtime(true) - $started) / 1_000_000_000;

    $ratios = array_column(array_column($caseResults, 'comparison'), 'native_advantage');
    $byCategory = [];
    foreach (['Simple', 'Complex', 'Pathological'] as $category) {
        $categoryRatios = [];
        foreach ($caseResults as $case) {
            if ($case['category'] === $category) {
                $categoryRatios[] = $case['comparison']['native_advantage'];
            }
        }
        if ($categoryRatios === []) {
            continue;
        }
        $byCategory[$category] = [
            'case_count' => count($categoryRatios),
            'geometric_mean_native_advantage' => geometricMean($categoryRatios),
            'native_wins' => count(array_filter(
                $categoryRatios,
                static fn (float $ratio): bool => $ratio >= 1,
            )),
        ];
    }
    $payload = [
        'schema_version' => BENCHMARK_SCHEMA_VERSION,
        'generated_at_utc' => gmdate('c'),
        'benchmark' => [
            'title' => 'PHP SQLite implementation vs native PDO SQLite',
            'profile' => $profile,
            'samples_per_engine' => $options['samples'],
            'warmup_batches' => $options['warmup'],
            'target_batch_ms' => $options['target_ms'],
            'clock' => 'hrtime(true), monotonic nanoseconds',
            'sample_order' => 'balanced ABBA',
            'outlier_policy' => 'none removed',
            'storage_mode' => 'in-memory',
            'elapsed_seconds' => $elapsedSeconds,
            'command' => implode(' ', array_map('escapeshellarg', $argv)),
            'harness_sha256' => $environment['repository']['benchmark_harness_sha256'],
            'scenario_matrix_sha256' => $scenarioMatrixHash,
        ],
        'engines' => [
            ENGINE_PHP => [
                'label' => $engineDefinitions[ENGINE_PHP]['label'],
                'class' => $engineDefinitions[ENGINE_PHP]['class'],
                'implementation' => 'Repository userland PHP SQLite PDO compatibility layer',
            ],
            ENGINE_NATIVE => [
                'label' => $engineDefinitions[ENGINE_NATIVE]['label'],
                'class' => $engineDefinitions[ENGINE_NATIVE]['class'],
                'implementation' => 'PHP pdo_sqlite extension linked to native SQLite',
            ],
        ],
        'summary' => [
            'case_count' => count($caseResults),
            'verified_case_count' => count(array_filter(
                $caseResults,
                static fn (array $case): bool => $case['verification']['matched'],
            )),
            'geometric_mean_native_advantage' => geometricMean($ratios),
            'minimum_native_advantage' => min($ratios),
            'maximum_native_advantage' => max($ratios),
            'native_wins' => count(array_filter(
                $ratios,
                static fn (float $ratio): bool => $ratio >= 1,
            )),
            'php_wins' => count(array_filter(
                $ratios,
                static fn (float $ratio): bool => $ratio < 1,
            )),
            'by_category' => $byCategory,
        ],
        'environment' => $environment,
        'cases' => $caseResults,
    ];

    $outputDirectory = $options['output_dir'];
    if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
        fwrite(STDERR, "Unable to create output directory: {$outputDirectory}\n");

        return 1;
    }
    $stamp = gmdate('Ymd\THis\Z');
    $baseName = 'libsqlite-benchmark-' . $stamp;
    $jsonPath = $outputDirectory . '/' . $baseName . '.json';
    $htmlPath = $outputDirectory . '/' . $baseName . '.html';
    $textPath = $outputDirectory . '/' . $baseName . '.txt';
    $table = renderTextTable($caseResults);
    $json = json_encode(
        $payload,
        JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ) . PHP_EOL;
    if (file_put_contents($jsonPath, $json) === false) {
        fwrite(STDERR, "Unable to write JSON artifact: {$jsonPath}\n");

        return 1;
    }
    $html = renderHtmlReport($payload, basename($jsonPath));
    if (file_put_contents($htmlPath, $html) === false
        || file_put_contents($textPath, $table) === false
    ) {
        fwrite(STDERR, "Unable to write report artifacts in {$outputDirectory}\n");

        return 1;
    }

    copyArtifact($jsonPath, $outputDirectory . '/latest.json');
    copyArtifact($htmlPath, $outputDirectory . '/latest.html');
    copyArtifact($textPath, $outputDirectory . '/latest.txt');

    fwrite(STDOUT, "\n" . $table . "\n");
    fwrite(
        STDOUT,
        sprintf(
            "Completed in %.1f s\nHTML: %s\nJSON: %s\nText: %s\n",
            $elapsedSeconds,
            $htmlPath,
            $jsonPath,
            $textPath,
        ),
    );

    if ($options['open']) {
        openReport($htmlPath);
    }

    return 0;
}

exit(main($argv));
