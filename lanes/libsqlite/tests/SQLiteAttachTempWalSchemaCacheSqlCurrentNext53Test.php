<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachTempMainWalSchemaCachePlan;

$schemas53 = static fn (): array => [
    'main' => [
        'schema_cookie' => 21,
        'wal_schema_cookie' => 22,
        'tables' => ['wp_options', 'wp_posts', 'wp_optionmeta'],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 5,
        'tables' => ['wp_options', 'wp_temp_import'],
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 9,
        'wal_frames' => [
            ['page' => 3, 'schema_cookie' => 99, 'commit' => true],
            ['page' => 1, 'schema_cookie' => 10, 'commit' => true],
        ],
        'tables' => ['wp_options', 'wp_archive_meta'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
    'network' => [
        'schema_cookie' => 11,
        'tables' => ['wp_blogs', 'wp_sitemeta'],
        'file' => '/srv/wp/network.sqlite',
    ],
];

$statements53 = static fn (): array => [
    "SELECT option_value FROM wp_options WHERE option_name = 'siteurl'",
    'SELECT main.wp_options.option_name FROM main.wp_options JOIN archive.wp_archive_meta ON archive.wp_archive_meta.option_id = main.wp_options.option_id',
    'UPDATE wp_options SET option_value = ? WHERE option_name = ?',
    'INSERT INTO archive.wp_options(option_name, option_value) VALUES(?, ?)',
    'DELETE FROM main.wp_optionmeta WHERE option_id IN (SELECT option_id FROM wp_options)',
    'SELECT * FROM network.wp_blogs JOIN wp_options ON wp_options.option_name = network.wp_blogs.domain',
    'SELECT * FROM "MAIN"."wp_posts" JOIN `archive`.`wp_options` ON 1 = 1',
];

$plan53 = static fn (?array $schemas = null, ?array $statements = null): array => SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql(
    $schemas ?? $schemas53(),
    $statements ?? $statements53(),
);

$value53 = static function (array $data, string $path): mixed {
    $cursor = $data;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }

        return null;
    }

    return $cursor;
};

$pathCases53 = [
    'status ok' => ['status', 'ok'],
    'operation is sql schema cache' => ['operation', 'attach-temp-main-wal-schema-cache-sql'],
    'statement count' => ['statement_count', 7],
    'search order includes temp first' => ['search_order', ['temp', 'main', 'archive', 'network']],
    'changed schemas include main archive' => ['changed_schemas', ['main', 'archive']],
    'main next cookie comes from wal cookie' => ['schema_cookies_next.main', 22],
    'archive next cookie comes from committed page one frame' => ['schema_cookies_next.archive', 10],
    'network next cookie unchanged' => ['schema_cookies_next.network', 11],
    'first statement table extraction' => ['statements.0.tables', ['wp_options']],
    'first statement resolves temp shadow' => ['statements.0.resolved_tables.wp_options.schema', 'temp'],
    'first statement does not reprepare for main wal shadow' => ['statements.0.requires_reprepare', false],
    'first statement is read only' => ['statements.0.read_only', true],
    'join statement extracts both qualified tables' => ['statements.1.tables', ['main.wp_options', 'archive.wp_archive_meta']],
    'join statement schemas' => ['statements.1.schemas', ['main', 'archive']],
    'join statement requires reprepare' => ['statements.1.requires_reprepare', true],
    'update statement write flag' => ['statements.2.read_only', false],
    'update statement resolves temp' => ['statements.2.resolved_tables.wp_options.schema', 'temp'],
    'insert statement write flag' => ['statements.3.read_only', false],
    'insert statement archive reprepare' => ['statements.3.requires_reprepare', true],
    'delete statement write flag' => ['statements.4.read_only', false],
    'delete statement extracts delete and subquery tables' => ['statements.4.tables', ['main.wp_optionmeta', 'wp_options']],
    'delete statement missing unqualified main table falls back temp shadow for subquery' => ['statements.4.resolved_tables.wp_options.schema', 'temp'],
    'network join extracts attached and temp tables' => ['statements.5.tables', ['network.wp_blogs', 'wp_options']],
    'network statement schemas' => ['statements.5.schemas', ['network', 'temp']],
    'network statement no reprepare' => ['statements.5.requires_reprepare', false],
    'quoted qualified names normalize' => ['statements.6.tables', ['main.wp_posts', 'archive.wp_options']],
    'dependency includes consolidated schema-cache sql marker' => ['dependencies.3', 'sqlite-attach-temp-wal-schema-cache-sql'],
];

