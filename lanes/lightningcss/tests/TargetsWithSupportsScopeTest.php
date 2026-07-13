<?php

declare(strict_types=1);

use PortLibs\LightningCSS\TargetsWithSupportsScope;

return [
    'targets with supports scope maps upstream nested exclusions' => static function (TestRunner $t): void {
        $targets = new TargetsWithSupportsScope();

        $t->same(false, $targets->excludes('OklabColors'), 'upstream src/targets.rs::supports_scope_correctly lines 292-315');
        $t->same(false, $targets->excludes('LabColors'));
        $t->same(false, $targets->excludes('P3Colors'));

        $t->true($targets->enterSupports(['OklabColors', 'LabColors']));
        $t->true($targets->excludes('OklabColors'));
        $t->true($targets->excludes('LabColors'));

        $t->true($targets->enterSupports(['P3Colors', 'LabColors']));
        $t->true($targets->excludes('OklabColors'));
        $t->true($targets->excludes('LabColors'));
        $t->true($targets->excludes('P3Colors'));

        $targets->exitSupports();
        $t->true($targets->excludes('OklabColors'));
        $t->true($targets->excludes('LabColors'));
        $t->same(false, $targets->excludes('P3Colors'));

        $targets->exitSupports();
        $t->same(false, $targets->excludes('OklabColors'));
        $t->same(false, $targets->excludes('LabColors'));
    },
];
