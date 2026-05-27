<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCreateIndex;

$tests = [];

$columnCases = [
    'parenthesized lower with outer collation' => [
        static fn (string $sql) => SQLiteCreateIndex::firstLowerExpression($sql),
        'CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE nocase)',
        ['option_name', 'NOCASE', false],
    ],
    'nested parenthesized lower with inner collation and outer desc' => [
        static fn (string $sql) => SQLiteCreateIndex::firstLowerExpression($sql),
        'CREATE INDEX idx ON wp_options(((lower(main.wp_options."option_name")) COLLATE rtrim) DESC)',
        ['option_name', 'RTRIM', true],
    ],
    'parenthesized upper with collation after expression wrapper' => [
        static fn (string $sql) => SQLiteCreateIndex::firstUpperExpression($sql),
        'CREATE INDEX idx ON wp_options((upper(option_name)) COLLATE nocase DESC)',
        ['option_name', 'NOCASE', true],
    ],
    'parenthesized upper with direction outside wrapper' => [
        static fn (string $sql) => SQLiteCreateIndex::firstUpperExpression($sql),
        'CREATE INDEX idx ON wp_options(((upper(option_name)) COLLATE binary) ASC)',
        ['option_name', 'BINARY', false],
    ],
    'parenthesized length with direction outside wrapper' => [
        static fn (string $sql) => SQLiteCreateIndex::firstLengthExpression($sql),
        'CREATE INDEX idx ON wp_options((length(option_name)) DESC)',
        ['option_name', 'BINARY', true],
    ],
    'parenthesized length with explicit rtrim collation' => [
        static fn (string $sql) => SQLiteCreateIndex::firstLengthExpression($sql),
        'CREATE INDEX idx ON wp_options(((length(main.wp_options.option_name)) COLLATE rtrim))',
        ['option_name', 'RTRIM', false],
    ],
    'parenthesized integer cast with outer collation and desc' => [
        static fn (string $sql) => SQLiteCreateIndex::firstIntegerCastExpression($sql),
        'CREATE INDEX idx ON wp_options((CAST(option_value AS INTEGER)) COLLATE binary DESC)',
        ['option_value', 'BINARY', true],
    ],
    'parenthesized integer cast with nested wrapper direction' => [
        static fn (string $sql) => SQLiteCreateIndex::firstIntegerCastExpression($sql),
        'CREATE INDEX idx ON wp_options(((CAST(main.wp_options."option_value" AS INTEGER)) DESC))',
        ['option_value', 'BINARY', true],
    ],
];

foreach ($columnCases as $name => [$parser, $sql, $expected]) {
    $tests['create index expression collation corpus ' . $name] = static function (TestRunner $t) use ($parser, $sql, $expected): void {
        $column = $parser($sql);
        $t->same($expected[0], $column?->columnName);
        $t->same($expected[1], $column?->collation);
        $t->same($expected[2], $column?->descending);
    };
}

$trimCases = [
    'parenthesized trim with characters and collation' => [
        "CREATE INDEX idx ON wp_options((trim(option_name, ' _')) COLLATE nocase DESC)",
        ['trim', 'option_name', ' _', 'NOCASE', true],
    ],
    'parenthesized ltrim with direction outside wrapper' => [
        "CREATE INDEX idx ON wp_options(((ltrim(option_name, '-')) COLLATE rtrim) DESC)",
        ['ltrim', 'option_name', '-', 'RTRIM', true],
    ],
    'parenthesized rtrim with default modifiers' => [
        'CREATE INDEX idx ON wp_options((rtrim(main.wp_options.option_name)))',
        ['rtrim', 'option_name', null, 'BINARY', false],
    ],
];

foreach ($trimCases as $name => [$sql, $expected]) {
    $tests['create index expression collation corpus ' . $name] = static function (TestRunner $t) use ($sql, $expected): void {
        $expression = SQLiteCreateIndex::firstTrimExpression($sql);
        $t->same($expected[0], $expression?->functionName);
        $t->same($expected[1], $expression?->columnName);
        $t->same($expected[2], $expression?->characters);
        $t->same($expected[3], $expression?->collation);
        $t->same($expected[4], $expression?->descending);
    };
}

$substringCases = [
    'parenthesized substr with collation and desc' => [
        'CREATE INDEX idx ON wp_options((substr(option_name, 1, 11)) COLLATE nocase DESC)',
        ['option_name', 1, 11, 'NOCASE', true],
    ],
    'parenthesized substring with suffix direction' => [
        'CREATE INDEX idx ON wp_options(((substring(main.wp_options.option_name, -9)) COLLATE rtrim) ASC)',
        ['option_name', -9, null, 'RTRIM', false],
    ],
    'parenthesized substr without collation keeps binary' => [
        'CREATE INDEX idx ON wp_options((substr(option_name, 2)))',
        ['option_name', 2, null, 'BINARY', false],
    ],
];

foreach ($substringCases as $name => [$sql, $expected]) {
    $tests['create index expression collation corpus ' . $name] = static function (TestRunner $t) use ($sql, $expected): void {
        $expression = SQLiteCreateIndex::firstSubstringExpression($sql);
        $t->same($expected[0], $expression?->columnName);
        $t->same($expected[1], $expression?->start);
        $t->same($expected[2], $expression?->length);
        $t->same($expected[3], $expression?->collation);
        $t->same($expected[4], $expression?->descending);
    };
}

