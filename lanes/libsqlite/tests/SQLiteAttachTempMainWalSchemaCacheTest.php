<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachTempMainWalSchemaCachePlan;

$baseSchemas = static fn (): array => [
    'main' => [
        'schema_cookie' => 12,
        'wal_schema_cookie' => 13,
        'tables' => ['wp_options', 'wp_posts'],
        'indexes' => ['wp_options_name'],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 4,
        'tables' => ['wp_options'],
        'indexes' => ['wp_options_temp_name'],
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 7,
        'wal_frames' => [
            ['page' => 2, 'schema_cookie' => 99],
            ['page' => 1, 'schema_cookie' => 8, 'commit' => true],
        ],
        'tables' => ['wp_options'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
];

$plan = static fn (?array $schemas = null, array $tables = ['wp_options', 'main.wp_options', 'archive.wp_options']): array => SQLiteAttachTempMainWalSchemaCachePlan::currentNext($schemas ?? $baseSchemas(), $tables);

$value = static function (array $data, string $path): mixed {
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

$pathCases = [
    'status ok' => ['status', 'ok'],
    'operation names attach temp main wal cache' => ['operation', 'attach-temp-main-wal-schema-cache'],
    'source defaults main' => ['source', 'main'],
    'search order starts temp main archive' => ['search_order', ['temp', 'main', 'archive']],
    'current temp cookie is preserved' => ['schema_cookies_current.temp', 4],
    'current main cookie is preserved' => ['schema_cookies_current.main', 12],
    'current archive cookie is preserved' => ['schema_cookies_current.archive', 7],
    'next main cookie comes from wal page one' => ['schema_cookies_next.main', 13],
    'next archive cookie comes from committed frame one' => ['schema_cookies_next.archive', 8],
    'next temp cookie is unchanged' => ['schema_cookies_next.temp', 4],
    'changed schemas include main archive' => ['changed_schemas', ['main', 'archive']],
    'unqualified wp_options resolves to temp' => ['prepared_tables.wp_options.schema', 'temp'],
    'unqualified wp_options is found' => ['prepared_tables.wp_options.found', true],
    'unqualified wp_options is not qualified' => ['prepared_tables.wp_options.qualified', false],
    'unqualified wp_options current cookie is temp' => ['prepared_tables.wp_options.schema_cookie', 4],
    'unqualified wp_options next cookie is temp' => ['prepared_tables.wp_options.next_schema_cookie', 4],
    'unqualified wp_options avoids reprepare when only main wal changes' => ['prepared_tables.wp_options.requires_reprepare', false],
    'unqualified wp_options shadows main and archive' => ['prepared_tables.wp_options.shadowed_schemas', ['main', 'archive']],
    'temp shadows main flag is true' => ['temp_shadows_main', true],
    'wal sources include main archive' => ['wal_schema_cookie_sources', ['main', 'archive']],
    'database list temp seq zero' => ['database_list.0.name', 'temp'],
    'database list main seq one' => ['database_list.1.name', 'main'],
    'database list archive seq two' => ['database_list.2.name', 'archive'],
    'database list main next cookie' => ['database_list.1.next_schema_cookie', 13],
    'database list archive next cookie' => ['database_list.2.next_schema_cookie', 8],
    'database list archive file' => ['database_list.2.file', '/srv/wp/archive.sqlite'],
    'dependencies include wal schema cookie' => ['dependencies.1', 'sqlite-wal-page-one-schema-cookie'],
    'dependencies include temp main resolution' => ['dependencies.2', 'sqlite-temp-main-name-resolution'],
    'plan requires reprepare for qualified changed schemas' => ['requires_reprepare', true],
];

foreach ($pathCases as $name => [$path, $expected]) {
    $tests['attach temp main wal schema cache ' . $name] = static function (TestRunner $t) use ($plan, $value, $path, $expected): void {
        $t->same($expected, $value($plan(), $path));
    };
}

$tests['attach temp main wal schema cache qualified table paths are keyed by original SQL text'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan();

    $t->same('main', $result['prepared_tables']['main.wp_options']['schema']);
    $t->same(true, $result['prepared_tables']['main.wp_options']['qualified']);
    $t->same(12, $result['prepared_tables']['main.wp_options']['schema_cookie']);
    $t->same(13, $result['prepared_tables']['main.wp_options']['next_schema_cookie']);
    $t->same(true, $result['prepared_tables']['main.wp_options']['requires_reprepare']);
    $t->same([], $result['prepared_tables']['main.wp_options']['shadowed_schemas']);
    $t->same('archive', $result['prepared_tables']['archive.wp_options']['schema']);
    $t->same(true, $result['prepared_tables']['archive.wp_options']['requires_reprepare']);
};

$predicateCases = [
    'uncommitted wal frame does not advance schema cookie' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['archive']['wal_frames'][1]['commit'] = false;
        return $plan($schemas)['schema_cookies_next']['archive'] === 7;
    },
    'non page one wal frame does not advance schema cookie' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['archive']['wal_frames'] = [['page' => 2, 'schema_cookie' => 99, 'commit' => true]];
        return $plan($schemas)['schema_cookies_next']['archive'] === 7;
    },
    'explicit wal schema cookie wins over frame cookie' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['archive']['wal_schema_cookie'] = 10;
        return $plan($schemas)['schema_cookies_next']['archive'] === 10;
    },
    'temp wal schema change forces unqualified reprepare' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['temp']['wal_schema_cookie'] = 5;
        return $plan($schemas)['prepared_tables']['wp_options']['requires_reprepare'] === true;
    },
    'missing temp table lets unqualified name resolve to main' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['temp']['tables'] = [];
        return $plan($schemas, ['wp_options'])['prepared_tables']['wp_options']['schema'] === 'main';
    },
    'missing temp table records archive shadow behind main' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['temp']['tables'] = [];
        return $plan($schemas, ['wp_options'])['prepared_tables']['wp_options']['shadowed_schemas'] === ['archive'];
    },
    'missing temp table main wal change forces unqualified reprepare' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['temp']['tables'] = [];
        return $plan($schemas, ['wp_options'])['prepared_tables']['wp_options']['requires_reprepare'] === true;
    },
    'missing temp and main table resolves to archive' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['temp']['tables'] = [];
        $schemas['main']['tables'] = ['wp_posts'];
        return $plan($schemas, ['wp_options'])['prepared_tables']['wp_options']['schema'] === 'archive';
    },
    'missing all tables records not found on main fallback' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['temp']['tables'] = [];
        $schemas['main']['tables'] = [];
        $schemas['archive']['tables'] = [];
        return $plan($schemas, ['wp_options'])['prepared_tables']['wp_options']['found'] === false;
    },
    'quoted source schema is normalized' => static function () use ($baseSchemas): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNext($baseSchemas(), ['wp_options'], '"MAIN"')['source'] === 'main';
    },
    'attached source schema is accepted' => static function () use ($baseSchemas): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNext($baseSchemas(), ['archive.wp_options'], 'archive')['source'] === 'archive';
    },
    'empty prepared table list keeps schema decisions' => static function () use ($baseSchemas): bool {
        $result = SQLiteAttachTempMainWalSchemaCachePlan::currentNext($baseSchemas(), []);
        return $result['prepared_tables'] === [] && $result['changed_schemas'] === ['main', 'archive'];
    },
    'default missing temp and main schemas are supplied' => static function (): bool {
        $result = SQLiteAttachTempMainWalSchemaCachePlan::currentNext(['archive' => ['schema_cookie' => 1, 'tables' => ['wp_options']]]);
        return $result['search_order'] === ['temp', 'main', 'archive'];
    },
    'default missing temp and main schemas do not shadow archive' => static function (): bool {
        $result = SQLiteAttachTempMainWalSchemaCachePlan::currentNext(['archive' => ['schema_cookie' => 1, 'tables' => ['wp_options']]]);
        return $result['prepared_tables']['wp_options']['schema'] === 'archive';
    },
    'database list keeps shared cache marker' => static function () use ($plan): bool {
        return $plan()['database_list'][2]['cache'] === 'shared';
    },
    'database list keeps temp file empty string' => static function () use ($plan): bool {
        return $plan()['database_list'][0]['file'] === '';
    },
    'main wal schema cookie reports changed source' => static function () use ($plan): bool {
        return in_array('main', $plan()['changed_schemas'], true);
    },
    'archive frame schema cookie reports changed source' => static function () use ($plan): bool {
        return in_array('archive', $plan()['changed_schemas'], true);
    },
    'temp unchanged schema is absent from changed sources' => static function () use ($plan): bool {
        return !in_array('temp', $plan()['changed_schemas'], true);
    },
    'temp shadow remains true only when main also has table' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['main']['tables'] = ['wp_posts'];
        return $plan($schemas)['temp_shadows_main'] === false;
    },
    'qualified missing attached table is not found' => static function () use ($baseSchemas, $plan): bool {
        return $plan($baseSchemas(), ['archive.wp_missing'])['prepared_tables']['archive.wp_missing']['found'] === false;
    },
    'qualified missing attached table still tracks archive cookie' => static function () use ($baseSchemas, $plan): bool {
        return $plan($baseSchemas(), ['archive.wp_missing'])['prepared_tables']['archive.wp_missing']['next_schema_cookie'] === 8;
    },
    'case insensitive prepared names resolve through temp' => static function () use ($baseSchemas, $plan): bool {
        return $plan($baseSchemas(), ['WP_OPTIONS'])['prepared_tables']['WP_OPTIONS']['schema'] === 'temp';
    },
    'case insensitive schema qualifier resolves main' => static function () use ($baseSchemas, $plan): bool {
        return $plan($baseSchemas(), ['MAIN.WP_OPTIONS'])['prepared_tables']['MAIN.WP_OPTIONS']['schema'] === 'main';
    },
    'later attached schema appears after archive' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['network'] = ['schema_cookie' => 2, 'tables' => ['wp_blogs'], 'file' => '/srv/wp/network.sqlite'];
        return $plan($schemas, ['network.wp_blogs'])['search_order'] === ['temp', 'main', 'archive', 'network'];
    },
    'later attached schema qualified table is found' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['network'] = ['schema_cookie' => 2, 'tables' => ['wp_blogs'], 'file' => '/srv/wp/network.sqlite'];
        return $plan($schemas, ['network.wp_blogs'])['prepared_tables']['network.wp_blogs']['found'] === true;
    },
    'later attached schema without wal does not require reprepare' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['network'] = ['schema_cookie' => 2, 'tables' => ['wp_blogs'], 'file' => '/srv/wp/network.sqlite'];
        return $plan($schemas, ['network.wp_blogs'])['prepared_tables']['network.wp_blogs']['requires_reprepare'] === false;
    },
    'later attached wal schema does require reprepare' => static function () use ($baseSchemas, $plan): bool {
        $schemas = $baseSchemas();
        $schemas['network'] = ['schema_cookie' => 2, 'wal_schema_cookie' => 3, 'tables' => ['wp_blogs'], 'file' => '/srv/wp/network.sqlite'];
        return $plan($schemas, ['network.wp_blogs'])['prepared_tables']['network.wp_blogs']['requires_reprepare'] === true;
    },
];

