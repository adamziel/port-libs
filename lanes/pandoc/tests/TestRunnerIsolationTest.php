<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

return [
    'isolated test runner prevents cross-file WordPress helper collisions' => static function (TestRunner $t) use ($root): void {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($root . '/tools/run-tests.php')
            . ' --isolate'
            . ' lanes/pandoc/tests/PlaygroundConverterPluginTest.php'
            . ' lanes/pandoc/tests/WordPressBlockWriterCoreRoundTripTest.php'
            . ' 2>&1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $report = implode("\n", $output);

        $t->same(0, $exitCode, $report);
        $t->true(
            preg_match('/2 test files, \\d+ assertions, 0 failures/', $report) === 1,
            'Expected the isolated aggregate summary to include both WordPress test files.'
        );
        $t->true(!str_contains($report, 'Cannot redeclare function serialize_block'));
    },
];
