<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'bytes' => 20, 'maybe' => null],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'bytes' => 20, 'maybe' => ''],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'option_value' => 'Example Site', 'bytes' => 12, 'maybe' => 'title'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'option_value' => 'cached', 'bytes' => 12, 'maybe' => 'feed'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'option_value' => 'cached', 'bytes' => 12, 'maybe' => null],
    ['option_id' => 6, 'option_name' => 'plugin_cache_key', 'autoload' => 'no', 'option_value' => ' enabled ', 'bytes' => 9, 'maybe' => 'cache'],
];

$run = static fn (string $sql, array $parameters = []): array => SQLiteSelectSql::execute(
    $sql,
    ['wp_options' => $rows],
    $parameters,
);

$normalize = static function (mixed $value): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return ['blob' => bin2hex($value->bytes)];
    }
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $item instanceof SQLiteBlobValue ? ['blob' => bin2hex($item->bytes)] : $item;
            if (is_array($item)) {
                $normalized[$key] = array_map(static function (mixed $nested): mixed {
                    return $nested instanceof SQLiteBlobValue ? ['blob' => bin2hex($nested->bytes)] : $nested;
                }, $item);
            }
        }

        return $normalized;
    }

    return $value;
};

$cases = [
    'coalesce projection fallback' => [
        'SELECT option_id, coalesce(maybe, option_name) AS label FROM wp_options ORDER BY option_id LIMIT 3',
        [['option_id' => 1, 'label' => 'siteurl'], ['option_id' => 2, 'label' => ''], ['option_id' => 3, 'label' => 'title']],
    ],
    'coalesce where fallback literal' => [
        'SELECT option_name FROM wp_options WHERE coalesce(maybe, \'missing\') = \'missing\' ORDER BY option_id',
        [['option_name' => 'siteurl'], ['option_name' => '_site_transient_update_plugins']],
    ],
    'ifnull projection fallback' => [
        'SELECT option_id, ifnull(maybe, autoload) AS label FROM wp_options ORDER BY option_id LIMIT 2',
        [['option_id' => 1, 'label' => 'yes'], ['option_id' => 2, 'label' => '']],
    ],
    'nullif projection equal' => [
        'SELECT option_id, nullif(autoload, \'yes\') AS write_flag FROM wp_options ORDER BY option_id LIMIT 4',
        [['option_id' => 1, 'write_flag' => null], ['option_id' => 2, 'write_flag' => null], ['option_id' => 3, 'write_flag' => null], ['option_id' => 4, 'write_flag' => 'no']],
    ],
    'nullif where nonmatch' => [
        'SELECT option_name FROM wp_options WHERE nullif(autoload, \'yes\') = \'no\' ORDER BY option_id',
        [['option_name' => '_transient_feed'], ['option_name' => '_site_transient_update_plugins'], ['option_name' => 'plugin_cache_key']],
    ],
    'typeof projection mixed storage classes' => [
        'SELECT option_id, typeof(maybe) AS kind FROM wp_options ORDER BY option_id LIMIT 3',
        [['option_id' => 1, 'kind' => 'null'], ['option_id' => 2, 'kind' => 'text'], ['option_id' => 3, 'kind' => 'text']],
    ],
    'typeof where null values' => [
        'SELECT option_name FROM wp_options WHERE typeof(maybe) = \'null\' ORDER BY option_id',
        [['option_name' => 'siteurl'], ['option_name' => '_site_transient_update_plugins']],
    ],
    'quote projection text escaping' => [
        'SELECT quote(option_name) AS q FROM wp_options WHERE option_id = 1',
        [['q' => "'siteurl'"]],
    ],
    'quote projection null' => [
        'SELECT quote(maybe) AS q FROM wp_options WHERE option_id = 1',
        [['q' => 'NULL']],
    ],
    'lower projection' => [
        'SELECT lower(option_value) AS value FROM wp_options WHERE option_id = 3',
        [['value' => 'example site']],
    ],
    'lower where case fold' => [
        'SELECT option_id FROM wp_options WHERE lower(option_value) = \'example site\'',
        [['option_id' => 3]],
    ],
    'upper projection' => [
        'SELECT upper(autoload) AS flag FROM wp_options WHERE option_id = 4',
        [['flag' => 'NO']],
    ],
    'upper order expression' => [
        'SELECT option_name FROM wp_options ORDER BY upper(maybe) LIMIT 3',
        [['option_name' => 'siteurl'], ['option_name' => '_site_transient_update_plugins'], ['option_name' => 'home']],
    ],
    'length projection text' => [
        'SELECT option_id, length(option_name) AS n FROM wp_options ORDER BY option_id LIMIT 2',
        [['option_id' => 1, 'n' => 7], ['option_id' => 2, 'n' => 4]],
    ],
    'length where threshold' => [
        'SELECT option_name FROM wp_options WHERE length(option_name) > 20 ORDER BY option_id',
        [['option_name' => '_site_transient_update_plugins']],
    ],
    'octet length projection' => [
        'SELECT option_name, octet_length(option_name) AS n FROM wp_options WHERE option_id = 6',
        [['option_name' => 'plugin_cache_key', 'n' => 16]],
    ],
    'substr positive start' => [
        'SELECT substr(option_name, 1, 4) AS prefix FROM wp_options WHERE option_id = 1',
        [['prefix' => 'site']],
    ],
    'substr negative start' => [
        'SELECT substr(option_name, -3, 3) AS suffix FROM wp_options WHERE option_id = 6',
        [['suffix' => 'key']],
    ],
    'substr where suffix' => [
        'SELECT option_name FROM wp_options WHERE substr(option_name, -4, 4) = \'feed\'',
        [['option_name' => '_transient_feed']],
    ],
    'trim projection default spaces' => [
        'SELECT trim(option_value) AS value FROM wp_options WHERE option_id = 6',
        [['value' => 'enabled']],
    ],
    'ltrim projection custom' => [
        'SELECT ltrim(option_name, \'_\') AS value FROM wp_options WHERE option_id = 4',
        [['value' => 'transient_feed']],
    ],
    'rtrim projection custom' => [
        'SELECT rtrim(option_name, \'key\') AS value FROM wp_options WHERE option_id = 6',
        [['value' => 'plugin_cache_']],
    ],
    'replace projection' => [
        'SELECT replace(option_name, \'_\', \'-\') AS value FROM wp_options WHERE option_id = 6',
        [['value' => 'plugin-cache-key']],
    ],
    'replace where generated value' => [
        'SELECT option_id FROM wp_options WHERE replace(autoload, \'yes\', \'auto\') = \'auto\' ORDER BY option_id LIMIT 2',
        [['option_id' => 1], ['option_id' => 2]],
    ],
    'instr projection hit' => [
        'SELECT instr(option_name, \'transient\') AS pos FROM wp_options WHERE option_id = 4',
        [['pos' => 2]],
    ],
    'instr projection miss' => [
        'SELECT instr(option_name, \'missing\') AS pos FROM wp_options WHERE option_id = 1',
        [['pos' => 0]],
    ],
    'instr where hit' => [
        'SELECT option_name FROM wp_options WHERE instr(option_name, \'plugin\') > 0 ORDER BY option_id',
        [['option_name' => '_site_transient_update_plugins'], ['option_name' => 'plugin_cache_key']],
    ],
    'concat projection skips null' => [
        'SELECT concat(option_name, \':\', maybe) AS label FROM wp_options WHERE option_id = 1',
        [['label' => 'siteurl:']],
    ],
    'concat projection mixed values' => [
        'SELECT concat(option_name, \':\', bytes) AS label FROM wp_options WHERE option_id = 6',
        [['label' => 'plugin_cache_key:9']],
    ],
    'concat ws projection skips null' => [
        'SELECT concat_ws(\':\', option_name, maybe, autoload) AS label FROM wp_options WHERE option_id = 1',
        [['label' => 'siteurl:yes']],
    ],
    'concat ws null separator' => [
        'SELECT concat_ws(maybe, option_name, autoload) AS label FROM wp_options WHERE option_id = 1',
        [['label' => null]],
    ],
    'hex projection text' => [
        'SELECT hex(autoload) AS value FROM wp_options WHERE option_id = 4',
        [['value' => '6E6F']],
    ],
    'unhex projection blob' => [
        'SELECT unhex(\'4142\') AS value FROM wp_options WHERE option_id = 1',
        [['value' => new SQLiteBlobValue('AB')]],
    ],
    'unhex invalid projection' => [
        'SELECT unhex(\'414\') AS value FROM wp_options WHERE option_id = 1',
        [['value' => null]],
    ],
    'zeroblob projection' => [
        'SELECT zeroblob(3) AS value FROM wp_options WHERE option_id = 1',
        [['value' => new SQLiteBlobValue("\0\0\0")]],
    ],
    'char projection' => [
        'SELECT char(87, 80) AS value FROM wp_options WHERE option_id = 1',
        [['value' => 'WP']],
    ],
    'unicode projection' => [
        'SELECT unicode(option_name) AS value FROM wp_options WHERE option_id = 1',
        [['value' => 115]],
    ],
    'unicode empty string' => [
        'SELECT unicode(maybe) AS value FROM wp_options WHERE option_id = 2',
        [['value' => null]],
    ],
    'min scalar projection' => [
        'SELECT min(bytes, 10) AS value FROM wp_options WHERE option_id = 1',
        [['value' => 10]],
    ],
    'max scalar projection' => [
        'SELECT max(bytes, 10) AS value FROM wp_options WHERE option_id = 3',
        [['value' => 12]],
    ],
    'min scalar null propagation' => [
        'SELECT min(bytes, maybe) AS value FROM wp_options WHERE option_id = 1',
        [['value' => null]],
    ],
    'iif true projection' => [
        'SELECT iif(bytes, option_name, \'manual\') AS label FROM wp_options WHERE option_id = 1',
        [['label' => 'siteurl']],
    ],
    'iif false projection' => [
        'SELECT iif(maybe, option_name, \'manual\') AS label FROM wp_options WHERE option_id = 1',
        [['label' => 'manual']],
    ],
    'iif without else' => [
        'SELECT iif(maybe, option_name) AS label FROM wp_options WHERE option_id = 1',
        [['label' => null]],
    ],
    'coalesce grouped projection' => [
        'SELECT autoload, sum(bytes) AS total, coalesce(autoload, \'unset\') AS flag FROM wp_options GROUP BY autoload ORDER BY autoload',
        [['autoload' => 'no', 'total' => 33, 'flag' => 'no'], ['autoload' => 'yes', 'total' => 52, 'flag' => 'yes']],
    ],
    'function in having predicate' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING nullif(autoload, \'yes\') = \'no\'',
        [['autoload' => 'no', 'total' => 33]],
    ],
    'function in order by after grouping' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload ORDER BY upper(autoload) DESC',
        [['autoload' => 'yes', 'total' => 52], ['autoload' => 'no', 'total' => 33]],
    ],
    'bound coalesce where' => [
        'SELECT option_name FROM wp_options WHERE coalesce(maybe, :fallback) = :fallback ORDER BY option_id',
        [['option_name' => 'siteurl'], ['option_name' => '_site_transient_update_plugins']],
        [':fallback' => 'fallback'],
    ],
    'bound substr length' => [
        'SELECT substr(option_name, 1, ?1) AS prefix FROM wp_options WHERE option_id = 6',
        [['prefix' => 'plugin']],
        [1 => 6],
    ],
    'quote bound text' => [
        'SELECT quote(:name) AS q FROM wp_options WHERE option_id = 1',
        [['q' => "'canary''s'"]],
        [':name' => "canary's"],
    ],
    'nested scalar functions projection' => [
        'SELECT upper(substr(trim(option_value), 1, 3)) AS label FROM wp_options WHERE option_id = 6',
        [['label' => 'ENA']],
    ],
    'nested scalar functions where' => [
        'SELECT option_name FROM wp_options WHERE lower(substr(option_name, 1, 6)) = \'plugin\'',
        [['option_name' => 'plugin_cache_key']],
    ],
    'datetime fractional day modifier' => [
        'SELECT datetime(\'2024-01-01 00:00:00\', \'+0.5 days\') AS value',
        [['value' => '2024-01-01 12:00:00']],
    ],
    'datetime fractional hour modifier' => [
        'SELECT datetime(\'2024-01-01 00:00:00\', \'+1.25 hours\') AS value',
        [['value' => '2024-01-01 01:15:00']],
    ],
    'datetime fractional minute modifier' => [
        'SELECT datetime(\'2024-01-01 00:00:00\', \'+1.5 minutes\') AS value',
        [['value' => '2024-01-01 00:01:30']],
    ],
    'time fractional second modifier without subsec' => [
        'SELECT time(\'2024-01-01 00:00:00\', \'+1.5 seconds\') AS value',
        [['value' => '00:00:01']],
    ],
    'time fractional second modifier with subsec' => [
        'SELECT time(\'2024-01-01 00:00:00\', \'+1.5 seconds\', \'subsec\') AS value',
        [['value' => '00:00:01.500']],
    ],
    'datetime fractional month modifier' => [
        'SELECT datetime(\'2024-01-31\', \'+1.5 months\') AS value',
        [['value' => '2024-03-17 00:00:00']],
    ],
    'datetime fractional year modifier' => [
        'SELECT datetime(\'2024-02-29\', \'+0.5 years\') AS value',
        [['value' => '2024-08-29 12:00:00']],
    ],
    'datetime negative fractional day modifier' => [
        'SELECT datetime(\'2024-01-02 00:00:00\', \'-0.25 days\') AS value',
        [['value' => '2024-01-01 18:00:00']],
    ],
    'datetime timezone positive offset normalizes utc' => [
        'SELECT datetime(\'2024-01-01 03:30:00+02:30\') AS value',
        [['value' => '2024-01-01 01:00:00']],
    ],
    'datetime timezone negative offset normalizes utc' => [
        'SELECT datetime(\'2024-01-01 03:30:00-02:30\') AS value',
        [['value' => '2024-01-01 06:00:00']],
    ],
    'datetime z suffix with subsec' => [
        'SELECT datetime(\'2024-01-01T00:00:00.125Z\', \'subsec\') AS value',
        [['value' => '2024-01-01 00:00:00.125']],
    ],
    'unixepoch truncates fractional seconds by default' => [
        'SELECT unixepoch(\'2024-01-01 00:00:00.125\') AS value',
        [['value' => 1704067200]],
    ],
    'unixepoch subsec returns fractional seconds' => [
        'SELECT unixepoch(\'2024-01-01 00:00:00.125\', \'subsec\') AS value',
        [['value' => 1704067200.125]],
    ],
    'datetime unixepoch subsec preserves milliseconds' => [
        'SELECT datetime(1704067200.125, \'unixepoch\', \'subsec\') AS value',
        [['value' => '2024-01-01 00:00:00.125']],
    ],
    'time unixepoch subsec preserves milliseconds' => [
        'SELECT time(1704067200.125, \'unixepoch\', \'subsec\') AS value',
        [['value' => '00:00:00.125']],
    ],
    'strftime fractional seconds' => [
        'SELECT strftime(\'%f\', \'2024-01-01 00:00:00.125\') AS value',
        [['value' => '00.125']],
    ],
    'strftime julian day text' => [
        'SELECT strftime(\'%J\', \'2024-01-01 00:00:00\') AS value',
        [['value' => '2460310.5']],
    ],
    'timediff preserves millisecond difference' => [
        'SELECT timediff(\'2024-01-02 03:04:05.678\', \'2024-01-01 01:02:03.123\') AS value',
        [['value' => '+0000-00-01 02:02:02.555']],
    ],
    'date function in where with fractional modifier' => [
        'SELECT option_name FROM wp_options WHERE date(\'2024-01-01 23:00:00\', \'+2 hours\') = \'2024-01-02\' ORDER BY option_id LIMIT 1',
        [['option_name' => 'siteurl']],
    ],
    'datetime function in order expression with timezone' => [
        'SELECT option_name FROM wp_options ORDER BY datetime(\'2024-01-01 03:30:00+02:30\'), option_id LIMIT 2',
        [['option_name' => 'siteurl'], ['option_name' => 'home']],
    ],
];

$tests = [];

foreach ($cases as $name => $case) {
    $tests['select sql core scalar function corpus ' . $name] = static function (TestRunner $t) use ($run, $case, $normalize): void {
        $t->same($normalize($case[1]), $normalize($run($case[0], $case[2] ?? [])));
    };
}

return $tests;
