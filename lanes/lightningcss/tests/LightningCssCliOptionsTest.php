<?php

declare(strict_types=1);

use PortLibs\LightningCSS\CssFormatter;
use PortLibs\LightningCSS\CssMinifier;
use PortLibs\LightningCSS\CustomMediaTransformer;
use PortLibs\LightningCSS\CssModulesTransformer;
use PortLibs\LightningCSS\LightningCssCliOptions;
use PortLibs\LightningCSS\NestingTransformer;
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

$temporaryPath = static function (string $name): string {
    $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'port-libs-lightningcss-cli-' . bin2hex(random_bytes(6));
    if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create temporary directory.');
    }

    return $directory . DIRECTORY_SEPARATOR . $name;
};
$moduleExport = static fn (string $name, bool $isReferenced = false): array => [
    'name' => $name,
    'composes' => [],
    'isReferenced' => $isReferenced,
];

return [
    'lightningcss cli emits formatted css for a valid input file' => static function (TestRunner $t) use ($temporaryPath): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::valid_input_file lines 149-168.
        $inputFile = $temporaryPath('test.css');
        file_put_contents($inputFile, ".foo {\n  border: none;\n}\n");

        $plan = LightningCssCliOptions::planOutputs([$inputFile]);
        $output = (new CssFormatter())->format((string) file_get_contents($plan['inputs'][0]));

        $t->same(null, $plan['outputs'][$inputFile]);
        $t->contains(".foo {\n  border: none;\n}", $output);
    },
    'lightningcss cli accepts an empty input file' => static function (TestRunner $t) use ($temporaryPath): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::empty_input_file lines 170-180.
        $inputFile = $temporaryPath('test.css');
        file_put_contents($inputFile, '');

        $plan = LightningCssCliOptions::planOutputs([$inputFile]);

        $t->same([$inputFile], $plan['inputs']);
        $t->same('', (new CssFormatter())->format((string) file_get_contents($inputFile)));
    },
    'lightningcss cli writes formatted css to output file' => static function (TestRunner $t) use ($temporaryPath): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::output_file_option lines 182-196.
        $inputFile = $temporaryPath('test.css');
        $outputFile = $temporaryPath('test.out');
        file_put_contents($inputFile, ".foo {\n  border: none;\n}\n");
        $plan = LightningCssCliOptions::planOutputs([$inputFile], $outputFile);

        LightningCssCliOptions::writeOutputFile(
            $plan['outputs'][$inputFile] ?? throw new RuntimeException('Missing planned output.'),
            (new CssFormatter())->format((string) file_get_contents($inputFile))
        );

        $t->contains(".foo {\n  border: none;\n}", (string) file_get_contents($outputFile));
    },
    'lightningcss cli creates missing output file directories' => static function (TestRunner $t) use ($temporaryPath): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::output_file_option_create_missing_directories lines 198-218.
        $inputFile = $temporaryPath('test.css');
        $outputFile = dirname($inputFile) . DIRECTORY_SEPARATOR . 'missing' . DIRECTORY_SEPARATOR . 'out.css';
        file_put_contents($inputFile, ".foo {\n  border: none;\n}\n");
        $plan = LightningCssCliOptions::planOutputs([$inputFile], $outputFile);

        LightningCssCliOptions::writeOutputFile(
            $plan['outputs'][$inputFile] ?? throw new RuntimeException('Missing planned output.'),
            (new CssFormatter())->format((string) file_get_contents($inputFile))
        );

        $t->true(is_file($outputFile));
        $t->contains(".foo {\n  border: none;\n}", (string) file_get_contents($outputFile));
    },
    'lightningcss cli minify option emits compact css' => static function (TestRunner $t) use ($temporaryPath): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::minify_option lines 272-284.
        $inputFile = $temporaryPath('test.css');
        file_put_contents($inputFile, ".foo {\n  border: none;\n}\n");
        $plan = LightningCssCliOptions::planOutputs([$inputFile]);

        $t->same('.foo{border:none}', (new CssMinifier())->minify((string) file_get_contents($plan['inputs'][0])));
    },
    'lightningcss cli nesting option lowers nested parent selectors' => static function (TestRunner $t) use ($temporaryPath): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::nesting_option lines 287-313.
        $inputFile = $temporaryPath('test.css');
        file_put_contents($inputFile, '.foo { color: blue; & > .bar { color: red; } }');
        $plan = LightningCssCliOptions::planOutputs([$inputFile], null, null, false, 'defaults');

        $t->same(
            '.foo{color:#00f}.foo>.bar{color:red}',
            (new NestingTransformer())->lower((string) file_get_contents($plan['inputs'][0]))
        );
    },
    'lightningcss cli targets option applies custom media lowering' => static function (TestRunner $t) use ($temporaryPath): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::targets lines 451-475.
        $inputFile = $temporaryPath('test.css');
        file_put_contents($inputFile, <<<'CSS'
@custom-media --foo print;
@media (--foo) {
  .a { color: red }
}
CSS);
        $plan = LightningCssCliOptions::planOutputs([$inputFile], null, null, false, 'last 1 Chrome version');
        $targets = LightningCssCliOptions::targetsForBrowserslistQueries(['last 1 Chrome version']);
        $output = (new CssFormatter())->format(
            (new CustomMediaTransformer())->transform((string) file_get_contents($plan['inputs'][0]))
        );

        $t->same(['chrome' => 120], $targets);
        $t->same(<<<'CSS'
@media print {
  .a {
    color: red;
  }
}
CSS . "\n", $output);
    },
    'lightningcss cli output file preserves checked input is selector' => static function (TestRunner $t) use ($temporaryPath): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::next_66191 lines 794-812.
        $inputFile = $temporaryPath('test.css');
        $outputFile = $temporaryPath('test.out');
        file_put_contents($inputFile, '.cb:is(input:checked) { margin: 3rem; }');
        $plan = LightningCssCliOptions::planOutputs([$inputFile], $outputFile);

        LightningCssCliOptions::writeOutputFile(
            $plan['outputs'][$inputFile] ?? throw new RuntimeException('Missing planned output.'),
            (new CssMinifier())->minify((string) file_get_contents($inputFile))
        );

        $t->contains('.cb:is(input:checked)', (string) file_get_contents($outputFile));
    },
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
    'lightningcss cli writes multiple input outputs into output directory' => static function (TestRunner $t) use ($temporaryPath): void {
        // Complements already represented upstream 22bdda3d tests/cli_integration_tests.rs::multiple_input_files lines 221-244.
        $inputFile = $temporaryPath('test.css');
        $root = dirname($inputFile);
        $inputFile2 = $root . DIRECTORY_SEPARATOR . 'test2.css';
        $outputDir = $root . DIRECTORY_SEPARATOR . 'out';

        file_put_contents($inputFile, ".foo {\n  border: none;\n}\n");
        file_put_contents($inputFile2, ".foo {\n  color: yellow;\n}\n");

        $plan = LightningCssCliOptions::planOutputs([$inputFile, $inputFile2], null, $outputDir);
        foreach ($plan['outputs'] as $input => $output) {
            LightningCssCliOptions::writeOutputFile(
                $output ?? throw new RuntimeException('Missing planned output.'),
                (new CssFormatter())->format((string) file_get_contents($input))
            );
        }

        $t->contains(".foo {\n  border: none;\n}", (string) file_get_contents($outputDir . DIRECTORY_SEPARATOR . 'test.css'));
        $t->contains(".foo {\n  color: #ff0;\n}", (string) file_get_contents($outputDir . DIRECTORY_SEPARATOR . 'test2.css'));
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
    'lightningcss cli emits css modules stdout json payload' => static function (TestRunner $t) use ($moduleExport): void {
        // Pinned upstream 22bdda3d tests/cli_integration_tests.rs::css_modules_stdout lines 357-375.
        $result = (new CssModulesTransformer())->transform(<<<'CSS'
.foo {
  color: red;
}

#id {
  animation: 2s test;
}

@keyframes test {
  from { color: red }
  to { color: yellow }
}

@counter-style circles {
  symbols: A B C;
}

ul {
  list-style: circles;
}

@keyframes fade {
  from { opacity: 0 }
  to { opacity: 1 }
}
CSS);

        $stdout = LightningCssCliOptions::cssModulesStdoutJson($result['code'], $result['exports']);
        $actual = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);

        $t->same("\n", substr($stdout, -1));
        $t->same(['code', 'exports'], array_keys($actual));
        $t->same('.EgL3uq_foo{color:red}#EgL3uq_id{animation:2s EgL3uq_test}@keyframes EgL3uq_test{0%{color:red}to{color:#ff0}}@counter-style EgL3uq_circles{symbols:A B C}ul{list-style:EgL3uq_circles}@keyframes EgL3uq_fade{0%{opacity:0}to{opacity:1}}', $actual['code']);
        $t->same([
            'foo' => $moduleExport('EgL3uq_foo'),
            'id' => $moduleExport('EgL3uq_id'),
            'test' => $moduleExport('EgL3uq_test', true),
            'circles' => $moduleExport('EgL3uq_circles', true),
            'fade' => $moduleExport('EgL3uq_fade'),
        ], $actual['exports']);
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
