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
    $state->stepArrayDistinctOrderBy($value, $name);
    $state->stepArrayFilter($value, $autoload === 'yes' ? 1 : 0);
    $state->stepArrayWindow($value);
    $state->stepArrayOrderByWindow($value, $name);
    $state->stepObject($name, $autoload === 'yes');
    $state->stepObjectDistinct($name, $autoload === 'yes');
    $state->stepObjectOrderBy($name, $autoload === 'yes', $name);
    $state->stepObjectDistinctOrderBy($name, $autoload === 'yes', $name);
    $state->stepObjectFilter($name, $autoload === 'yes', $autoload === 'yes');
    $state->stepObjectWindow($name, $autoload === 'yes');
    $state->stepObjectOrderByWindow($name, $autoload === 'yes', $name);
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
    'distinctNameOrderedOptionValueArrayFromSteps' => $state->finalizeDistinctOrderedArray('JSON_GROUP_ARRAY'),
    'autoloadedOptionValueArrayFromFilterSteps' => $state->finalizeFilteredArray('JSON_GROUP_ARRAY'),
    'rollingCurrentAndPreviousOptionValues' => $state->finalizeWindowedArray(1, 0, 'JSON_GROUP_ARRAY'),
    'nameOrderedRollingCurrentAndPreviousOptionValues' => $state->finalizeOrderedWindowedArray(1, 0, 'JSON_GROUP_ARRAY'),
    'distinctOptionValueJsonbDecoded' => SQLiteJsonB::decode(
        SQLiteJsonAggregate::jsonGroupArrayDistinctSqlFunctionArguments('JSONB_GROUP_ARRAY', $optionValues)->bytes,
    ),
    'autoloadedOptionValueJsonbDecoded' => SQLiteJsonB::decode(
        $state->finalizeFilteredArray('JSONB_GROUP_ARRAY')->bytes,
    ),
    'nameOrderedOptionValueJsonbDecoded' => SQLiteJsonB::decode(
        $state->finalizeOrderedArray('JSONB_GROUP_ARRAY')->bytes,
    ),
    'distinctNameOrderedOptionValueJsonbDecoded' => SQLiteJsonB::decode(
        $state->finalizeDistinctOrderedArray('JSONB_GROUP_ARRAY')->bytes,
    ),
    'rollingOptionValueJsonbDecoded' => array_map(
        static fn (SQLiteBlobValue $frame): mixed => SQLiteJsonB::decode($frame->bytes),
        $state->finalizeWindowedArray(1, 0, 'JSONB_GROUP_ARRAY'),
    ),
    'optionValueJsonbDecoded' => SQLiteJsonB::decode(
        $state->finalizeArray('JSONB_GROUP_ARRAY')->bytes,
    ),
    'autoloadMap' => SQLiteJsonAggregate::jsonGroupObject($autoloadSummary),
    'autoloadMapDispatch' => $state->finalizeObject('JSON_GROUP_OBJECT'),
    'distinctAutoloadMapDispatch' => $state->finalizeDistinctObject('JSON_GROUP_OBJECT'),
    'nameOrderedAutoloadMapDispatch' => $state->finalizeOrderedObject('JSON_GROUP_OBJECT'),
    'distinctNameOrderedAutoloadMapDispatch' => $state->finalizeDistinctOrderedObject('JSON_GROUP_OBJECT'),
    'autoloadOnlyMapDispatch' => $state->finalizeFilteredObject('JSON_GROUP_OBJECT'),
    'rollingCurrentAndPreviousAutoloadMap' => $state->finalizeWindowedObject(1, 0, 'JSON_GROUP_OBJECT'),
    'nameOrderedRollingCurrentAndPreviousAutoloadMap' => $state->finalizeOrderedWindowedObject(1, 0, 'JSON_GROUP_OBJECT'),
    'autoloadMapJsonbHex' => bin2hex(
        $state->finalizeObject('JSONB_GROUP_OBJECT')->bytes,
    ),
    'nameOrderedAutoloadMapJsonbDecoded' => SQLiteJsonB::decode(
        $state->finalizeOrderedObject('JSONB_GROUP_OBJECT')->bytes,
    ),
    'rollingAutoloadMapJsonbDecoded' => array_map(
        static fn (SQLiteBlobValue $frame): mixed => SQLiteJsonB::decode($frame->bytes),
        $state->finalizeWindowedObject(1, 0, 'JSONB_GROUP_OBJECT'),
    ),
    'aggregateStepRows' => $state->summary(),
    'applicationUse' => 'Local-only wp_options import summary that mirrors SQLite json_group_array()/json_group_array(DISTINCT)/json_group_array(ORDER BY option_name)/json_group_array(DISTINCT ORDER BY option_name)/json_group_array() FILTER plus json_group_object() DISTINCT/ORDER BY/FILTER and bounded ROWS window step/final results with uppercase JSONB/result dispatch for copied option values, JSON subtype fragments, JSONB blobs, booleans, and NULLs without requiring the SQLite extension.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
