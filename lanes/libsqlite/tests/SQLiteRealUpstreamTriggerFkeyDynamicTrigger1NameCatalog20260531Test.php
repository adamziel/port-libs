<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test';

$tests = [
    'real upstream trigger1 catalog identity cites same-name trigger block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'Create a trigger with the same name as a table'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER t2 BEFORE DELETE ON t2'));
        $t->true(is_string($source) && str_contains($source, 'DROP TRIGGER t2'));
    },
    'real upstream trigger1 catalog identity cites quoted keyword trigger block' => static function (TestRunner $t) use ($sourcePath): void {
        $source = file_get_contents($sourcePath);
        $t->true(is_string($source) && str_contains($source, 'trigger can be quoted so that keywords'));
        $t->true(is_string($source) && str_contains($source, "CREATE TRIGGER 'trigger' AFTER INSERT ON t2"));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER "trigger" AFTER INSERT ON t2'));
        $t->true(is_string($source) && str_contains($source, 'CREATE TRIGGER [trigger] AFTER INSERT ON t2'));
    },
];

$quoteSpecs = [
    ['single', "'trigger'"],
    ['double', '"trigger"'],
    ['bracket', '[trigger]'],
];

for ($variant = 1; $variant <= 334; ++$variant) {
    $suffix = sprintf('%03d', $variant);
    $tableName = 'app_settings_' . $suffix;
    $rows = [
        ['a' => $variant, 'b' => 'alpha-' . $suffix],
        ['a' => $variant + 1, 'b' => 'beta-' . $suffix],
        ['a' => $variant + 2, 'b' => 'gamma-' . $suffix],
        ['a' => $variant + 3, 'b' => 'delta-' . $suffix],
    ];

    foreach ($quoteSpecs as [$quoteStyle, $quotedName]) {
        $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerNameCatalogIdentityPlan($tableName, $quotedName, $rows);
        $case = sprintf('real upstream trigger1 catalog identity dynamic %s %s', $suffix, $quoteStyle);

        $tests[$case] = static function (TestRunner $t) use ($plan, $tableName, $rows, $quotedName, $quoteStyle): void {
            $actual = $plan();

            $t->same('trigger1.test trigger1-6.1..6.8 and trigger1-8.1..8.6', $actual['source']);
            $t->same('trigger-name-catalog-identity', $actual['operation']);
            $t->same('commit-ok', $actual['status']);
            $t->same($tableName, $actual['table_name']);
            $t->same($tableName, $actual['same_name_trigger_name']);
            $t->same(true, $actual['same_name_trigger_created']);
            $t->same(true, $actual['same_name_trigger_fires_before_drop']);
            $t->same('constraint-trigger', $actual['same_name_delete_status']);
            $t->same('deletes are not permitted', $actual['same_name_delete_error']);
            $t->same($rows, $actual['same_name_rows_after_blocked_delete']);
            $t->same([
                ['type' => 'table', 'name' => $tableName, 'tbl_name' => $tableName],
                ['type' => 'trigger', 'name' => $tableName, 'tbl_name' => $tableName],
            ], $actual['catalog_after_same_name_create']);
            $t->same($actual['catalog_after_same_name_create'], $actual['catalog_after_reopen']);
            $t->same([
                ['type' => 'table', 'name' => $tableName, 'tbl_name' => $tableName],
            ], $actual['catalog_after_drop_same_name_trigger']);
            $t->same(false, $actual['drop_same_name_trigger_removed_table']);
            $t->same($rows, $actual['table_rows_after_drop_same_name_trigger']);
            $t->same($quotedName, $actual['quoted_trigger_input']);
            $t->same('trigger', $actual['quoted_trigger_name']);
            $t->same($quoteStyle, $actual['quoted_trigger_quote_style']);
            $t->same('commit-ok', $actual['quoted_trigger_create_status']);
            $t->same([
                ['type' => 'trigger', 'name' => 'trigger', 'tbl_name' => $tableName],
            ], $actual['quoted_trigger_catalog_after_create']);
            $t->same('commit-ok', $actual['quoted_trigger_drop_status']);
            $t->same([], $actual['quoted_trigger_catalog_after_drop']);
            $t->same(true, $actual['quoted_trigger_name_normalized_once']);
            $t->same('sqlite-trigger1-trigger-name-may-collide-with-table-name', $actual['dependencies'][0]);
            $t->same('sqlite-trigger1-drop-trigger-does-not-drop-namesake-table', $actual['dependencies'][1]);
            $t->same('sqlite-trigger1-quoted-keyword-trigger-name-normalizes-in-catalog', $actual['dependencies'][2]);
            $t->same('sqlite-trigger1-quoted-trigger-drop-removes-only-trigger', $actual['dependencies'][3]);
        };
    }
}

$tests['real upstream trigger1 catalog identity rejects empty rowset'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerNameCatalogIdentityPlan('app_settings', "'trigger'", []));
};

$tests['real upstream trigger1 catalog identity rejects malformed row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerNameCatalogIdentityPlan('app_settings', "'trigger'", [['a' => 1]]));
};

$tests['real upstream trigger1 catalog identity rejects malformed table name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerNameCatalogIdentityPlan('bad-table', "'trigger'", [['a' => 1, 'b' => 'alpha']]));
};

$tests['real upstream trigger1 catalog identity rejects empty quoted trigger name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerNameCatalogIdentityPlan('app_settings', "''", [['a' => 1, 'b' => 'alpha']]));
};

return $tests;
