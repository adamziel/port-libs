<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSql;

$rows70 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'hits' => 5, 'touched' => 'old'],
    ['option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'hits' => 2, 'touched' => 'old'],
    ['option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'hits' => 7, 'touched' => 'old'],
    ['option_name' => 'theme_mods', 'option_value' => null, 'autoload' => 'no', 'hits' => 1, 'touched' => 'old'],
];

$sql70 = static function (string $values, string $returning, ?string $where = '1'): string {
    return 'INSERT INTO wp_options(option_name, option_value, autoload, hits, touched) VALUES '
        . $values
        . ' ON CONFLICT(option_name) DO UPDATE SET '
        . 'option_value = excluded.option_value, '
        . 'autoload = coalesce(excluded.autoload, wp_options.autoload), '
        . 'hits = wp_options.hits + excluded.hits, '
        . 'touched = wp_options.touched || ' . "'>'" . ' || excluded.touched'
        . ($where === null ? '' : ' WHERE ' . $where)
        . ' RETURNING ' . $returning;
};

$run70 = static fn (string $values, string $returning, ?string $where = '1'): array => SQLiteUpsertReturningSql::execute(
    $sql70($values, $returning, $where),
    ['wp_options' => $rows70],
    [['option_name']],
);

$tests = [
    'upsert returning expression current next70 update arithmetic expression' => static fn (TestRunner $t) => $t->same(
        [['next_hits' => 9]],
        $run70("('siteurl','https://new.test','yes',3,'u1')", 'hits + 1 AS next_hits')['returning'],
    ),
    'upsert returning expression current next70 update subtraction expression' => static fn (TestRunner $t) => $t->same(
        [['delta' => 6]],
        $run70("('siteurl','https://new.test','yes',3,'u2')", 'hits - 2 AS delta')['returning'],
    ),
    'upsert returning expression current next70 update concatenates final row' => static fn (TestRunner $t) => $t->same(
        [['label' => 'siteurl:old>u3']],
        $run70("('siteurl','https://new.test','yes',3,'u3')", "option_name || ':' || touched AS label")['returning'],
    ),
    'upsert returning expression current next70 update coalesce sees final value' => static fn (TestRunner $t) => $t->same(
        [['value' => 'https://new.test']],
        $run70("('siteurl','https://new.test','yes',3,'u4')", "coalesce(option_value, 'fallback') AS value")['returning'],
    ),
    'upsert returning expression current next70 update target-qualified expression' => static fn (TestRunner $t) => $t->same(
        [['label' => 'siteurl=yes']],
        $run70("('siteurl','https://new.test','yes',3,'u5')", "wp_options.option_name || '=' || wp_options.autoload AS label")['returning'],
    ),
    'upsert returning expression current next70 update expression after column alias' => static fn (TestRunner $t) => $t->same(
        [['name' => 'siteurl', 'score' => 18]],
        $run70("('siteurl','https://new.test','yes',4,'u6')", 'option_name AS name, hits + 9 AS score')['returning'],
    ),
    'upsert returning expression current next70 update expression before wildcard' => static fn (TestRunner $t) => $t->same(
        16,
        $run70("('siteurl','https://new.test','yes',3,'u7')", 'hits + hits AS doubled, *')['returning'][0]['doubled'],
    ),
    'upsert returning expression current next70 wildcard still includes final update column' => static fn (TestRunner $t) => $t->same(
        'old>u7',
        $run70("('siteurl','https://new.test','yes',3,'u7')", 'hits + hits AS doubled, *')['returning'][0]['touched'],
    ),
    'upsert returning expression current next70 insert arithmetic expression' => static fn (TestRunner $t) => $t->same(
        [['next_hits' => 3]],
        $run70("('new_plugin','enabled','no',2,'i1')", 'hits + 1 AS next_hits')['returning'],
    ),
    'upsert returning expression current next70 insert concatenates inserted row' => static fn (TestRunner $t) => $t->same(
        [['label' => 'new_plugin:no']],
        $run70("('new_plugin','enabled','no',2,'i2')", "option_name || ':' || autoload AS label")['returning'],
    ),
    'upsert returning expression current next70 insert coalesce expression' => static fn (TestRunner $t) => $t->same(
        [['value' => 'fallback']],
        $run70("('new_plugin',NULL,'no',2,'i3')", "coalesce(option_value, 'fallback') AS value")['returning'],
    ),
    'upsert returning expression current next70 insert target-qualified column expression' => static fn (TestRunner $t) => $t->same(
        [['name' => 'new_plugin']],
        $run70("('new_plugin','enabled','no',2,'i4')", 'wp_options.option_name AS name')['returning'],
    ),
    'upsert returning expression current next70 skipped conflict emits no expression row' => static fn (TestRunner $t) => $t->same(
        [],
        $run70("('home','skip','yes',9,'skip')", 'hits + 1 AS next_hits', 'wp_options.hits > 9')['returning'],
    ),
    'upsert returning expression current next70 skipped conflict keeps changes zero' => static fn (TestRunner $t) => $t->same(
        0,
        $run70("('home','skip','yes',9,'skip')", 'hits + 1 AS next_hits', 'wp_options.hits > 9')['changes'],
    ),
    'upsert returning expression current next70 where uses current row before returning final row' => static fn (TestRunner $t) => $t->same(
        [['score' => 15]],
        $run70("('blogname','New Blog','yes',8,'u8')", 'hits AS score', 'wp_options.hits = 7')['returning'],
    ),
    'upsert returning expression current next70 where uses excluded row before returning final row' => static fn (TestRunner $t) => $t->same(
        [['score' => 15]],
        $run70("('blogname','New Blog','yes',8,'u9')", 'hits AS score', 'excluded.hits = 8')['returning'],
    ),
    'upsert returning expression current next70 repeated conflict returns first final expression' => static fn (TestRunner $t) => $t->same(
        [6, 10],
        array_column($run70("('home','h1','yes',4,'u10a'),('home','h2','yes',4,'u10b')", 'hits AS score, touched AS mark', 'wp_options.hits < 10')['returning'], 'score'),
    ),
    'upsert returning expression current next70 repeated conflict sees updated touched current' => static fn (TestRunner $t) => $t->same(
        ['old>u10a', 'old>u10a>u10b'],
        array_column($run70("('home','h1','yes',4,'u10a'),('home','h2','yes',4,'u10b')", 'hits AS score, touched AS mark', 'wp_options.hits < 10')['returning'], 'mark'),
    ),
    'upsert returning expression current next70 inserted then update expression rows' => static fn (TestRunner $t) => $t->same(
        ['transient:no:1', 'transient:yes:4'],
        array_column($run70("('transient','one','no',1,'i5'),('transient','two','yes',3,'u11')", "option_name || ':' || autoload || ':' || hits AS label")['returning'], 'label'),
    ),
    'upsert returning expression current next70 mixed skip preserves only changed expression rows' => static fn (TestRunner $t) => $t->same(
        ['siteurl:6', 'new_plugin:2'],
        array_column($run70("('siteurl','site','yes',1,'u12'),('home','skip','yes',1,'skip'),('new_plugin','enabled','no',2,'i6')", "option_name || ':' || hits AS label", 'wp_options.hits >= 5')['returning'], 'label'),
    ),
    'upsert returning expression current next70 null arithmetic returns null' => static fn (TestRunner $t) => $t->same(
        [['score' => null]],
        $run70("('new_plugin','enabled','no',NULL,'i7')", 'hits + 1 AS score')['returning'],
    ),
    'upsert returning expression current next70 null concatenation returns null' => static fn (TestRunner $t) => $t->same(
        [['label' => null]],
        $run70("('new_plugin',NULL,'no',2,'i8')", "option_value || ':x' AS label")['returning'],
    ),
    'upsert returning expression current next70 coalesce after null update fallback' => static fn (TestRunner $t) => $t->same(
        [['value' => 'fallback']],
        $run70("('theme_mods',NULL,'no',2,'u13')", "coalesce(option_value, 'fallback') AS value")['returning'],
    ),
    'upsert returning expression current next70 returning expression can use parenthesized math' => static fn (TestRunner $t) => $t->same(
        [['score' => 19]],
        $run70("('siteurl','site','yes',4,'u14')", '(hits + 5) + 5 AS score')['returning'],
    ),
    'upsert returning expression current next70 returning expression can use parenthesized concat' => static fn (TestRunner $t) => $t->same(
        [['label' => 'siteurl:yes']],
        $run70("('siteurl','site','yes',4,'u15')", "(option_name || ':') || autoload AS label")['returning'],
    ),
    'upsert returning expression current next70 returning column alias after expression' => static fn (TestRunner $t) => $t->same(
        [['score' => 8, 'name' => 'siteurl']],
        $run70("('siteurl','site','yes',3,'u16')", 'hits AS score, option_name AS name')['returning'],
    ),
    'upsert returning expression current next70 returning expression preserves output order' => static fn (TestRunner $t) => $t->same(
        ['score', 'label', 'name'],
        array_keys($run70("('siteurl','site','yes',3,'u17')", "hits AS score, option_name || ':' || touched AS label, option_name AS name")['returning'][0]),
    ),
    'upsert returning expression current next70 returning expression preserves row order' => static fn (TestRunner $t) => $t->same(
        ['siteurl:6', 'blogname:8', 'new_plugin:1'],
        array_column($run70("('siteurl','site','yes',1,'u18'),('blogname','blog','no',1,'u18'),('new_plugin','enabled','no',1,'i18')", "option_name || ':' || hits AS label")['returning'], 'label'),
    ),
    'upsert returning expression current next70 parse keeps expression returning sql' => static fn (TestRunner $t) => $t->same(
        'hits + 1 AS next_hits',
        SQLiteUpsertReturningSql::parse($sql70("('x','v','yes',1,'n')", 'hits + 1 AS next_hits'))['returning'],
    ),
    'upsert returning expression current next70 parse keeps target qualified returning sql' => static fn (TestRunner $t) => $t->same(
        'wp_options.option_name AS name',
        SQLiteUpsertReturningSql::parse($sql70("('x','v','yes',1,'n')", 'wp_options.option_name AS name'))['returning'],
    ),
    'upsert returning expression current next70 rejects unaliased expression' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $run70("('siteurl','site','yes',3,'bad')", 'hits + 1'),
    ),
    'upsert returning expression current next70 rejects missing expression column' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $run70("('siteurl','site','yes',3,'bad')", 'missing + 1 AS bad'),
    ),
    'upsert returning expression current next70 rejects excluded expression reference' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $run70("('siteurl','site','yes',3,'bad')", 'excluded.hits + 1 AS bad'),
    ),
    'upsert returning expression current next70 rejects excluded column reference' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $run70("('siteurl','site','yes',3,'bad')", 'excluded.hits AS bad'),
    ),
    'upsert returning expression current next70 rejects expression without alias in mixed projection' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $run70("('siteurl','site','yes',3,'bad')", 'option_name, hits + 1'),
    ),
    'upsert returning expression current next70 rejects malformed alias' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $run70("('siteurl','site','yes',3,'bad')", 'hits + 1 AS bad-name'),
    ),
    'upsert returning expression current next70 rejects unsupported literal expression' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $run70("('siteurl','site','yes',3,'bad')", '1.5 AS bad'),
    ),
    'upsert returning expression current next70 rejects excluded inside coalesce' => static fn (TestRunner $t) => $t->throws(
        InvalidArgumentException::class,
        static fn () => $run70("('siteurl','site','yes',3,'bad')", "coalesce(excluded.option_value, option_value) AS bad"),
    ),
    'upsert returning expression current next70 expression does not change after rows' => static fn (TestRunner $t) => $t->same(
        8,
        $run70("('siteurl','site','yes',3,'u19')", 'hits + 100 AS preview')['after'][0]['hits'],
    ),
    'upsert returning expression current next70 expression result is absent from after rows' => static fn (TestRunner $t) => $t->same(
        false,
        array_key_exists('preview', $run70("('siteurl','site','yes',3,'u20')", 'hits + 100 AS preview')['after'][0]),
    ),
    'upsert returning expression current next70 update list remains final rows only' => static fn (TestRunner $t) => $t->same(
        [['option_name' => 'siteurl', 'option_value' => 'site', 'autoload' => 'yes', 'hits' => 8, 'touched' => 'old>u21']],
        $run70("('siteurl','site','yes',3,'u21')", 'hits + 1 AS preview')['updated_rows'],
    ),
    'upsert returning expression current next70 before rows remain unchanged' => static fn (TestRunner $t) => $t->same(
        5,
        $run70("('siteurl','site','yes',3,'u22')", 'hits + 1 AS preview')['before'][0]['hits'],
    ),
    'upsert returning expression current next70 unique null still inserts and evaluates expression' => static fn (TestRunner $t) => $t->same(
        [['label' => null]],
        $run70("(NULL,'anon','no',3,'i9')", "option_name || ':x' AS label")['returning'],
    ),
    'upsert returning expression current next70 unique null insert increments changes' => static fn (TestRunner $t) => $t->same(
        1,
        $run70("(NULL,'anon','no',3,'i10')", 'hits AS score')['changes'],
    ),
    'upsert returning expression current next70 expression with escaped string literal' => static fn (TestRunner $t) => $t->same(
        [['label' => "siteurl:Bob's"]],
        $run70("('siteurl','site','yes',3,'u23')", "option_name || ':Bob''s' AS label")['returning'],
    ),
    'upsert returning expression current next70 expression output can shadow column alias' => static fn (TestRunner $t) => $t->same(
        [['hits' => 9]],
        $run70("('siteurl','site','yes',3,'u24')", 'hits + 1 AS hits')['returning'],
    ),
    'upsert returning expression current next70 wildcard after shadow restores row column' => static fn (TestRunner $t) => $t->same(
        8,
        $run70("('siteurl','site','yes',3,'u25')", 'hits + 1 AS hits, *')['returning'][0]['hits'],
    ),
    'upsert returning expression current next70 wildcard before shadow lets expression overwrite' => static fn (TestRunner $t) => $t->same(
        9,
        $run70("('siteurl','site','yes',3,'u26')", '*, hits + 1 AS hits')['returning'][0]['hits'],
    ),
    'upsert returning expression current next70 multiple expression aliases evaluate same final row' => static fn (TestRunner $t) => $t->same(
        [['a' => 8, 'b' => 9, 'c' => 'siteurl:old>u27']],
        $run70("('siteurl','site','yes',3,'u27')", "hits AS a, hits + 1 AS b, option_name || ':' || touched AS c")['returning'],
    ),
    'upsert returning expression current next70 insert followed by skipped update keeps first expression' => static fn (TestRunner $t) => $t->same(
        [['score' => 1]],
        $run70("('runtime_cache','one','no',1,'i11'),('runtime_cache','two','no',2,'u28')", 'hits AS score', "excluded.autoload = 'yes'")['returning'],
    ),
    'upsert returning expression current next70 insert followed by skipped update records skipped row' => static fn (TestRunner $t) => $t->same(
        ['runtime_cache'],
        array_column($run70("('runtime_cache','one','no',1,'i12'),('runtime_cache','two','no',2,'u29')", 'hits AS score', "excluded.autoload = 'yes'")['skipped_rows'], 'option_name'),
    ),
    'upsert returning expression current next70 expression after absent where update' => static fn (TestRunner $t) => $t->same(
        [['score' => 10]],
        $run70("('blogname','Blog','yes',3,'u30')", 'hits AS score', null)['returning'],
    ),
    'upsert returning expression current next70 dependency marker scenario is local' => static fn (TestRunner $t) => $t->same(
        ['sqlite-upsert-returning-expression-current-next70'],
        ['sqlite-upsert-returning-expression-current-next70'],
    ),
];

return $tests;
