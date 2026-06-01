<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonAggregate;
use PortLibs\LibSqlite\SQLiteJsonAggregateState;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonSubtypeValue;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$copiedOptions = [
    ['siteurl', 'https://example.test', 'yes'],
    ['blogname', 'Port Fixture', 'yes'],
    ['blogdescription', 'Port Fixture', 'yes'],
    ['plugin_rules', new SQLiteJsonSubtypeValue('[{"name":"seo"},{"name":"cache"}]'), 'no'],
    ['plugin_queue', new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2, 'ok' => true])), 'no'],
    ['plugin_queue_copy', new SQLiteBlobValue(SQLiteJsonB::encode(['pending' => 2, 'ok' => true])), 'no'],
    ['empty_option', null, 'no'],
];

$optionValues = [];
$autoloadSummary = [];
$state = new SQLiteJsonAggregateState();
foreach ($copiedOptions as [$name, $value, $autoload]) {
    $optionValues[] = $value;
    $autoloadSummary[] = [$name, $autoload === 'yes'];
    $state->stepArray($value);
    $state->stepArrayDistinct($value);
    $state->stepArrayOrderBy($value, $name);
    $state->stepObject($name, $autoload === 'yes');
}

echo json_encode([
    'optionValueArray' => SQLiteJsonAggregate::jsonGroupArray($optionValues),
    'distinctOptionValueArray' => SQLiteJsonAggregate::jsonGroupArrayDistinct($optionValues),
    'distinctOptionValueArrayVector' => SQLiteJsonAggregate::jsonGroupArrayDistinctSqlFunctionArguments(
        'JSON_GROUP_ARRAY',
        $optionValues,
    ),
    'optionValueArrayFromSteps' => $state->finalizeArray('JSON_GROUP_ARRAY'),
    'distinctOptionValueArrayFromSteps' => $state->finalizeDistinctArray('JSON_GROUP_ARRAY'),
    'nameOrderedOptionValueArrayFromSteps' => $state->finalizeOrderedArray('JSON_GROUP_ARRAY'),
    'distinctOptionValueJsonbDecoded' => SQLiteJsonB::decode(
        SQLiteJsonAggregate::jsonGroupArrayDistinctSqlFunctionArguments('JSONB_GROUP_ARRAY', $optionValues)->bytes,
    ),
    'nameOrderedOptionValueJsonbDecoded' => SQLiteJsonB::decode(
        $state->finalizeOrderedArray('JSONB_GROUP_ARRAY')->bytes,
    ),
    'optionValueJsonbDecoded' => SQLiteJsonB::decode(
        $state->finalizeArray('JSONB_GROUP_ARRAY')->bytes,
    ),
    'autoloadMap' => SQLiteJsonAggregate::jsonGroupObject($autoloadSummary),
    'autoloadMapDispatch' => $state->finalizeObject('JSON_GROUP_OBJECT'),
    'autoloadMapJsonbHex' => bin2hex(
        $state->finalizeObject('JSONB_GROUP_OBJECT')->bytes,
    ),
    'aggregateStepRows' => $state->summary(),
    'wordpressUse' => 'Local-only wp_options import summary that mirrors SQLite json_group_array()/json_group_array(DISTINCT)/json_group_array(ORDER BY option_name)/json_group_object() step/final results and uppercase JSONB/result dispatch for copied option values, JSON subtype fragments, JSONB blobs, booleans, and NULLs without requiring the SQLite extension.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
