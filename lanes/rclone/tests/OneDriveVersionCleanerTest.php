<?php

declare(strict_types=1);

use PortLibs\Rclone\OneDriveVersionCleaner;

return [
    'onedrive version cleaner keeps current version and deletes older versions in order' => static function (TestRunner $t): void {
        $flow = OneDriveVersionCleaner::deleteOldVersions('exports/site.wxr', [
            ['id' => 'current'],
            ['id' => 'v3'],
            ['id' => 'v2'],
        ]);

        $t->same(3, $flow['fetchedVersions']);
        $t->same('current', $flow['keptVersion']);
        $t->same(['v3', 'v2'], $flow['deletedVersions']);
        $t->same([], $flow['skippedVersions']);
        $t->same(['get-versions', 'delete-version:v3', 'delete-version:v2'], $flow['sequence']);
        $t->same(null, $flow['error']);
    },
    'onedrive version cleaner noops when graph returns fewer than two versions' => static function (TestRunner $t): void {
        $none = OneDriveVersionCleaner::deleteOldVersions('exports/site.wxr', []);
        $one = OneDriveVersionCleaner::deleteOldVersions('exports/site.wxr', [['ID' => 'current']]);

        $t->same(null, $none['keptVersion']);
        $t->same([], $none['deletedVersions']);
        $t->same(['get-versions'], $none['sequence']);
        $t->same(null, $one['keptVersion']);
        $t->same([], $one['deletedVersions']);
        $t->same(['get-versions'], $one['sequence']);
    },
    'onedrive version cleaner stops at first old version delete error' => static function (TestRunner $t): void {
        $flow = OneDriveVersionCleaner::deleteOldVersions('exports/site.wxr', [
            'current',
            'v3',
            'v2',
        ], [
            'deleteErrors' => [
                'v2' => 'Graph delete denied',
            ],
        ]);

        $t->same('current', $flow['keptVersion']);
        $t->same(['v3'], $flow['deletedVersions']);
        $t->same(['get-versions', 'delete-version:v3', 'delete-version:v2'], $flow['sequence']);
        $t->same('Graph delete denied', $flow['error']);
    },
    'onedrive version cleaner dry run records destructive skips without deleting' => static function (TestRunner $t): void {
        $flow = OneDriveVersionCleaner::deleteOldVersions('exports/site.wxr', [
            'current',
            'v3',
            'v2',
        ], [
            'dryRun' => true,
            'deleteErrors' => [
                'v2' => 'would not be observed on dry run',
            ],
        ]);

        $t->same('current', $flow['keptVersion']);
        $t->same([], $flow['deletedVersions']);
        $t->same(['v3', 'v2'], $flow['skippedVersions']);
        $t->same(null, $flow['error']);
    },
    'onedrive version cleaner returns list errors before delete planning' => static function (TestRunner $t): void {
        $flow = OneDriveVersionCleaner::deleteOldVersions('exports/site.wxr', [
            'current',
            'v3',
        ], [
            'listError' => 'Graph versions unavailable',
        ]);

        $t->same(null, $flow['keptVersion']);
        $t->same([], $flow['deletedVersions']);
        $t->same(['get-versions'], $flow['sequence']);
        $t->same('Graph versions unavailable', $flow['error']);
    },
    'wordpress onedrive no versions cleanup preflight preserves current export version' => static function (TestRunner $t): void {
        $example = require __DIR__ . '/../examples/wordpress-onedrive-no-versions-cleanup.php';

        $t->same('onedrive-no-versions-cleanup', $example['source']);
        $t->same('current-wxr', $example['keptVersion']);
        $t->same(['previous-review', 'pre-import'], $example['deletedVersions']);
        $t->same(['previous-review', 'pre-import'], $example['dryRunSkippedVersions']);
        $t->same('Graph delete denied', $example['deleteError']);
        $t->same(false, $example['secretInputsRead']);
    },
];
