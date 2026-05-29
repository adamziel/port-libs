<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachTempMainWalSchemaCachePlan;

$schemas64 = static fn (): array => [
    'main' => [
        'schema_cookie' => 30,
        'wal_schema_cookie' => 31,
        'tables' => ['wp_options', 'wp_posts'],
        'next_tables' => ['wp_posts'],
        'indexes' => ['wp_options_name'],
        'next_indexes' => [],
        'file' => '/srv/wp/current.sqlite',
        'cache' => 'shared',
    ],
    'temp' => [
        'schema_cookie' => 4,
        'tables' => ['wp_options'],
        'next_tables' => [],
        'indexes' => ['wp_options_temp_name'],
        'next_indexes' => [],
        'file' => '',
    ],
    'archive' => [
        'schema_cookie' => 8,
        'wal_frames' => [
            ['page' => 1, 'schema_cookie' => 9, 'commit' => true],
        ],
        'tables' => ['wp_archive_options'],
        'next_tables' => ['wp_options', 'wp_archive_options'],
        'indexes' => [],
        'next_indexes' => ['wp_options_name'],
        'file' => '/srv/wp/archive.sqlite',
        'cache' => 'shared',
    ],
    'network' => [
        'schema_cookie' => 2,
        'tables' => ['wp_blogs'],
        'next_tables' => ['wp_blogs', 'wp_options'],
        'indexes' => [],
        'next_indexes' => [],
        'file' => '/srv/wp/network.sqlite',
    ],
];

$plan64 = static fn (?array $schemas = null, array $tables = ['wp_options', 'main.wp_options', 'archive.wp_options', 'network.wp_options', 'wp_blogs']): array => SQLiteAttachTempMainWalSchemaCachePlan::currentNextObjects(
    $schemas ?? $schemas64(),
    $tables,
);

$value64 = static function (array $data, string $path): mixed {
    $cursor = $data;
    $parts = explode('.', $path);
    while ($parts !== []) {
        if (is_array($cursor)) {
            for ($length = count($parts); $length > 0; --$length) {
                $candidate = implode('.', array_slice($parts, 0, $length));
                if (array_key_exists($candidate, $cursor)) {
                    $cursor = $cursor[$candidate];
                    $parts = array_slice($parts, $length);
                    continue 2;
                }
            }
        }

        $part = array_shift($parts);
        $cursor = is_numeric($part) ? $cursor[(int) $part] : $cursor[$part];
    }

    return $cursor;
};

