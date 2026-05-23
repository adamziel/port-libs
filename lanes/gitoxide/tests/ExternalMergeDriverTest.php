<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\BuiltinDriver;
use PortLibs\Gitoxide\ExternalMergeDriver;
use PortLibs\Gitoxide\MergeDriverChoice;

$tempDir = static function (): string {
    $path = sys_get_temp_dir() . '/gitoxide-ext-driver-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0700) && !is_dir($path)) {
        throw new RuntimeException("Could not create temp directory: {$path}");
    }

    return $path;
};

return [
    'external merge driver selection sorts drivers and preserves builtin fallback' => static function (TestRunner $t): void {
        $drivers = [
            new ExternalMergeDriver('union'),
            new ExternalMergeDriver('to proof it will be sorted'),
            new ExternalMergeDriver('b', recursive: 'for-recursion'),
            new ExternalMergeDriver('for-recursion', recursive: 'should not be looked up'),
        ];

        $t->same(
            ['b', 'for-recursion', 'to proof it will be sorted', 'union'],
            array_map(static fn (ExternalMergeDriver $driver): string => $driver->name, ExternalMergeDriver::sorted($drivers)),
        );

        $set = ExternalMergeDriver::select(BuiltinDriver::ATTRIBUTE_SET, drivers: $drivers);
        $unset = ExternalMergeDriver::select(BuiltinDriver::ATTRIBUTE_UNSET, drivers: $drivers);
        $unspecified = ExternalMergeDriver::select(BuiltinDriver::ATTRIBUTE_UNSPECIFIED, drivers: $drivers);
        $defaultExternal = ExternalMergeDriver::select(
            BuiltinDriver::ATTRIBUTE_UNSPECIFIED,
            drivers: $drivers,
            defaultDriver: 'union',
        );
        $defaultBuiltin = ExternalMergeDriver::select(
            BuiltinDriver::ATTRIBUTE_UNSPECIFIED,
            drivers: $drivers,
            defaultDriver: 'binary',
        );
        $caseMismatch = ExternalMergeDriver::select(
            BuiltinDriver::ATTRIBUTE_UNSPECIFIED,
            drivers: $drivers,
            defaultDriver: 'Binary',
        );
        $custom = ExternalMergeDriver::select(BuiltinDriver::ATTRIBUTE_VALUE, 'b', $drivers);
        $recursive = ExternalMergeDriver::select(BuiltinDriver::ATTRIBUTE_VALUE, 'b', $drivers, isVirtualAncestor: true);

        $t->same(MergeDriverChoice::BUILTIN, $set->kind);
        $t->same(BuiltinDriver::TEXT, $set->builtin);
        $t->same(BuiltinDriver::BINARY, $unset->builtin);
        $t->same(BuiltinDriver::TEXT, $unspecified->builtin);
        $t->same(MergeDriverChoice::EXTERNAL, $defaultExternal->kind);
        $t->same('union', $defaultExternal->name);
        $t->same(BuiltinDriver::BINARY, $defaultBuiltin->builtin);
        $t->same(BuiltinDriver::TEXT, $caseMismatch->builtin);
        $t->same(MergeDriverChoice::EXTERNAL, $custom->kind);
        $t->same('b', $custom->name);
        $t->same('for-recursion', $recursive->name);
        $t->same(BlobMerge::PICK_OURS, $recursive->resolveBinaryWith);
    },
    'external merge driver command expands upstream placeholders without executing' => static function (TestRunner $t) use ($tempDir): void {
        $driver = new ExternalMergeDriver(
            'wp-json',
            'merge-driver %O %A %B %L %P %S %X %Y %F %% %',
        );
        $worktree = $tempDir();
        $command = null;

        try {
            $command = $driver->prepareCommand(
                "base\n",
                "ours\n",
                "theirs\n",
                "wp-content/plugins/acme's/plugin.php",
                "ancestor label",
                "current label",
                "other's label",
                9,
                worktreeDir: $worktree,
            );

            $t->true(str_starts_with($command->ancestorPath, $worktree));
            $t->true(str_starts_with($command->currentPath, $worktree));
            $t->true(str_starts_with($command->otherPath, $worktree));
            $t->same("base\n", file_get_contents($command->ancestorPath));
            $t->same("ours\n", file_get_contents($command->currentPath));
            $t->same("theirs\n", file_get_contents($command->otherPath));
            $t->same(
                "merge-driver {$command->ancestorPath} {$command->currentPath} {$command->otherPath} 9 " .
                "'wp-content/plugins/acme'\\''s/plugin.php' 'ancestor label' 'current label' 'other'\\''s label' %F %% %",
                $command->command,
            );
        } finally {
            $command?->cleanup();
            @rmdir($worktree);
        }
    },
    'external merge driver template quotes path and empty labels like gix prepare external driver' => static function (TestRunner $t): void {
        $expanded = ExternalMergeDriver::expandCommandTemplate(
            'cmd --base=%O --ours=%A --theirs=%B --marker=%L --path=%P --labels=%S:%X:%Y --unknown=%F --literal=%% --trail=%',
            '/tmp/base',
            '/tmp/current',
            '/tmp/other',
            7,
            "wp-content/themes/acme's/theme.json",
            null,
            'current label',
            "their's label",
        );

        $t->same(
            "cmd --base=/tmp/base --ours=/tmp/current --theirs=/tmp/other --marker=7 " .
            "--path='wp-content/themes/acme'\\''s/theme.json' --labels='':'current label':'their'\\''s label' " .
            "--unknown=%F --literal=%% --trail=%",
            $expanded,
        );
    },
    'wordpress external merge driver fixture prepares custom block command' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-external-merge-driver.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-external-merge-driver.php';

        $choice = ExternalMergeDriver::select(
            BuiltinDriver::ATTRIBUTE_VALUE,
            $fixture['attributeValue'],
            $fixture['drivers'],
        );

        $t->same(MergeDriverChoice::EXTERNAL, $choice->kind);
        $t->same('wordpress-json-normalizer', $choice->name);
        $t->same('wordpress-json-normalizer', $summary['driver']);
        $t->true($summary['tempFilesUnderWorktree']);
        $t->same($fixture['current'], $summary['currentBuffer']);
        $t->contains('--marker=11', $summary['command']);
        $t->contains("--path='wp-content/themes/acme/theme.json'", $summary['command']);
        $t->contains('--unknown=%F', $summary['command']);
        $t->contains('A PHP deployment tool can prepare', $summary['wordpressUse']);
    },
];
