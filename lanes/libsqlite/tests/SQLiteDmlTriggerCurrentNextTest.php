<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDmlTriggerCurrentNextPlan;

return [
    'captures insert update delete old and new trigger row images' => static function (TestRunner $t): void {
        $rows = [
            ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'bytes' => 20],
            ['setting_id' => 2, 'key_name' => 'public_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'bytes' => 20],
            ['setting_id' => 3, 'key_name' => 'cache_feed', 'key_value' => 'cached', 'load_policy' => 'no', 'bytes' => 6],
        ];
        $triggers = [
            [
                'name' => 'app_settings_bi_audit',
                'timing' => 'before',
                'event' => 'insert',
                'table' => 'app_settings',
                'when' => ['left' => 'new.load_policy', 'operator' => '=', 'right' => 'yes'],
                'values' => ['phase' => 'before-insert', 'rowid' => 'new.setting_id', 'name' => 'new.key_name', 'value' => 'new.key_value'],
            ],
            [
                'name' => 'app_settings_ai_audit',
                'timing' => 'after',
                'event' => 'insert',
                'table' => 'app_settings',
                'values' => ['phase' => 'after-insert', 'rowid' => 'new.setting_id', 'name' => 'new.key_name', 'value' => 'new.key_value'],
            ],
            [
                'name' => 'app_settings_bu_audit',
                'timing' => 'before',
                'event' => 'update',
                'table' => 'app_settings',
                'of' => ['key_value', 'bytes'],
                'when' => ['left' => 'old.key_value', 'operator' => '!=', 'right' => 'new.key_value'],
                'values' => ['phase' => 'before-update', 'rowid' => 'old.setting_id', 'name' => 'old.key_name', 'old_value' => 'old.key_value', 'new_value' => 'new.key_value'],
            ],
            [
                'name' => 'app_settings_au_audit',
                'timing' => 'after',
                'event' => 'update',
                'table' => 'app_settings',
                'of' => ['key_value'],
                'values' => ['phase' => 'after-update', 'rowid' => 'new.setting_id', 'name' => 'new.key_name', 'old_value' => 'old.key_value', 'new_value' => 'new.key_value'],
            ],
            [
                'name' => 'app_settings_bd_audit',
                'timing' => 'before',
                'event' => 'delete',
                'table' => 'app_settings',
                'when' => ['left' => 'old.load_policy', 'operator' => 'is', 'right' => 'no'],
                'values' => ['phase' => 'before-delete', 'rowid' => 'old.setting_id', 'name' => 'old.key_name', 'value' => 'old.key_value'],
            ],
            [
                'name' => 'app_settings_ad_audit',
                'timing' => 'after',
                'event' => 'delete',
                'table' => 'app_settings',
                'values' => ['phase' => 'after-delete', 'rowid' => 'old.setting_id', 'name' => 'old.key_name', 'value' => 'old.key_value'],
            ],
        ];

        $insert = SQLiteDmlTriggerCurrentNextPlan::insertRows(
            $rows,
            [
                ['setting_id' => null, 'key_name' => 'site_title', 'key_value' => 'Example Site', 'load_policy' => 'yes', 'bytes' => 12],
                ['setting_id' => 9, 'key_name' => 'module_update_cache', 'key_value' => 'modules', 'load_policy' => 'no', 'bytes' => 7],
            ],
            $triggers,
        );
        $t->same(2, $insert['changes']);
        $t->same([4, 9], $insert['visited']);
        $t->same([4, 9], array_column($insert['inserted'], 'setting_id'));
        $t->same(['site_title', 'module_update_cache'], array_column($insert['inserted'], 'key_name'));
        $t->same(5, count($insert['rows']));
        $t->same(['before-insert', 'after-insert', 'after-insert'], array_column($insert['audit'], 'phase'));
        $t->same([4, 4, 9], array_column($insert['audit'], 'rowid'));
        $t->same(['site_title', 'site_title', 'module_update_cache'], array_column($insert['audit'], 'name'));
        $t->same('Example Site', $insert['rows'][3]['key_value']);
        $t->same('modules', $insert['rows'][4]['key_value']);

        $update = SQLiteDmlTriggerCurrentNextPlan::updateRows(
            $insert['rows'],
            [
                'key_value' => static fn (array $row): string => strtoupper((string) $row['key_value']),
                'bytes' => static fn (array $row): int => strlen((string) $row['key_value']) + 10,
            ],
            static fn (array $row): bool => in_array($row['key_name'], ['public_url', 'site_title'], true),
            $triggers,
            [['column' => 'setting_id', 'direction' => 'desc']],
            2,
        );
        $t->same(2, $update['changes']);
        $t->same([4, 2], $update['visited']);
        $t->same(['site_title', 'public_url'], array_column($update['updated'], 'key_name'));
        $t->same(['EXAMPLE SITE', 'HTTPS://EXAMPLE.TEST'], array_column($update['updated'], 'key_value'));
        $t->same([22, 30], array_column($update['updated'], 'bytes'));
        $t->same(['before-update', 'after-update', 'before-update', 'after-update'], array_column($update['audit'], 'phase'));
        $t->same([4, 4, 2, 2], array_column($update['audit'], 'rowid'));
        $t->same(['Example Site', 'Example Site', 'https://example.test', 'https://example.test'], array_column($update['audit'], 'old_value'));
        $t->same(['EXAMPLE SITE', 'EXAMPLE SITE', 'HTTPS://EXAMPLE.TEST', 'HTTPS://EXAMPLE.TEST'], array_column($update['audit'], 'new_value'));
        $t->same('HTTPS://EXAMPLE.TEST', $update['rows'][1]['key_value']);
        $t->same('EXAMPLE SITE', $update['rows'][3]['key_value']);

        $delete = SQLiteDmlTriggerCurrentNextPlan::deleteRows(
            $update['rows'],
            static fn (array $row): bool => $row['load_policy'] === 'no',
            $triggers,
            [['column' => 'setting_id', 'direction' => 'asc']],
            2,
        );
        $t->same(2, $delete['changes']);
        $t->same([3, 9], $delete['visited']);
        $t->same(['cache_feed', 'module_update_cache'], array_column($delete['deleted'], 'key_name'));
        $t->same(['before-delete', 'after-delete', 'before-delete', 'after-delete'], array_column($delete['audit'], 'phase'));
        $t->same([3, 3, 9, 9], array_column($delete['audit'], 'rowid'));
        $t->same(['cached', 'cached', 'modules', 'modules'], array_column($delete['audit'], 'value'));
        $t->same(3, count($delete['rows']));
        $t->same(['base_url', 'public_url', 'site_title'], array_column($delete['rows'], 'key_name'));
        $t->same(['https://example.test', 'HTTPS://EXAMPLE.TEST', 'EXAMPLE SITE'], array_column($delete['rows'], 'key_value'));
        $t->same([], $delete['inserted']);
        $t->same([], $delete['updated']);
        $t->same(0, count($delete['inserted']));
        $t->same(0, count($delete['updated']));
        $t->same(2, count($delete['deleted']));
    },
    'guards malformed dml trigger current next definitions' => static function (TestRunner $t): void {
        $rows = [
            ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'bytes' => 20],
        ];
        $insertTrigger = [
            'name' => 'bad_old_insert',
            'timing' => 'before',
            'event' => 'insert',
            'table' => 'app_settings',
            'values' => ['rowid' => 'old.setting_id'],
        ];
        $deleteTrigger = [
            'name' => 'bad_new_delete',
            'timing' => 'after',
            'event' => 'delete',
            'table' => 'app_settings',
            'values' => ['rowid' => 'new.setting_id'],
        ];
        $updateTrigger = [
            'name' => 'skip_unchanged',
            'timing' => 'after',
            'event' => 'update',
            'table' => 'app_settings',
            'of' => ['key_value'],
            'values' => ['rowid' => 'new.setting_id'],
        ];

        $unchanged = SQLiteDmlTriggerCurrentNextPlan::updateRows(
            $rows,
            ['bytes' => 21],
            static fn (array $row): bool => $row['setting_id'] === 1,
            [$updateTrigger],
        );
        $t->same(1, $unchanged['changes']);
        $t->same([], $unchanged['audit']);
        $t->same(21, $unchanged['rows'][0]['bytes']);
        $t->same('https://example.test', $unchanged['rows'][0]['key_value']);
        $t->same([1], $unchanged['visited']);

        $coalesce = SQLiteDmlTriggerCurrentNextPlan::updateRows(
            $rows,
            ['key_value' => null],
            static fn (array $row): bool => $row['setting_id'] === 1,
            [[
                'name' => 'coalesce_update',
                'timing' => 'after',
                'event' => 'update',
                'table' => 'app_settings',
                'values' => ['rowid' => 'coalesce:new.setting_id:old.setting_id', 'value' => 'coalesce:new.key_value:old.key_value'],
            ]],
        );
        $t->same(1, $coalesce['changes']);
        $t->same(1, $coalesce['audit'][0]['rowid']);
        $t->same('https://example.test', $coalesce['audit'][0]['value']);
        $t->same(null, $coalesce['rows'][0]['key_value']);
        $t->same('coalesce_update', $coalesce['audit'][0]['trigger']);

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::insertRows($rows, [['key_name' => 'public_url']], [$insertTrigger]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::deleteRows($rows, static fn (): bool => true, [$deleteTrigger]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::updateRows($rows, [], static fn (): bool => true, []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::updateRows($rows, ['1bad' => 'x'], static fn (): bool => true, []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::deleteRows($rows, static fn (): bool => true, [['name' => 'bad', 'timing' => 'instead', 'event' => 'delete', 'table' => 'app_settings']]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::deleteRows($rows, static fn (): bool => true, [['name' => 'bad', 'timing' => 'after', 'event' => 'delete', 'table' => 'other']]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::deleteRows($rows, static fn (): bool => true, [], [['column' => 'setting_id', 'direction' => 'sideways']]));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteDmlTriggerCurrentNextPlan::deleteRows($rows, static fn (): bool => true, [], [], -1));
    },
];
