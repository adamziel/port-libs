<?php

declare(strict_types=1);

use PortLibs\LightningCSS\LightningCssCliOptions;
use PortLibs\LightningCSS\TransitionPrefixer;

$resolveBrowserslist = static function (
    array $files,
    array $environment = [],
    string $currentDirectory = '/theme'
): array {
    return LightningCssCliOptions::resolveBrowserslistConfig(
        $currentDirectory,
        $environment,
        static fn (string $path): bool => array_key_exists($path, $files),
        static fn (string $path): string => $files[$path]
    );
};

$prefixBorderRadiusFromBrowserslist = static function (array $resolution, string $css = '* { border-radius: 1rem; }'): string {
    $targets = LightningCssCliOptions::targetsForBrowserslistQueries($resolution['queries']);

    return (new TransitionPrefixer())->prefixForTargets($css, $targets);
};

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
    'lightningcss cli applies browserslist defaults without discovered config' => static function (TestRunner $t) use ($resolveBrowserslist, $prefixBorderRadiusFromBrowserslist): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::browserslist_defaults lines 517-540.
        $resolution = $resolveBrowserslist([]);

        $t->same('defaults', $resolution['source']);
        $t->same(['defaults'], $resolution['queries']);
        $t->same('*{border-radius:1rem}', $prefixBorderRadiusFromBrowserslist(
            $resolution,
            '* { -webkit-border-radius: 1rem; border-radius: 1rem; }'
        ));
    },
    'lightningcss cli reads browserslist from BROWSERSLIST env' => static function (TestRunner $t) use ($resolveBrowserslist, $prefixBorderRadiusFromBrowserslist): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::browserslist_env_config lines 543-570.
        $resolution = $resolveBrowserslist([], ['BROWSERSLIST' => 'safari 4']);

        $t->same('env:BROWSERSLIST', $resolution['source']);
        $t->same(['safari 4'], $resolution['queries']);
        $t->same('*{-webkit-border-radius:1rem;border-radius:1rem}', $prefixBorderRadiusFromBrowserslist($resolution));
    },
    'lightningcss cli reads browserslist from BROWSERSLIST_CONFIG file' => static function (TestRunner $t) use ($resolveBrowserslist, $prefixBorderRadiusFromBrowserslist): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::browserslist_env_config_file lines 572-607.
        $resolution = $resolveBrowserslist(
            ['/theme/config' => "safari 4\n"],
            ['BROWSERSLIST_CONFIG' => '/theme/config']
        );

        $t->same('/theme/config', $resolution['source']);
        $t->same(['safari 4'], $resolution['queries']);
        $t->same('*{-webkit-border-radius:1rem;border-radius:1rem}', $prefixBorderRadiusFromBrowserslist($resolution));
    },
    'lightningcss cli discovers browserslist file' => static function (TestRunner $t) use ($resolveBrowserslist, $prefixBorderRadiusFromBrowserslist): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::browserslist_config_discovery lines 609-642.
        $resolution = $resolveBrowserslist(['/theme/browserslist' => "safari 4\n"]);

        $t->same('/theme/browserslist', $resolution['source']);
        $t->same(['safari 4'], $resolution['queries']);
        $t->same('*{-webkit-border-radius:1rem;border-radius:1rem}', $prefixBorderRadiusFromBrowserslist($resolution));
    },
    'lightningcss cli discovers browserslistrc file' => static function (TestRunner $t) use ($resolveBrowserslist, $prefixBorderRadiusFromBrowserslist): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::browserslist_rc_discovery lines 644-677.
        $resolution = $resolveBrowserslist(['/theme/.browserslistrc' => "safari 4\n"]);

        $t->same('/theme/.browserslistrc', $resolution['source']);
        $t->same(['safari 4'], $resolution['queries']);
        $t->same('*{-webkit-border-radius:1rem;border-radius:1rem}', $prefixBorderRadiusFromBrowserslist($resolution));
    },
    'lightningcss cli discovers package json browserslist section' => static function (TestRunner $t) use ($resolveBrowserslist, $prefixBorderRadiusFromBrowserslist): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::browserslist_package_discovery lines 679-714.
        $resolution = $resolveBrowserslist(['/theme/package.json' => '{"browserslist":"safari 4"}']);

        $t->same('/theme/package.json', $resolution['source']);
        $t->same(['safari 4'], $resolution['queries']);
        $t->same('*{-webkit-border-radius:1rem;border-radius:1rem}', $prefixBorderRadiusFromBrowserslist($resolution));
    },
    'lightningcss cli applies browserslist environment from NODE_ENV' => static function (TestRunner $t) use ($resolveBrowserslist, $prefixBorderRadiusFromBrowserslist): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::browserslist_environment_from_node_env lines 716-753.
        $resolution = $resolveBrowserslist(
            ['/theme/browserslist' => "last 1 Chrome version\n\n[legacy]\nsafari 4\n"],
            ['NODE_ENV' => 'legacy']
        );

        $t->same('legacy', $resolution['environment']);
        $t->same(['safari 4'], $resolution['queries']);
        $t->same('*{-webkit-border-radius:1rem;border-radius:1rem}', $prefixBorderRadiusFromBrowserslist($resolution));
    },
    'lightningcss cli applies browserslist environment from BROWSERSLIST_ENV' => static function (TestRunner $t) use ($resolveBrowserslist, $prefixBorderRadiusFromBrowserslist): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::browserslist_environment_from_browserslist_env lines 755-792.
        $resolution = $resolveBrowserslist(
            ['/theme/browserslist' => "last 1 Chrome version\n\n[legacy]\nsafari 4\n"],
            ['NODE_ENV' => 'production', 'BROWSERSLIST_ENV' => 'legacy']
        );

        $t->same('legacy', $resolution['environment']);
        $t->same(['safari 4'], $resolution['queries']);
        $t->same('*{-webkit-border-radius:1rem;border-radius:1rem}', $prefixBorderRadiusFromBrowserslist($resolution));
    },
];
