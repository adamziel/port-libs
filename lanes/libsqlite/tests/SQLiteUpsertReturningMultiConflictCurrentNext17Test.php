<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$baseRows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'slot' => 'primary', 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'no', 'slot' => 'secondary', 'option_value' => 'https://home.test', 'revision' => 3],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'maybe', 'slot' => 'display', 'option_value' => 'Old Blog', 'revision' => 5],
    ['option_id' => 4, 'option_name' => 'theme_mods', 'autoload' => null, 'slot' => 'json', 'option_value' => '{}', 'revision' => 2],
];

$nameAssignments = [
    'option_id' => static fn (array $current, array $excluded): mixed => $excluded['option_id'],
    'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
    'slot' => static fn (array $current, array $excluded): mixed => $excluded['slot'],
    'option_value' => static fn (array $current, array $excluded): mixed => $excluded['option_value'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + (int) $excluded['revision'],
];

$autoloadAssignments = [
    'option_name' => static fn (array $current, array $excluded): mixed => $excluded['option_name'],
    'slot' => static fn (array $current, array $excluded): mixed => $excluded['slot'],
    'option_value' => static fn (array $current, array $excluded): string => 'autoload-arm:' . $current['option_name'] . '->' . $excluded['option_name'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + 10 + (int) $excluded['revision'],
];

$slotAssignments = [
    'option_name' => static fn (array $current, array $excluded): mixed => $excluded['option_name'],
    'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
    'option_value' => static fn (array $current, array $excluded): string => 'slot-arm:' . $current['option_name'] . '->' . $excluded['option_name'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + 20 + (int) $excluded['revision'],
];

$arms = [
    ['target' => ['option_name'], 'action' => 'update', 'assignments' => $nameAssignments],
    ['target' => ['autoload'], 'action' => 'update', 'assignments' => $autoloadAssignments, 'where' => static fn (array $current, array $excluded): bool => $excluded['option_value'] !== 'skip-autoload'],
    ['target' => ['slot'], 'action' => 'nothing'],
    ['target' => null, 'action' => 'update', 'assignments' => $slotAssignments],
];

$run = static function (array $incomingRows, ?array $customArms = null) use ($baseRows, $arms): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $baseRows,
        $incomingRows,
        $customArms ?? $arms,
        [['option_name'], ['autoload'], ['slot']],
    );
};

$mixedPlan = static fn (): array => $run([
    ['option_id' => 10, 'option_name' => 'siteurl', 'autoload' => 'manual', 'slot' => 'canonical', 'option_value' => 'https://new.test', 'revision' => 4],
    ['option_id' => 11, 'option_name' => 'plugin_for_manual', 'autoload' => 'manual', 'slot' => 'plugin-slot', 'option_value' => 'plugin', 'revision' => 1],
    ['option_id' => 12, 'option_name' => 'slot_only_skip', 'autoload' => 'slot-auto', 'slot' => 'display', 'option_value' => 'ignored-slot', 'revision' => 1],
    ['option_id' => 13, 'option_name' => 'fresh_plugin', 'autoload' => 'fresh-auto', 'slot' => 'fresh-slot', 'option_value' => 'fresh', 'revision' => 1],
    ['option_id' => 14, 'option_name' => 'theme_copy', 'autoload' => 'json-copy', 'slot' => 'json', 'option_value' => 'slot-skip', 'revision' => 2],
]);

$firstMatchPlan = static fn (): array => $run([
    ['option_id' => 20, 'option_name' => 'siteurl', 'autoload' => 'yes', 'slot' => 'primary', 'option_value' => 'name-wins', 'revision' => 1],
]);

$whereSkipPlan = static fn (): array => $run([
    ['option_id' => 30, 'option_name' => 'skip_candidate', 'autoload' => 'yes', 'slot' => 'skip-slot', 'option_value' => 'skip-autoload', 'revision' => 1],
    ['option_id' => 31, 'option_name' => 'safe_after_skip', 'autoload' => 'safe-auto', 'slot' => 'safe-slot', 'option_value' => 'safe', 'revision' => 1],
]);

$repeatPlan = static fn (): array => $run([
    ['option_id' => 40, 'option_name' => 'repeat_one', 'autoload' => 'repeat-auto', 'slot' => 'repeat-slot', 'option_value' => 'inserted', 'revision' => 1],
    ['option_id' => 41, 'option_name' => 'repeat_two', 'autoload' => 'repeat-auto', 'slot' => 'repeat-slot-two', 'option_value' => 'autoload-repeat', 'revision' => 2],
    ['option_id' => 42, 'option_name' => 'repeat_two', 'autoload' => 'repeat-final', 'slot' => 'repeat-final-slot', 'option_value' => 'name-repeat', 'revision' => 3],
]);

$nullPlan = static fn (): array => $run([
    ['option_id' => 50, 'option_name' => 'null_autoload_a', 'autoload' => null, 'slot' => 'null-a', 'option_value' => 'a', 'revision' => 1],
    ['option_id' => 51, 'option_name' => 'null_autoload_b', 'autoload' => null, 'slot' => 'null-b', 'option_value' => 'b', 'revision' => 1],
]);

$catchAllPlan = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
    $baseRows,
    [
        ['option_id' => 60, 'option_name' => 'fresh_name', 'autoload' => 'fresh-autoload', 'slot' => 'primary', 'option_value' => 'slot-catch-all', 'revision' => 1],
    ],
    [
        ['target' => ['option_name'], 'action' => 'nothing'],
        ['target' => null, 'action' => 'update', 'assignments' => $slotAssignments],
    ],
    [['option_name'], ['autoload'], ['slot']],
);