foreach ($pathCases53 as $name => [$path, $expected]) {
    $tests['attach temp wal schema cache sql current next53 ' . $name] = static function (TestRunner $t) use ($plan53, $value53, $path, $expected): void {
        $t->same($expected, $value53($plan53(), $path));
    };
}

$predicateCases53 = [
    'main join table next cookie' => static function () use ($plan53): bool {
        return $plan53()['statements']['1']['resolved_tables']['main.wp_options']['next_schema_cookie'] === 22;
    },
    'archive join table next cookie' => static function () use ($plan53): bool {
        return $plan53()['statements']['1']['resolved_tables']['archive.wp_archive_meta']['next_schema_cookie'] === 10;
    },
    'quoted main table requires reprepare' => static function () use ($plan53): bool {
        return $plan53()['statements']['6']['resolved_tables']['main.wp_posts']['requires_reprepare'] === true;
    },
    'quoted archive table requires reprepare' => static function () use ($plan53): bool {
        return $plan53()['statements']['6']['resolved_tables']['archive.wp_options']['requires_reprepare'] === true;
    },
    'prepared tables retain table-list plan' => static function () use ($plan53): bool {
        return $plan53()['prepared_tables']['archive.wp_options']['schema'] === 'archive';
    },
    'temp wal cookie makes temp select reprepare' => static function () use ($schemas53): bool {
        $schemas = $schemas53();
        $schemas['temp']['wal_schema_cookie'] = 6;
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas, ['SELECT * FROM wp_options'])['statements']['0']['requires_reprepare'] === true;
    },
    'main wal cookie reparses unqualified when temp shadow is absent' => static function () use ($schemas53): bool {
        $schemas = $schemas53();
        $schemas['temp']['tables'] = [];
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas, ['SELECT * FROM wp_options'])['statements']['0']['resolved_tables']['wp_options']['schema'] === 'main'
            && SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas, ['SELECT * FROM wp_options'])['statements']['0']['requires_reprepare'] === true;
    },
    'archive uncommitted page one frame keeps statement stable' => static function () use ($schemas53): bool {
        $schemas = $schemas53();
        $schemas['archive']['wal_frames'] = [['page' => 1, 'schema_cookie' => 10, 'commit' => false]];
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas, ['SELECT * FROM archive.wp_options'])['statements']['0']['requires_reprepare'] === false;
    },
    'archive non page one frame keeps statement stable' => static function () use ($schemas53): bool {
        $schemas = $schemas53();
        $schemas['archive']['wal_frames'] = [['page' => 4, 'schema_cookie' => 10, 'commit' => true]];
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas, ['SELECT * FROM archive.wp_options'])['statements']['0']['requires_reprepare'] === false;
    },
    'explicit archive wal cookie wins over frame cookie' => static function () use ($schemas53): bool {
        $schemas = $schemas53();
        $schemas['archive']['wal_schema_cookie'] = 12;
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas, ['SELECT * FROM archive.wp_options'])['schema_cookies_next']['archive'] === 12;
    },
    'insert into unqualified temp import remains stable' => static function () use ($schemas53): bool {
        $plan = SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['INSERT INTO wp_temp_import(option_name) VALUES(?)']);
        return $plan['statements']['0']['resolved_tables']['wp_temp_import']['schema'] === 'temp'
            && $plan['statements']['0']['requires_reprepare'] === false;
    },
    'update qualified main table reparses' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['UPDATE main.wp_posts SET post_title = ?'])['statements']['0']['requires_reprepare'] === true;
    },
    'delete qualified network table remains stable' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['DELETE FROM network.wp_sitemeta WHERE meta_key = ?'])['statements']['0']['requires_reprepare'] === false;
    },
    'subquery table is deduplicated' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT * FROM wp_options WHERE option_id IN (SELECT option_id FROM wp_options)'])['statements']['0']['tables'] === ['wp_options'];
    },
    'statement schemas deduplicate repeated table' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT * FROM wp_options a JOIN wp_options b ON a.option_id = b.option_id'])['statements']['0']['schemas'] === ['temp'];
    },
    'case insensitive qualified attached table resolves' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT * FROM ARCHIVE.WP_OPTIONS'])['statements']['0']['resolved_tables']['archive.wp_options']['schema'] === 'archive';
    },
    'single quoted qualified table normalizes' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ["SELECT * FROM 'archive'.'wp_options'"])['statements']['0']['tables'] === ['archive.wp_options'];
    },
    'source schema can be attached' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT * FROM archive.wp_options'], 'archive')['source'] === 'archive';
    },
    'empty statement list keeps schema cookies' => static function () use ($schemas53): bool {
        $plan = SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), []);
        return $plan['statement_count'] === 0 && $plan['schema_cookies_next']['main'] === 22;
    },
    'missing referenced qualified schema records not found' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT * FROM missing.wp_options'])['prepared_tables']['missing.wp_options']['found'] === false;
    },
    'missing referenced qualified schema still tracks null cookie' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT * FROM missing.wp_options'])['prepared_tables']['missing.wp_options']['next_schema_cookie'] === null;
    },
    'unqualified missing table falls back main not found' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT * FROM wp_missing'])['prepared_tables']['wp_missing']['schema'] === 'main';
    },
    'unqualified missing table follows main changed reprepare' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT * FROM wp_missing'])['prepared_tables']['wp_missing']['requires_reprepare'] === true;
    },
    'database list preserves attached order' => static function () use ($plan53): bool {
        return array_column($plan53()['database_list'], 'name') === ['temp', 'main', 'archive', 'network'];
    },
    'wal sources include only wal schemas' => static function () use ($plan53): bool {
        return $plan53()['wal_schema_cookie_sources'] === ['main', 'archive'];
    },
    'statement zero shadowed schemas are main and archive' => static function () use ($plan53): bool {
        return $plan53()['statements']['0']['resolved_tables']['wp_options']['shadowed_schemas'] === ['main', 'archive'];
    },
    'statement one qualified main has no shadowed schemas' => static function () use ($plan53): bool {
        return $plan53()['statements']['1']['resolved_tables']['main.wp_options']['shadowed_schemas'] === [];
    },
    'network attached schema can become changed by wal frame' => static function () use ($schemas53): bool {
        $schemas = $schemas53();
        $schemas['network']['wal_frames'] = [['page' => 1, 'schema_cookie' => 12, 'commit' => true]];
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas, ['SELECT * FROM network.wp_blogs'])['statements']['0']['requires_reprepare'] === true;
    },
    'network attached schema without wal frame remains unchanged' => static function () use ($schemas53): bool {
        return !in_array('network', SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT * FROM network.wp_blogs'])['changed_schemas'], true);
    },
    'read only with leading whitespace' => static function () use ($schemas53): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ["\n SELECT * FROM wp_options"])['statements']['0']['read_only'] === true;
    },
];

foreach ($predicateCases53 as $name => $predicate) {
    $tests['attach temp wal schema cache sql current next53 ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

$errorCases53 = [
    'rejects empty sql text' => static fn () => SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['']),
    'rejects sql without table reference' => static fn () => SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT 1']),
    'rejects missing source schema' => static fn () => SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql($schemas53(), ['SELECT * FROM wp_options'], 'missing'),
    'rejects non integer schema cookie' => static fn () => SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql(['main' => ['schema_cookie' => '21']], ['SELECT * FROM wp_options']),
    'rejects wal frame without integer page' => static fn () => SQLiteAttachTempMainWalSchemaCachePlan::currentNextSql(['main' => ['schema_cookie' => 21, 'wal_frames' => [['page' => '1']]]], ['SELECT * FROM wp_options']),
];

foreach ($errorCases53 as $name => $callback) {
    $tests['attach temp wal schema cache sql current next53 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
