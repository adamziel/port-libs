<?php

declare(strict_types=1);

use PortLibs\LightningCSS\LightningCssCliOptions;

return [
    'lightningcss cli rejects multiple inputs with one output file' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::multiple_input_files_out_file lines 247-258.
        try {
            LightningCssCliOptions::planOutputs(['test.css', 'test2.css'], '/tmp/test.out');
        } catch (InvalidArgumentException $exception) {
            $t->same('Cannot use --output-file with multiple input files.', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected multiple-input --output-file rejection.');
    },
    'lightningcss cli rejects multiple inputs to stdout' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::multiple_input_files_stdout lines 261-270.
        try {
            LightningCssCliOptions::planOutputs(['test.css', 'test2.css']);
        } catch (InvalidArgumentException $exception) {
            $t->same('Multiple input files require --output-dir.', $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected multiple-input stdout rejection.');
    },
    'lightningcss cli maps multiple inputs to output directory basenames' => static function (TestRunner $t): void {
        // Complements upstream 22bdda3d tests/cli_integration_tests.rs::multiple_input_files lines 221-244.
        $plan = LightningCssCliOptions::planOutputs(
            ['/tmp/in/test.css', '/tmp/in/test2.css'],
            null,
            '/tmp/out'
        );

        $t->same([
            '/tmp/in/test.css' => '/tmp/out' . DIRECTORY_SEPARATOR . 'test.css',
            '/tmp/in/test2.css' => '/tmp/out' . DIRECTORY_SEPARATOR . 'test2.css',
        ], $plan['outputs']);
    },
    'lightningcss cli rejects browserslist and targets together' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::browserslist_targets_exclusive lines 496-507.
        try {
            LightningCssCliOptions::planOutputs(['test.css'], null, null, true, 'defaults');
        } catch (InvalidArgumentException $exception) {
            $t->same("The argument '--targets <TARGETS>' cannot be used with '--browserslist'", $exception->getMessage());
            return;
        }

        throw new RuntimeException('Expected mutually exclusive browserslist/targets rejection.');
    },
    'lightningcss cli infers css modules json beside output css' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::css_modules_infer_output_file lines 315-334.
        $t->same('/tmp/out.json', LightningCssCliOptions::cssModulesJsonOutputPath(null, '/tmp/out.css'));
        $t->same('out.json', LightningCssCliOptions::cssModulesJsonOutputPath(null, 'out.css'));
    },
    'lightningcss cli honors explicit css modules json target' => static function (TestRunner $t): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::css_modules_output_target_option lines 337-354.
        $t->same('/tmp/module.json', LightningCssCliOptions::cssModulesJsonOutputPath('/tmp/module.json', '/tmp/out.css'));
    },
];
