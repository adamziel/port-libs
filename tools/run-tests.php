<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestRunner.php';

$root = dirname(__DIR__);
$files = glob($root . '/lanes/*/tests/*Test.php') ?: [];
sort($files);

$runner = new TestRunner();

foreach ($files as $file) {
    $tests = require $file;
    if (!is_array($tests)) {
        throw new RuntimeException("Test file did not return an array: {$file}");
    }
    $runner->runTests($tests, str_replace($root . '/', '', $file));
}

$count = count($files);
fwrite(STDOUT, "\n{$count} test files, {$runner->assertions()} assertions, {$runner->failures()} failures\n");
exit($runner->failures() === 0 ? 0 : 1);

