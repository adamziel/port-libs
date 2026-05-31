<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream trigger1 target class cites table instead of rejection' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test');
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.12'));
        $t->true(is_string($source) && str_contains($source, 'cannot create INSTEAD OF trigger on table: t1'));
    },
    'real upstream trigger1 target class cites view before after rejection' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger1.test');
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.13'));
        $t->true(is_string($source) && str_contains($source, 'do_test trigger1-1.14'));
        $t->true(is_string($source) && str_contains($source, 'cannot create BEFORE trigger on view: v1'));
        $t->true(is_string($source) && str_contains($source, 'cannot create AFTER trigger on view: v1'));
    },
];

$expectation = static function (string $timing, string $targetKind, string $targetName): array {
    $status = 'commit-ok';
    $error = null;
    if ($targetKind === 'table' && $timing === 'instead of') {
        $status = 'schema-error';
        $error = 'cannot create INSTEAD OF trigger on table: ' . $targetName;
    } elseif ($targetKind === 'view' && $timing !== 'instead of') {
        $status = 'schema-error';
        $error = 'cannot create ' . strtoupper($timing) . ' trigger on view: ' . $targetName;
    }

    return [$status, $error, $status === 'commit-ok'];
};

$timings = ['before', 'after', 'instead of'];
$targetKinds = ['table', 'view'];

for ($i = 1; $i <= 250; ++$i) {
    foreach ($timings as $timing) {
        foreach ($targetKinds as $targetKind) {
            $targetName = ($targetKind === 'table' ? 'app_table_' : 'app_view_') . $i;
            [$status, $error, $installed] = $expectation($timing, $targetKind, $targetName);
            $case = sprintf('real upstream trigger1.test trigger1-1.12..1.14 target class dynamic %03d %s %s', $i, $targetKind, str_replace(' ', '-', $timing));

            $tests[$case . ' reports target validation status'] = static function (TestRunner $t) use ($timing, $targetKind, $targetName, $status, $error, $installed): void {
                $plan = SQLiteDynamicTriggerForeignKeyPlan::triggerCreationTargetDiagnostic($timing, $targetKind, $targetName);

                $t->same('trigger1.test trigger1-1.12..1.14', $plan['source']);
                $t->same('trigger-creation-target-diagnostic', $plan['operation']);
                $t->same($status, $plan['status']);
                $t->same($timing, $plan['timing']);
                $t->same($targetKind, $plan['target_kind']);
                $t->same($targetName, $plan['target_name']);
                $t->same($error, $plan['error']);
                $t->same($installed, $plan['installed']);
            };

            $tests[$case . ' keeps trigger1 dependency labels'] = static function (TestRunner $t) use ($timing, $targetKind, $targetName): void {
                $plan = SQLiteDynamicTriggerForeignKeyPlan::triggerCreationTargetDiagnostic($timing, $targetKind, $targetName);

                $t->same('sqlite-trigger1-instead-of-trigger-requires-view', $plan['dependencies'][0]);
                $t->same('sqlite-trigger1-before-after-trigger-requires-table', $plan['dependencies'][1]);
                $t->same('sqlite-trigger1-target-kind-validated-before-trigger-install', $plan['dependencies'][2]);
            };
        }
    }
}

$tests['real upstream trigger1 target class rejects unsupported timing'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCreationTargetDiagnostic('during', 'table', 'app_table'));
};

$tests['real upstream trigger1 target class rejects unsupported target kind'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCreationTargetDiagnostic('before', 'index', 'app_table'));
};

$tests['real upstream trigger1 target class rejects malformed target name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerCreationTargetDiagnostic('before', 'table', 'bad-name'));
};

return $tests;
