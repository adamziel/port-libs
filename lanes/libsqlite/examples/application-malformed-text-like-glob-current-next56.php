<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteSelectPredicate;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => "plugin_é\xc3", 'option_value' => 'malformed tail', 'autoload' => 'no'],
    ['option_id' => 2, 'option_name' => "plugin_\xc3é", 'option_value' => 'malformed middle', 'autoload' => 'no'],
    ['option_id' => 3, 'option_name' => 'plugin_é', 'option_value' => 'well formed', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'plugin_plain', 'option_value' => 'ascii', 'autoload' => 'yes'],
];

$likePredicate = [
    'operator' => 'LIKE',
    'left' => ['type' => 'column', 'name' => 'option_name'],
    'right' => ['type' => 'literal', 'value' => 'plugin_é_'],
];
$globPredicate = [
    'operator' => 'GLOB',
    'left' => ['type' => 'column', 'name' => 'option_name'],
    'right' => ['type' => 'literal', 'value' => 'plugin_?é'],
];

$preview = [
    'scenario' => 'application-malformed-text-like-glob-current-next56',
    'applicationUse' => 'Copied wp_options option_name predicates can scan malformed imported UTF-8 bytes without losing valid multibyte prefix matching for LIKE/GLOB diagnostics.',
    'likePattern' => 'plugin_é_',
    'likeMatchedOptionIds' => array_column(SQLiteSelectPredicate::filter($options, $likePredicate), 'option_id'),
    'globPattern' => 'plugin_?é',
    'globMatchedOptionIds' => array_column(SQLiteSelectPredicate::filter($options, $globPredicate), 'option_id'),
    'directLikeValidPrefixMalformedTail' => SQLiteDatabase::likeMatches("plugin_é\xc3", 'plugin_é_'),
    'directLikeDoesNotSplitValidCodepoint' => SQLiteDatabase::likeMatches('plugin_é', 'plugin___'),
    'directGlobMalformedMiddle' => SQLiteDatabase::globMatches("plugin_\xc3é", 'plugin_?é'),
    'directGlobDoesNotSplitValidCodepoint' => SQLiteDatabase::globMatches('plugin_é', 'plugin_??'),
    'dependencies' => ['native-php-utf8-pattern-splitter'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($preview['likeMatchedOptionIds'] === [1]);
    assert($preview['globMatchedOptionIds'] === [2]);
    assert($preview['directLikeValidPrefixMalformedTail'] === true);
    assert($preview['directLikeDoesNotSplitValidCodepoint'] === false);
    assert($preview['directGlobMalformedMiddle'] === true);
    assert($preview['directGlobDoesNotSplitValidCodepoint'] === false);
    echo "application-malformed-text-like-glob-current-next56 self-test passed\n";
    return;
}

echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