$pathCases64 = [
    'status ok' => ['status', 'ok'],
    'operation object cache' => ['operation', 'attach-temp-main-wal-schema-cache-objects'],
    'source main' => ['source', 'main'],
    'search order temp main archive network' => ['search_order', ['temp', 'main', 'archive', 'network']],
    'current temp cookie' => ['schema_cookies_current.temp', 4],
    'next temp cookie' => ['schema_cookies_next.temp', 4],
    'current main cookie' => ['schema_cookies_current.main', 30],
    'next main cookie' => ['schema_cookies_next.main', 31],
    'current archive cookie' => ['schema_cookies_current.archive', 8],
    'next archive cookie from wal frame' => ['schema_cookies_next.archive', 9],
    'changed schemas main archive' => ['changed_schemas', ['main', 'archive']],
    'object changed schemas temp main archive network' => ['object_changed_schemas', ['temp', 'main', 'archive', 'network']],
    'requires reprepare true' => ['requires_reprepare', true],
    'unqualified current schema temp' => ['prepared_tables.wp_options.current.schema', 'temp'],
    'unqualified current found' => ['prepared_tables.wp_options.current.found', true],
    'unqualified current shadows main' => ['prepared_tables.wp_options.current.shadowed_schemas', ['main']],
    'unqualified next schema archive after temp main drops' => ['prepared_tables.wp_options.next.schema', 'archive'],
    'unqualified next found' => ['prepared_tables.wp_options.next.found', true],
    'unqualified next shadows network' => ['prepared_tables.wp_options.next.shadowed_schemas', ['network']],
    'unqualified resolution changed' => ['prepared_tables.wp_options.resolution_changed', true],
    'unqualified requires reprepare' => ['prepared_tables.wp_options.requires_reprepare', true],
    'qualified main current found' => ['prepared_tables.main.wp_options.current.found', true],
    'qualified main next missing' => ['prepared_tables.main.wp_options.next.found', false],
    'qualified main schema stable' => ['prepared_tables.main.wp_options.next.schema', 'main'],
    'qualified main resolution changed' => ['prepared_tables.main.wp_options.resolution_changed', true],
    'qualified archive current missing' => ['prepared_tables.archive.wp_options.current.found', false],
    'qualified archive next found' => ['prepared_tables.archive.wp_options.next.found', true],
    'qualified archive next cookie' => ['prepared_tables.archive.wp_options.next.schema_cookie', 9],
    'qualified archive resolution changed' => ['prepared_tables.archive.wp_options.resolution_changed', true],
    'qualified network current missing' => ['prepared_tables.network.wp_options.current.found', false],
    'qualified network next found' => ['prepared_tables.network.wp_options.next.found', true],
    'qualified network requires reprepare' => ['prepared_tables.network.wp_options.requires_reprepare', true],
    'wp blogs current network' => ['prepared_tables.wp_blogs.current.schema', 'network'],
    'wp blogs next network' => ['prepared_tables.wp_blogs.next.schema', 'network'],
    'wp blogs resolution unchanged' => ['prepared_tables.wp_blogs.resolution_changed', false],
    'wp blogs still reprepare because network objects changed' => ['prepared_tables.wp_blogs.requires_reprepare', true],
    'database list temp next cookie' => ['database_list.0.next_schema_cookie', 4],
    'database list main file' => ['database_list.1.file', '/srv/wp/current.sqlite'],
    'database list archive cache' => ['database_list.2.cache', 'shared'],
    'dependency marker' => ['dependencies.0', 'sqlite-attach-temp-main-wal-schema-cache'],
    'dependency wal ddl' => ['dependencies.1', 'sqlite-wal-ddl-object-resolution'],
];

foreach ($pathCases64 as $name => [$path, $expected]) {
    $tests['attach temp main wal schema cache plan ' . $name] = static function (TestRunner $t) use ($plan64, $value64, $path, $expected): void {
        $t->same($expected, $value64($plan64(), $path));
    };
}

