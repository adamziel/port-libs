<?php

declare(strict_types=1);

use PortLibs\Gitoxide\BlobMerge;
use PortLibs\Gitoxide\BuiltinDriver;
use PortLibs\Gitoxide\ExternalMergeDriver;
use PortLibs\Gitoxide\ExternalMergeDriverCommand;
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
    'external merge driver temp placeholders preserve supplied worktree spelling' => static function (TestRunner $t) use ($tempDir): void {
        $root = $tempDir();
        $target = $root . DIRECTORY_SEPARATOR . 'actual worktree';
        $linked = $root . DIRECTORY_SEPARATOR . 'linked worktree';
        $worktree = $target;
        $command = null;

        try {
            if (!mkdir($target, 0700) && !is_dir($target)) {
                throw new RuntimeException("Could not create temp directory: {$target}");
            }
            if (function_exists('symlink') && @symlink($target, $linked)) {
                $worktree = $linked;
            }

            $driver = new ExternalMergeDriver(
                'wp-json',
                'merge-driver "%O" "%A" "%B" %P %S %X %Y',
            );
            $command = $driver->prepareCommand(
                'base',
                'ours',
                'theirs',
                "wp-content/themes/acme's/theme.json",
                null,
                'current label',
                "other's label",
                worktreeDir: $worktree,
            );

            $t->true(
                str_starts_with($command->ancestorPath, $worktree . DIRECTORY_SEPARATOR),
                'ancestor tempfile path should preserve the supplied worktree spelling',
            );
            $t->true(
                str_starts_with($command->currentPath, $worktree . DIRECTORY_SEPARATOR),
                'current tempfile path should preserve the supplied worktree spelling',
            );
            $t->true(
                str_starts_with($command->otherPath, $worktree . DIRECTORY_SEPARATOR),
                'other tempfile path should preserve the supplied worktree spelling',
            );
            $t->contains('"' . $command->ancestorPath . '"', $command->command);
            $t->contains('"' . $command->currentPath . '"', $command->command);
            $t->contains('"' . $command->otherPath . '"', $command->command);
            $t->contains("'wp-content/themes/acme'\\''s/theme.json'", $command->command);
            $t->contains("'' 'current label' 'other'\\''s label'", $command->command);
        } finally {
            $command?->cleanup();
            if (is_link($linked)) {
                unlink($linked);
            }
            @rmdir($target);
            @rmdir($root);
        }
    },
    'external merge driver command requires an injected runner for native readback' => static function (TestRunner $t): void {
        $method = new ReflectionMethod(ExternalMergeDriverCommand::class, 'run');
        $parameters = $method->getParameters();

        $t->same(1, count($parameters));
        $t->same('runner', $parameters[0]->getName());
        $t->same(false, $parameters[0]->isOptional());
        $t->same('callable', (string) $parameters[0]->getType());
    },
    'external merge driver reads back current tempfile after successful injected runner' => static function (TestRunner $t) use ($tempDir): void {
        $driver = new ExternalMergeDriver(
            'wp-json',
            'merge-driver %O %A %B %L %P %S %X %Y',
        );
        $worktree = $tempDir();
        $command = null;

        try {
            $command = $driver->prepareCommand(
                "base\n",
                "ours\n",
                "theirs\0\n",
                'wp-content/themes/acme/theme.json',
                'ancestor label',
                'current label',
                'other label',
                7,
                worktreeDir: $worktree,
            );

            $result = $command->run(static function ($prepared): int {
                file_put_contents(
                    $prepared->currentPath,
                    file_get_contents($prepared->ancestorPath) .
                    "--theirs--\n" .
                    file_get_contents($prepared->otherPath),
                );

                return 0;
            });

            $t->same("base\n--theirs--\ntheirs\0\n", $result->content);
            $t->same(\PortLibs\Gitoxide\BlobMergeResult::RESOLUTION_COMPLETE, $result->resolution);
            $t->same(0, $result->conflictCount);
        } finally {
            $command?->cleanup();
            @rmdir($worktree);
        }
    },
    'external merge driver non-zero exit does not read result tempfile' => static function (TestRunner $t) use ($tempDir): void {
        $driver = new ExternalMergeDriver('wp-json', 'merge-driver %A');
        $worktree = $tempDir();
        $command = null;

        try {
            $command = $driver->prepareCommand('base', 'ours', 'theirs', 'theme.json', worktreeDir: $worktree);
            unlink($command->currentPath);

            try {
                $command->readResultFromExitCode(1);
                $t->true(false, 'Expected non-zero external driver status to throw');
            } catch (RuntimeException $exception) {
                $t->contains('non-zero exit status 1', $exception->getMessage());
            }
        } finally {
            $command?->cleanup();
            @rmdir($worktree);
        }
    },
    'external merge driver treats missing resources as empty buffers' => static function (TestRunner $t) use ($tempDir): void {
        $driver = new ExternalMergeDriver('wp-json', 'merge-driver %O %A %B %P');
        $worktree = $tempDir();
        $command = null;

        try {
            $command = $driver->prepareCommand(
                null,
                null,
                null,
                'wp-content/themes/acme/deleted-theme.json',
                worktreeDir: $worktree,
            );

            $t->same('', file_get_contents($command->ancestorPath));
            $t->same('', file_get_contents($command->currentPath));
            $t->same('', file_get_contents($command->otherPath));
            $t->contains("'wp-content/themes/acme/deleted-theme.json'", $command->command);

            $result = $command->run(static function ($prepared): int {
                file_put_contents($prepared->currentPath, "deleted theme merge result\n");

                return 0;
            });

            $t->same("deleted theme merge result\n", $result->content);
        } finally {
            $command?->cleanup();
            @rmdir($worktree);
        }
    },
    'external merge driver rejects too large resources before writing tempfiles' => static function (TestRunner $t) use ($tempDir): void {
        $driver = new ExternalMergeDriver('wp-json', 'merge-driver %A');
        $worktree = $tempDir();

        try {
            try {
                $driver->prepareCommand(
                    'base',
                    'ours',
                    'unspecified',
                    'wp-content/uploads/photo.avif',
                    worktreeDir: $worktree,
                    largeFileThresholdBytes: 9,
                );
                $t->true(false, 'Expected too-large external driver resource to throw');
            } catch (RuntimeException $exception) {
                $t->contains('OtherOrTheirs', $exception->getMessage());
                $t->contains('too large', $exception->getMessage());
            }

            $t->same([], glob($worktree . '/gix-merge-*') ?: []);
            $t->throws(
                InvalidArgumentException::class,
                static fn () => $driver->prepareCommand('base', 'ours', 'theirs', 'theme.json', largeFileThresholdBytes: -1),
            );
        } finally {
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
        $t->same($fixture['expectedMerged'], $summary['mergedBuffer']);
        $t->same(\PortLibs\Gitoxide\BlobMergeResult::RESOLUTION_COMPLETE, $summary['resultResolution']);
        $t->same('', $summary['deletedBaseBuffer']);
        $t->same(true, $summary['tooLargeMediaRejected']);
        $t->contains('OtherOrTheirs', $summary['tooLargeMediaError']);
        $t->contains('--marker=11', $summary['command']);
        $t->contains("--path='wp-content/themes/acme/theme.json'", $summary['command']);
        $t->contains('--unknown=%F', $summary['command']);
        $t->contains('deleted theme.json bases as empty driver tempfiles', $summary['wordpressUse']);
        $t->contains('reject too-large media-like resources', $summary['wordpressUse']);
    },
];
