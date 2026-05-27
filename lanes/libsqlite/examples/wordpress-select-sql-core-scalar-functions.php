<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'maybe' => null],
    ['option_id' => 2, 'option_name' => 'blogname', 'autoload' => 'yes', 'option_value' => ' Example Site ', 'maybe' => 'title'],
    ['option_id' => 3, 'option_name' => '_transient_feed', 'autoload' => 'no', 'option_value' => 'cached', 'maybe' => 'feed'],
    ['option_id' => 4, 'option_name' => 'plugin_cache_key', 'autoload' => 'no', 'option_value' => ' enabled ', 'maybe' => null],
];

$rows = SQLiteSelectSql::execute(
    <<<'SQL'
SELECT
  option_id,
  coalesce(maybe, option_name) AS label,
  upper(substr(trim(option_value), 1, 3)) AS preview,
  quote(option_name) AS quoted_name,
  hex(autoload) AS autoload_hex
FROM wp_options
WHERE coalesce(maybe, 'missing') != 'feed'
ORDER BY lower(coalesce(maybe, option_name)), length(option_name)
SQL,
    ['wp_options' => $options],
);

$normalize = static function (mixed $value) use (&$normalize): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return ['blob' => bin2hex($value->bytes)];
    }
    if (is_array($value)) {
        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $normalize($item);
        }

        return $normalized;
    }

    return $value;
};

if (($argv[1] ?? '') === '--self-test') {
    $expected = [
        ['option_id' => 4, 'label' => 'plugin_cache_key', 'preview' => 'ENA', 'quoted_name' => "'plugin_cache_key'", 'autoload_hex' => '6E6F'],
        ['option_id' => 1, 'label' => 'siteurl', 'preview' => 'HTT', 'quoted_name' => "'siteurl'", 'autoload_hex' => '796573'],
        ['option_id' => 2, 'label' => 'title', 'preview' => 'EXA', 'quoted_name' => "'blogname'", 'autoload_hex' => '796573'],
    ];

    if ($normalize($rows) !== $expected) {
        fwrite(STDERR, 'Unexpected SELECT SQL scalar-function rows: ' . var_export($normalize($rows), true) . "\n");
        exit(1);
    }
}

echo json_encode($normalize($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
