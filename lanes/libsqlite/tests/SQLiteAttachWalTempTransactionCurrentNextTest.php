<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachWalTempTransactionCurrentNextPlan;

$tests = [];

$schemas = [
    'main' => ['schema_cookie' => 40, 'wal_schema_cookie' => 41, 'file' => '/srv/wp/current.sqlite'],
    'temp' => ['schema_cookie' => 6, 'temp' => true, 'file' => ''],
    'archive' => [
        'schema_cookie' => 10,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 11, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 12, 'commit' => false],
        ],
        'file' => '/srv/wp/archive.sqlite',
    ],
    'network' => ['schema_cookie' => 3, 'file' => '/srv/wp/network.sqlite'],
];

$operations = [
    ['op' => 'schema_write', 'schema' => 'main', 'object' => 'wp_options_autoload_idx'],
    ['op' => 'savepoint', 'savepoint' => 'plugin_import'],
    ['op' => 'schema_write', 'schema' => 'temp', 'object' => 'wp_options_stage'],
    ['op' => 'schema_write', 'schema' => 'archive', 'object' => 'wp_archive_options_idx'],
    ['op' => 'rollback_to', 'savepoint' => 'plugin_import'],
    ['op' => 'schema_write', 'schema' => 'network', 'object' => 'wp_blogs_domain_idx'],
    ['op' => 'release', 'savepoint' => 'plugin_import'],
];

$plan = static fn (?string $outcome = null, ?array $overrideSchemas = null, ?array $overrideOperations = null): array => SQLiteAttachWalTempTransactionCurrentNextPlan::plan(
    $overrideSchemas ?? $schemas,
    $overrideOperations ?? $operations,
    $outcome ?? 'commit',
);

