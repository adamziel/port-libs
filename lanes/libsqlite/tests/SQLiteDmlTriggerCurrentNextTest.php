<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDmlTriggerCurrentNextPlan;

return [
    'captures insert update delete old and new trigger row images' => static function (TestRunner $t): void {
        $rows = [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 20],
            ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 20],
            ['option_id' => 3, 'option_name' => '_transient_feed', 'option_value' => 'cached', 'autoload' => 'no', 'bytes' => 6],
        ];
        $triggers = [
            [
                'name' => 'wp_options_bi_audit',
                'timing' => 'before',
                'event' => 'insert',
                'table' => 'wp_options',
                'when' => ['left' => 'new.autoload', 'operator' => '=', 'right' => 'yes'],
                'values' => ['phase' => 'before-insert', 'rowid' => 'new.option_id', 'name' => 'new.option_name', 'value' => 'new.option_value'],
            ],
            [
                'name' => 'wp_options_ai_audit',
                'timing' => 'after',
                'event' => 'insert',
                'table' => 'wp_options',
                'values' => ['phase' => 'after-insert', 'rowid' => 'new.option_id', 'name' => 'new.option_name', 'value' => 'new.option_value'],
            ],
            [
                'name' => 'wp_options_bu_audit',
                'timing' => 'before',
                'event' => 'update',
                'table' => 'wp_options',
                'of' => ['option_value', 'bytes'],
                'when' => ['left' => 'old.option_value', 'operator' => '!=', 'right' => 'new.option_value'],
                'values' => ['phase' => 'before-update', 'rowid' => 'old.option_id', 'name' => 'old.option_name', 'old_value' => 'old.option_value', 'new_value' => 'new.option_value'],
            ],
            [
                'name' => 'wp_options_au_audit',
                'timing' => 'after',
                'event' => 'update',
                'table' => 'wp_options',
                'of' => ['option_value'],
                'values' => ['phase' => 'after-update', 'rowid' => 'new.option_id', 'name' => 'new.option_name', 'old_value' => 'old.option_value', 'new_value' => 'new.option_value'],
            ],
            [
                'name' => 'wp_options_bd_audit',
                'timing' => 'before',
                'event' => 'delete',
                'table' => 'wp_options',
                'when' => ['left' => 'old.autoload', 'operator' => 'is', 'right' => 'no'],
                'values' => ['phase' => 'before-delete', 'rowid' => 'old.option_id', 'name' => 'old.option_name', 'value' => 'old.option_value'],
            ],
            [
                'name' => 'wp_options_ad_audit',
                'timing' => 'after',
                'event' => 'delete',
                'table' => 'wp_options',
                'values' => ['phase' => 'after-delete', 'rowid' => 'old.option_id', 'name' => 'old.option_name', 'value' => 'old.option_value'],
            ],
        ];

        $insert = SQLiteDmlTriggerCurrentNextPlan::insertRows(
            $rows,
            [
                ['option_id' => null, 'option_name' => 'blogname', 'option_value' => 'Example Site', 'autoload' => 'yes', 'bytes' => 12],
                ['option_id' => 9, 'option_name' => '_site_transient_update_plugins', 'option_value' => 'plugins', 'autoload' => 'no', 'bytes' => 7],
            ],
            $triggers,
        );
        $t->same(2, $insert['changes']);
        $t->same([4, 9], $insert['visited']);
        $t->same([4, 9], array_column($insert['inserted'], 'option_id'));
        $t->same(['blogname', '_site_transient_update_plugins'], array_column($insert['inserted'], 'option_name'));
        $t->same(5, count($insert['rows']));
        $t->same(['before-insert', 'after-insert', 'after-insert'], array_column($insert['audit'], 'phase'));
        $t->same([4, 4, 9], array_column($insert['audit'], 'rowid'));
        $t->same(['blogname', 'blogname', '_site_transient_update_plugins'], array_column($insert['audit'], 'name'));
        $t->same('Example Site', $insert['rows'][3]['option_value']);
        $t->same('plugins', $insert['rows'][4]['option_value']);

        $update = SQLiteDmlTriggerCurrentNextPlan::updateRows(
            $insert['rows'],
            [
                'option_value' => static fn (array $row): string => strtoupper((string) $row['option_value']),
                'bytes' => static fn (array $row): int => strlen((string) $row['option_value']) + 10,
            ],
            static fn (array $row): bool => in_array($row['option_name'], ['home', 'blogname'], true),
            $triggers,
            [['column' => 'option_id', 'direction' => 'desc']],
            2,
        );
        $t->same(2, $update['changes']);
        $t->same([4, 2], $update['visited']);
        $t->same(['blogname', 'home'], array_column($update['updated'], 'option_name'));
        $t->same(['EXAMPLE SITE', 'HTTPS://EXAMPLE.TEST'], array_column($update['updated'], 'option_value'));
        $t->same([22, 30], array_column($update['updated'], 'bytes'));
        $t->same(['before-update', 'after-update', 'before-update', 'after-update'], array_column($update['audit'], 'phase'));
        $t->same([4, 4, 2, 2], array_column($update['audit'], 'rowid'));
        $t->same(['Example Site', 'Example Site', 'https://example.test', 'https://example.test'], array_column($update['audit'], 'old_value'));
        $t->same(['EXAMPLE SITE', 'EXAMPLE SITE', 'HTTPS://EXAMPLE.TEST', 'HTTPS://EXAMPLE.TEST'], array_column($update['audit'], 'new_value'));
        $t->same('HTTPS://EXAMPLE.TEST', $update['rows'][1]['option_value']);
        $t->same('EXAMPLE SITE', $update['rows'][3]['option_value']);

        $delete = SQLiteDmlTriggerCurrentNextPlan::deleteRows(
            $update['rows'],
            static fn (array $row): bool => $row['autoload'] === 'no',
            $triggers,
            [['column' => 'option_id', 'direction' => 'asc']],
            2,
        );
        $t->same(2, $delete['changes']);
        $t->same([3, 9], $delete['visited']);
        $t->same(['_transient_feed', '_site_transient_update_plugins'], array_column($delete['deleted'], 'option_name'));
        $t->same(['before-delete', 'after-delete', 'before-delete', 'after-delete'], array_column($delete['audit'], 'phase'));
        $t->same([3, 3, 9, 9], array_column($delete['audit'], 'rowid'));
        $t->same(['cached', 'cached', 'plugins', 'plugins'], array_column($delete['audit'], 'value'));
        $t->same(3, count($delete['rows']));
        $t->same(['siteurl', 'home', 'blogname'], array_column($delete['rows'], 'option_name'));
        $t->same(['https://example.test', 'HTTPS://EXAMPLE.TEST', 'EXAMPLE SITE'], array_column($delete['rows'], 'option_value'));
        $t->same([], $delete['inserted']);
        $t->same([], $delete['updated']);
        $t->same(0, count($delete['inserted']));
        $t->same(0, count($delete['updated']));
        $t->same(2, count($delete['deleted']));
    },
    'guards malformed dml trigger current next definitions' => static function (TestRunner $t): void {
        $rows = [
            ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'bytes' => 20],
        ];
        $insertTrigger = [
            'name' => 'bad_old_insert',
            'timing' => 'before',
            'event' => 'insert',
            'table' => 'wp_options',
            'values' => ['rowid' => 'old.option_id'],
        ];
        $deleteTrigger = [
            'name' => 'bad_new_delete',
            'timing' => 'after',
            'event' => 'delete',
            'table' => 'wp_options',
            'values' => ['rowid' => 'new.option_id'],
        ];
        $updateTrigger = [
            'name' => 'skip_unchanged',
            'timing' => 'after',
            'event' => 'update',
            'table' => 'wp_options',
            'of' => ['option_value'],
            'values' => ['rowid' => 'new.option_id'],
        ];

        $unchanged = SQLiteDmlTriggerCurrentNextPlan::updateRows(
            $rows,
            ['bytes' => 21],
            static fn (array $row): bool => $row['option_id'] === 1,
            [$updateTrigger],
        );
        $t->same(1, $unchanged['changes']);
        $t->same([], $unchanged['audit']);
        $t->same(21, $unchanged['rows'][0]['bytes']);
        $t->same('https://example.test', $unchanged['rows'][0]['option_value']);
        $t->same([1], $unchanged['visited']);

        $coalesce = SQLiteDmlTriggerCurrentNextPlan::updateRows(
            $rows,
            ['option_value' => null],
            static fn (array $row): bool => $row['option_id'] === 1,
            [[
                'name' => 'coalesce_update',
                'timing' => 'after',
                'event' => 'update',
                'table' => 'wp_options',
                'values' => ['rowid' => 'coalesce:new.option_id:old.option_id', 'value' => 'coalesce:new.option_value:old.option_value'],
            ]],
        );
        $t->same(1, $coalesce['changes']);
        $t->same(1, $coalesce['audit'][0]['rowid']);
        $t->same('https://example.test', $coalesce['audit'][0]['value']);
        $t->same(null, $coalesce['rows'][0]['option_value']);
        $t->same('coalesce_update', $coalesce['audit'][0]['trigger']);

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::insertRows($rows, [['option_name' => 'home']], [$insertTrigger]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::deleteRows($rows, static fn (): bool => true, [$deleteTrigger]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::updateRows($rows, [], static fn (): bool => true, []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::updateRows($rows, ['1bad' => 'x'], static fn (): bool => true, []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::deleteRows($rows, static fn (): bool => true, [['name' => 'bad', 'timing' => 'instead', 'event' => 'delete', 'table' => 'wp_options']]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::deleteRows($rows, static fn (): bool => true, [['name' => 'bad', 'timing' => 'after', 'event' => 'delete', 'table' => 'other']]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::deleteRows($rows, static fn (): bool => true, [], [['column' => 'option_id', 'direction' => 'sideways']]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::deleteRows($rows, static fn (): bool => true, [], [], -1));
    },
];