$jsonCases = [
    'parenthesized json extract with collation' => [
        static fn (string $sql) => SQLiteCreateIndex::firstJsonExtractExpression($sql),
        "CREATE INDEX idx ON wp_options((json_extract(option_value, '$.enabled')) COLLATE nocase DESC)",
        ['option_value', '$.enabled', 'NOCASE', true],
    ],
    'nested parenthesized json extract path' => [
        static fn (string $sql) => SQLiteCreateIndex::firstJsonExtractExpression($sql),
        'CREATE INDEX idx ON wp_options(((json_extract(main.wp_options.option_value, \'$."plugin.enabled"\')) COLLATE rtrim))',
        ['option_value', '$."plugin.enabled"', 'RTRIM', false],
    ],
    'parenthesized json text operator with collation' => [
        static fn (string $sql) => SQLiteCreateIndex::firstJsonTextOperatorExpression($sql),
        "CREATE INDEX idx ON wp_options((option_value ->> 'cache') COLLATE nocase DESC)",
        ['option_value', '$.cache', 'NOCASE', true],
    ],
    'parenthesized json text operator integer path' => [
        static fn (string $sql) => SQLiteCreateIndex::firstJsonTextOperatorExpression($sql),
        'CREATE INDEX idx ON wp_options(((option_value ->> (0)) COLLATE binary))',
        ['option_value', '$[0]', 'BINARY', false],
    ],
    'parenthesized json value operator with collation' => [
        static fn (string $sql) => SQLiteCreateIndex::firstJsonValueOperatorExpression($sql),
        "CREATE INDEX idx ON wp_options((option_value -> 'settings.v1') COLLATE rtrim DESC)",
        ['option_value', '$."settings.v1"', 'RTRIM', true],
    ],
    'parenthesized json value operator integer path' => [
        static fn (string $sql) => SQLiteCreateIndex::firstJsonValueOperatorExpression($sql),
        'CREATE INDEX idx ON wp_options(((main.wp_options.option_value -> (1)) COLLATE nocase) ASC)',
        ['option_value', '$[1]', 'NOCASE', false],
    ],
];

foreach ($jsonCases as $name => [$parser, $sql, $expected]) {
    $tests['create index expression collation corpus ' . $name] = static function (TestRunner $t) use ($parser, $sql, $expected): void {
        $expression = $parser($sql);
        $t->same($expected[0], $expression?->columnName);
        $t->same($expected[1], $expression?->path);
        $t->same($expected[2], $expression?->collation);
        $t->same($expected[3], $expression?->descending);
    };
}

$rejectionCases = [
    'keeps ordinary column parser from accepting parenthesized expression' => static fn () => SQLiteCreateIndex::firstColumn('CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE nocase)'),
    'rejects parenthesized constant lower expression' => static fn () => SQLiteCreateIndex::firstLowerExpression("CREATE INDEX idx ON wp_options((lower('option_name')) COLLATE nocase)"),
    'rejects parenthesized non integer cast type' => static fn () => SQLiteCreateIndex::firstIntegerCastExpression('CREATE INDEX idx ON wp_options((CAST(option_value AS TEXT)) COLLATE nocase)'),
    'rejects parenthesized malformed json path' => static fn () => SQLiteCreateIndex::firstJsonExtractExpression("CREATE INDEX idx ON wp_options((json_extract(option_value, '$.')) COLLATE nocase)"),
    'rejects parenthesized json operator arithmetic path' => static fn () => SQLiteCreateIndex::firstJsonTextOperatorExpression('CREATE INDEX idx ON wp_options((option_value ->> (1 + 1)) COLLATE nocase)'),
    'rejects missing collation name after wrapper' => static fn () => SQLiteCreateIndex::firstLowerExpression('CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE)'),
    'rejects unsupported modifier after wrapper' => static fn () => SQLiteCreateIndex::firstUpperExpression('CREATE INDEX idx ON wp_options((upper(option_name)) NULLS FIRST)'),
    'rejects unbalanced expression wrapper' => static fn () => SQLiteCreateIndex::firstLengthExpression('CREATE INDEX idx ON wp_options((length(option_name) COLLATE nocase)'),
];

foreach ($rejectionCases as $name => $parser) {
    $tests['create index expression collation corpus ' . $name] = static function (TestRunner $t) use ($parser): void {
        $t->same(null, $parser());
    };
}

$tests['create index expression collation corpus preserves partial predicate with parenthesized expression'] = static function (TestRunner $t): void {
    $column = SQLiteCreateIndex::firstLowerExpression("CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE nocase) WHERE option_name IS NOT NULL AND autoload = 'yes'");
    $t->same('option_name', $column?->columnName);
    $t->same('NOCASE', $column?->collation);
    $t->same(true, $column?->partial);
    $t->same('AND', $column?->partialPredicate?->operator);
};

$tests['create index expression collation corpus parses second indexed term after parenthesized expression'] = static function (TestRunner $t): void {
    $columns = SQLiteCreateIndex::columns('CREATE INDEX idx ON wp_options((lower(option_name)) COLLATE nocase, autoload COLLATE rtrim DESC)');
    $t->same(null, $columns);
    $autoloadOnly = SQLiteCreateIndex::columns('CREATE INDEX idx ON wp_options(autoload COLLATE rtrim DESC, option_name)');
    $t->same('autoload', $autoloadOnly[0]?->columnName);
    $t->same('RTRIM', $autoloadOnly[0]?->collation);
    $t->same(true, $autoloadOnly[0]?->descending);
};

return $tests;