$value = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$cases = [
    'status committed' => ['status', 'committed'],
    'operation marker' => ['operation', 'attach-wal-temp-transaction-current'],
    'outcome commit' => ['outcome', 'commit'],
    'schema count' => ['schema_count', 4],
    'operation count' => ['operation_count', 7],
    'schema write count' => ['schema_write_count', 4],
    'changed schemas' => ['changed_schemas', ['archive', 'main', 'network', 'temp']],
    'rolled back schemas' => ['rolled_back_schemas', ['archive', 'temp']],
    'open savepoints empty after release' => ['open_savepoints', []],
    'current reader policy' => ['current_reader_policy', 'continue_current_snapshot_until_statement_reset'],
    'next reader policy' => ['next_reader_policy', 'read_committed_schema_cookies'],
    'requires reprepare' => ['requires_reprepare', true],
    'reprepare schemas' => ['reprepare_schemas', ['archive', 'main', 'network', 'temp']],
    'main current cookie from wal schema cookie' => ['schemas.main.current_cookie', 41],
    'main transaction next cookie' => ['schemas.main.transaction_next_cookie', 42],
    'main post transaction cookie' => ['schemas.main.post_transaction_cookie', 42],
    'main journal wal' => ['schemas.main.journal', 'wal'],
    'main pending writes' => ['schemas.main.pending_writes', 1],
    'main pending object' => ['schemas.main.pending_objects.0', 'wp_options_autoload_idx'],
    'main current reader hidden' => ['schemas.main.visible_to_current_reader', false],
    'main visible after commit' => ['schemas.main.visible_after_commit', true],
    'temp current cookie' => ['schemas.temp.current_cookie', 6],
    'temp transaction next restored by rollback' => ['schemas.temp.transaction_next_cookie', 6],
    'temp post transaction unchanged' => ['schemas.temp.post_transaction_cookie', 6],
    'temp journal' => ['schemas.temp.journal', 'temp-rollback'],
    'temp pending writes cleared' => ['schemas.temp.pending_writes', 0],
    'archive current cookie from committed wal frame' => ['schemas.archive.current_cookie', 11],
    'archive uncommitted wal frame ignored' => ['schemas.archive.transaction_next_cookie', 11],
    'archive post transaction unchanged' => ['schemas.archive.post_transaction_cookie', 11],
    'archive pending writes cleared' => ['schemas.archive.pending_writes', 0],
    'network current cookie' => ['schemas.network.current_cookie', 3],
    'network transaction next cookie' => ['schemas.network.transaction_next_cookie', 4],
    'network post transaction cookie' => ['schemas.network.post_transaction_cookie', 4],
    'network pending object' => ['schemas.network.pending_objects.0', 'wp_blogs_domain_idx'],
    'first step op' => ['steps.0.op', 'schema_write'],
    'first step action wal' => ['steps.0.action', 'wal'],
    'first step next main cookie' => ['steps.0.next_cookies.main', 42],
    'savepoint step action' => ['steps.1.action', 'savepoint_open'],
    'temp write action' => ['steps.2.action', 'temp-rollback'],
    'temp write next cookie' => ['steps.2.next_cookies.temp', 7],
    'archive write next cookie' => ['steps.3.next_cookies.archive', 12],
    'rollback step action' => ['steps.4.action', 'savepoint_rollback'],
    'rollback step schemas' => ['steps.4.schemas', ['archive', 'temp']],
    'rollback restores temp cookie' => ['steps.4.next_cookies.temp', 6],
    'rollback restores archive cookie' => ['steps.4.next_cookies.archive', 11],
    'network write action' => ['steps.5.action', 'wal'],
    'release step action' => ['steps.6.action', 'savepoint_release'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-wal-temp-transaction-current'],
    'dependency visibility' => ['dependencies.1', 'sqlite-attach-wal-temp-transaction-schema-cookie-visibility'],
    'dependency rollback' => ['dependencies.2', 'sqlite-savepoint-rollback-restores-uncommitted-schema-cookies'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['attach wal temp current transaction ' . $name] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
        $t->same($expected, $value($plan(), $path));
    };
}

$predicateCases = [
    'transaction rollback hides all pending schema cookie changes' => static function () use ($plan): bool {
        $result = $plan('rollback');
        return $result['status'] === 'rolled_back'
            && $result['next_reader_policy'] === 'reuse_current_schema_cookies'
            && $result['requires_reprepare'] === false
            && $result['reprepare_schemas'] === []
            && $result['schemas']['main']['post_transaction_cookie'] === 41
            && $result['schemas']['network']['post_transaction_cookie'] === 3;
    },
    'committed wal frame cookie wins over stale schema cookie' => static function () use ($plan): bool {
        return $plan()['schemas']['archive']['current_cookie'] === 11
            && $plan()['steps'][3]['current_cookies']['archive'] === 11;
    },
    'wal schema cookie wins when no committed frame exists' => static function () use ($schemas, $operations): bool {
        $copy = $schemas;
        unset($copy['main']['wal_frames']);
        $copy['main']['wal_schema_cookie'] = 44;
        return SQLiteAttachWalTempTransactionCurrentNextPlan::plan($copy, [$operations[0]])['schemas']['main']['current_cookie'] === 44;
    },
    'uncommitted wal frame does not become current cookie' => static function () use ($schemas, $operations): bool {
        $copy = $schemas;
        $copy['archive']['wal_frames'] = [['page' => 1, 'schema_cookie' => 15, 'commit' => false]];
        return SQLiteAttachWalTempTransactionCurrentNextPlan::plan($copy, [$operations[3]])['schemas']['archive']['current_cookie'] === 10;
    },
    'temp schema is routed to temp rollback journal' => static fn (): bool => $plan()['schemas']['temp']['journal'] === 'temp-rollback',
    'attached schema is routed to wal journal' => static fn (): bool => $plan()['schemas']['archive']['journal'] === 'wal',
    'quoted schema names normalize before lookup' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, [
            ['op' => 'schema_write', 'schema' => '"MAIN"', 'object' => 'wp_options_idx'],
        ]);
        return $result['schemas']['main']['transaction_next_cookie'] === 42;
    },
    'bracketed temp schema names normalize before lookup' => static function () use ($schemas): bool {
        $result = SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, [
            ['op' => 'schema_write', 'schema' => '[TEMP]', 'object' => 'scratch'],
        ]);
        return $result['schemas']['temp']['transaction_next_cookie'] === 7;
    },
    'empty operations rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, []);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'unknown outcome rejected' => static function () use ($schemas, $operations): bool {
        try {
            SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, $operations, 'abort');
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'missing schema rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, [
                ['op' => 'schema_write', 'schema' => 'missing', 'object' => 'x'],
            ]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'missing savepoint rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, [
                ['op' => 'rollback_to', 'savepoint' => 'missing'],
            ]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'unsupported operation rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, [
                ['op' => 'select', 'schema' => 'main'],
            ]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'non integer schema cookie rejected' => static function () use ($schemas, $operations): bool {
        $copy = $schemas;
        $copy['main']['schema_cookie'] = '40';
        try {
            SQLiteAttachWalTempTransactionCurrentNextPlan::plan($copy, [$operations[0]]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'empty savepoint name rejected' => static function () use ($schemas): bool {
        try {
            SQLiteAttachWalTempTransactionCurrentNextPlan::plan($schemas, [
                ['op' => 'savepoint', 'savepoint' => ''],
            ]);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'rolled back schemas are sorted' => static fn (): bool => $plan()['rolled_back_schemas'] === ['archive', 'temp'],
    'changed schemas are sorted' => static fn (): bool => $plan()['changed_schemas'] === ['archive', 'main', 'network', 'temp'],
    'rollback to savepoint does not erase earlier main write' => static fn (): bool => $plan()['schemas']['main']['transaction_next_cookie'] === 42,
    'release keeps committed network write pending' => static fn (): bool => $plan()['steps'][6]['next_cookies']['network'] === 4,
    'current reader never sees uncommitted main cookie' => static fn (): bool => $plan()['steps'][5]['current_cookies']['main'] === 41,
];

foreach ($predicateCases as $name => $callback) {
    $tests['attach wal temp current transaction ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->true($callback());
    };
}

return $tests;