$cases = [
    'mixed returning omits do nothing rows' => [static fn (): mixed => array_column($mixedPlan()['returning_rows'], 'option_name'), ['siteurl', 'plugin_for_manual', 'fresh_plugin']],
    'mixed skipped contains slot do nothing rows' => [static fn (): mixed => array_column($mixedPlan()['skipped_rows'], 'option_name'), ['slot_only_skip', 'theme_copy']],
    'mixed changes excludes skipped rows' => [static fn (): mixed => $mixedPlan()['changes'], 3],
    'mixed inserted rows include only non conflict row' => [static fn (): mixed => array_column($mixedPlan()['inserted_rows'], 'option_name'), ['fresh_plugin']],
    'mixed updated rows include name and autoload arms' => [static fn (): mixed => array_column($mixedPlan()['updated_rows'], 'option_name'), ['siteurl', 'plugin_for_manual']],
    'mixed matched arm targets preserve statement conflicts' => [static fn (): mixed => array_column($mixedPlan()['matched_arms'], 'target'), [['option_name'], ['autoload'], ['slot'], ['slot']]],
    'mixed matched actions include skipped nothing arms' => [static fn (): mixed => array_column($mixedPlan()['matched_arms'], 'action'), ['update', 'update', 'nothing', 'nothing']],
    'mixed name arm uses excluded values' => [static fn (): mixed => $mixedPlan()['returning_rows'][0]['option_value'], 'https://new.test'],
    'mixed autoload arm rewrites conflicting current row name' => [static fn (): mixed => $mixedPlan()['returning_rows'][1]['option_value'], 'autoload-arm:siteurl->plugin_for_manual'],
    'mixed autoload arm revision uses current row after previous update' => [static fn (): mixed => $mixedPlan()['returning_rows'][1]['revision'], 16],
    'mixed named slot arm prevents later catch all' => [static fn (): mixed => in_array('theme_copy', array_column($mixedPlan()['returning_rows'], 'option_name'), true), false],
    'mixed after has no skipped slot insert' => [static fn (): mixed => in_array('slot_only_skip', array_column($mixedPlan()['after'], 'option_name'), true), false],
    'mixed after appends fresh insert' => [static fn (): mixed => array_column($mixedPlan()['after'], 'option_name'), ['plugin_for_manual', 'home', 'blogname', 'theme_mods', 'fresh_plugin']],
    'mixed returning projection works after conflict arms' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['name' => 'option_name', 'value' => 'option_value'])[1], ['name' => 'plugin_for_manual', 'value' => 'autoload-arm:siteurl->plugin_for_manual']],
    'first match target option name wins over autoload and slot' => [static fn (): mixed => $firstMatchPlan()['returning_rows'][0]['option_name'], 'siteurl'],
    'first match does not route to autoload arm' => [static fn (): mixed => $firstMatchPlan()['returning_rows'][0]['option_value'], 'name-wins'],
    'first match still validates secondary unique conflict' => [static fn (): mixed => $run([['option_id' => 21, 'option_name' => 'siteurl', 'autoload' => 'no', 'slot' => 'secondary', 'option_value' => 'bad', 'revision' => 1]]), InvalidArgumentException::class],
    'where skip omits autoload conflict returning row' => [static fn (): mixed => array_column($whereSkipPlan()['returning_rows'], 'option_name'), ['safe_after_skip']],
    'where skip records incoming row' => [static fn (): mixed => array_column($whereSkipPlan()['skipped_rows'], 'option_name'), ['skip_candidate']],
    'where skip does not run secondary unique checks' => [static fn (): mixed => $whereSkipPlan()['after'][0]['autoload'], 'yes'],
    'where skip still inserts later safe row' => [static fn (): mixed => $whereSkipPlan()['inserted_rows'][0]['option_name'], 'safe_after_skip'],
    'repeat first row inserts' => [static fn (): mixed => $repeatPlan()['inserted_rows'][0]['option_name'], 'repeat_one'],
    'repeat second row matches earlier inserted autoload' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['option_value'], 'autoload-arm:repeat_one->repeat_two'],
    'repeat third row matches updated option name before autoload' => [static fn (): mixed => $repeatPlan()['returning_rows'][2]['option_value'], 'name-repeat'],
    'repeat final row appears once' => [static fn (): mixed => count(array_filter($repeatPlan()['after'], static fn (array $row): bool => $row['option_name'] === 'repeat_two')), 1],
    'repeat changes include insert and two updates' => [static fn (): mixed => $repeatPlan()['changes'], 3],
    'repeat returning order follows incoming rows' => [static fn (): mixed => array_column($repeatPlan()['returning_rows'], 'option_id'), [40, 40, 42]],
    'null autoload rows do not conflict with unique autoload arm' => [static fn (): mixed => array_column($nullPlan()['inserted_rows'], 'option_name'), ['null_autoload_a', 'null_autoload_b']],
    'null autoload changes include both inserts' => [static fn (): mixed => $nullPlan()['changes'], 2],
    'null autoload has no matched arms' => [static fn (): mixed => $nullPlan()['matched_arms'], []],
    'catch all arm handles conflict missed by named arm' => [static fn (): mixed => $catchAllPlan()['matched_arms'][0]['target'], null],
    'catch all arm updates slot current row' => [static fn (): mixed => $catchAllPlan()['returning_rows'][0]['option_value'], 'slot-arm:siteurl->fresh_name'],
    'catch all after replaces original slot holder name' => [static fn (): mixed => $catchAllPlan()['after'][0]['option_name'], 'fresh_name'],
    'incoming conflicting non targeted unique aborts before insert' => [static fn (): mixed => $run([['option_id' => 70, 'option_name' => 'fresh_bad', 'autoload' => 'no', 'slot' => 'fresh-bad', 'option_value' => 'bad', 'revision' => 1]], [['target' => ['option_name'], 'action' => 'update', 'assignments' => $nameAssignments]]), InvalidArgumentException::class],
    'update result conflicting with current row aborts' => [static fn (): mixed => $run([['option_id' => 71, 'option_name' => 'siteurl', 'autoload' => 'no', 'slot' => 'primary-next', 'option_value' => 'bad', 'revision' => 1]]), InvalidArgumentException::class],
    'later insert conflicting with earlier update aborts when no autoload arm handles it' => [static fn (): mixed => $run([['option_id' => 72, 'option_name' => 'siteurl', 'autoload' => 'site-next', 'slot' => 'site-next-slot', 'option_value' => 'ok', 'revision' => 1], ['option_id' => 73, 'option_name' => 'later_bad', 'autoload' => 'site-next', 'slot' => 'later-slot', 'option_value' => 'bad', 'revision' => 1]], [['target' => ['option_name'], 'action' => 'update', 'assignments' => $nameAssignments]]), InvalidArgumentException::class],
    'conflict arms validate non empty list' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [], [], [['option_name']]), InvalidArgumentException::class],
    'conflict arms validate target list' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [], [['target' => 'option_name', 'action' => 'nothing']], [['option_name']]), InvalidArgumentException::class],
    'conflict arms validate action' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [], [['target' => ['option_name'], 'action' => 'replace']], [['option_name']]), InvalidArgumentException::class],
    'conflict arms validate update assignments' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [], [['target' => ['option_name'], 'action' => 'update']], [['option_name']]), InvalidArgumentException::class],
    'conflict arms validate do nothing assignments' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [], [['target' => ['option_name'], 'action' => 'nothing', 'assignments' => $nameAssignments]], [['option_name']]), InvalidArgumentException::class],
    'conflict arms validate where callable' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [], [['target' => ['option_name'], 'action' => 'nothing', 'where' => true]], [['option_name']]), InvalidArgumentException::class],
    'unique constraints validate conflict arm baseline' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::executeConflictArms($baseRows, [], $arms, []), InvalidArgumentException::class],
    'returning projection wildcard still exposes arm output' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($catchAllPlan()['returning_rows'], ['*'])[0]['revision'], 22],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upsert returning multi conflict current next17 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