foreach ($predicateCases as $name => $predicate) {
    $tests['attach temp main wal schema cache ' . $name] = static function (TestRunner $t) use ($predicate): void {
        $t->same(true, $predicate());
    };
}

$errorCases = [
    'rejects missing source schema' => static fn () => SQLiteAttachTempMainWalSchemaCachePlan::currentNext($baseSchemas(), ['wp_options'], 'missing'),
    'rejects non integer schema cookie' => static fn () => SQLiteAttachTempMainWalSchemaCachePlan::currentNext(['main' => ['schema_cookie' => '1']]),
    'rejects wal frame without integer page' => static fn () => SQLiteAttachTempMainWalSchemaCachePlan::currentNext(['main' => ['schema_cookie' => 1, 'wal_frames' => [['page' => '1']]]]),
    'rejects empty prepared table name' => static fn () => SQLiteAttachTempMainWalSchemaCachePlan::currentNext($baseSchemas(), ['']),
    'rejects empty qualified schema name' => static fn () => SQLiteAttachTempMainWalSchemaCachePlan::currentNext($baseSchemas(), ['.wp_options']),
];

foreach ($errorCases as $name => $callback) {
    $tests['attach temp main wal schema cache ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(InvalidArgumentException::class, $callback);
    };
}

return $tests;