$predicateCases64 = [
    'uncommitted wal cookie does not change archive cookie' => static function () use ($schemas64, $plan64): bool {
        $schemas = $schemas64();
        $schemas['archive']['wal_frames'][0]['commit'] = false;
        return $plan64($schemas)['schema_cookies_next']['archive'] === 8;
    },
    'explicit wal schema cookie wins over committed frame' => static function () use ($schemas64, $plan64): bool {
        $schemas = $schemas64();
        $schemas['archive']['wal_schema_cookie'] = 10;
        return $plan64($schemas)['schema_cookies_next']['archive'] === 10;
    },
    'unchanged next table lists avoid object changed schemas' => static function () use ($schemas64, $plan64): bool {
        $schemas = $schemas64();
        foreach ($schemas as $name => $schema) {
            $schemas[$name]['next_tables'] = $schema['tables'];
            $schemas[$name]['next_indexes'] = $schema['indexes'];
        }
        return $plan64($schemas)['object_changed_schemas'] === [];
    },
    'unchanged cookies and objects avoid global reprepare' => static function () use ($schemas64, $plan64): bool {
        $schemas = $schemas64();
        foreach ($schemas as $name => $schema) {
            $schemas[$name]['next_tables'] = $schema['tables'];
            $schemas[$name]['next_indexes'] = $schema['indexes'];
            unset($schemas[$name]['wal_schema_cookie'], $schemas[$name]['wal_frames']);
        }
        return $plan64($schemas, ['wp_options'])['requires_reprepare'] === false;
    },
    'next temp create shadows current main' => static function () use ($schemas64, $plan64): bool {
        $schemas = $schemas64();
        $schemas['temp']['tables'] = [];
        $schemas['temp']['next_tables'] = ['wp_options'];
        $schemas['main']['next_tables'] = ['wp_options', 'wp_posts'];
        return $plan64($schemas, ['wp_options'])['prepared_tables']['wp_options']['next']['schema'] === 'temp';
    },
    'current missing unqualified falls to main' => static function () use ($schemas64, $plan64): bool {
        $schemas = $schemas64();
        $schemas['temp']['tables'] = [];
        $schemas['main']['tables'] = ['wp_options'];
        return $plan64($schemas, ['wp_options'])['prepared_tables']['wp_options']['current']['schema'] === 'main';
    },
    'next missing all returns main not found' => static function () use ($schemas64, $plan64): bool {
        $schemas = $schemas64();
        foreach (['temp', 'main', 'archive', 'network'] as $name) {
            $schemas[$name]['next_tables'] = [];
        }
        $next = $plan64($schemas, ['wp_missing'])['prepared_tables']['wp_missing']['next'];
        return $next['schema'] === 'main' && $next['found'] === false;
    },
    'case insensitive qualified next lookup' => static function () use ($schemas64, $plan64): bool {
        return $plan64($schemas64(), ['ARCHIVE.WP_OPTIONS'])['prepared_tables']['ARCHIVE.WP_OPTIONS']['next']['found'] === true;
    },
    'quoted source schema is accepted' => static function () use ($schemas64): bool {
        return SQLiteAttachTempMainWalSchemaCachePlan::currentNextObjects($schemas64(), ['archive.wp_options'], '"main"')['source'] === 'main';
    },
    'missing source schema is rejected' => static function () use ($schemas64): bool {
        try {
            SQLiteAttachTempMainWalSchemaCachePlan::currentNextObjects($schemas64(), ['wp_options'], 'missing');
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    },
    'empty table list keeps schema object decisions' => static function () use ($schemas64): bool {
        $result = SQLiteAttachTempMainWalSchemaCachePlan::currentNextObjects($schemas64(), []);
        return $result['prepared_tables'] === [] && $result['object_changed_schemas'] === ['temp', 'main', 'archive', 'network'];
    },
    'missing temp main defaults still let archive next resolve' => static function (): bool {
        $result = SQLiteAttachTempMainWalSchemaCachePlan::currentNextObjects([
            'archive' => [
                'schema_cookie' => 1,
                'tables' => [],
                'next_tables' => ['wp_options'],
            ],
        ], ['wp_options']);
        return $result['prepared_tables']['wp_options']['next']['schema'] === 'archive';
    },
    'next index-only change forces reprepare without resolution change' => static function () use ($schemas64, $plan64): bool {
        $schemas = $schemas64();
        $schemas['network']['next_tables'] = $schemas['network']['tables'];
        $schemas['network']['next_indexes'] = ['wp_blogs_domain'];
        $result = $plan64($schemas, ['network.wp_blogs'])['prepared_tables']['network.wp_blogs'];
        return $result['resolution_changed'] === false && $result['requires_reprepare'] === true;
    },
    'current shadow list follows current objects only' => static function () use ($schemas64, $plan64): bool {
        $schemas = $schemas64();
        $schemas['archive']['tables'][] = 'wp_options';
        return $plan64($schemas, ['wp_options'])['prepared_tables']['wp_options']['current']['shadowed_schemas'] === ['main', 'archive'];
    },
    'next shadow list follows next objects only' => static function () use ($schemas64, $plan64): bool {
        $schemas = $schemas64();
        $schemas['temp']['next_tables'] = ['wp_options'];
        return $plan64($schemas, ['wp_options'])['prepared_tables']['wp_options']['next']['shadowed_schemas'] === ['archive', 'network'];
    },
];

foreach ($predicateCases64 as $name => $callback) {
    $tests['attach temp main wal schema cache plan ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->true($callback());
    };
}

return $tests;
