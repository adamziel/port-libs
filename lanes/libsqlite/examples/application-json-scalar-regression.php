<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonPatch.php';
require_once __DIR__ . '/../src/SQLiteJsonPretty.php';
require_once __DIR__ . '/../src/SQLiteJsonQuote.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';

use PortLibs\LibSqlite\SQLiteJsonCanonical;
use PortLibs\LibSqlite\SQLiteJsonPatch;
use PortLibs\LibSqlite\SQLiteJsonPretty;
use PortLibs\LibSqlite\SQLiteJsonQuote;

$optionValue = "{plugin:{enabled:true,modes:['sync','cache',],ttl:300,},}";
$patch = '{"plugin":{"ttl":600,"modes":["sync","cache","forms"]}}';

$canonical = SQLiteJsonCanonical::json($optionValue);
$patched = SQLiteJsonPatch::patch($canonical, $patch);
$pretty = SQLiteJsonPretty::jsonPretty($patched, '  ');

echo json_encode(
    [
        'option_name' => 'plugin_settings',
        'canonical' => $canonical,
        'patched' => $patched,
        'quoted_name' => SQLiteJsonQuote::jsonQuote('plugin_settings'),
        'pretty_lines' => $pretty === null ? 0 : substr_count($pretty, "\n") + 1,
    ],
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
) . PHP_EOL;
