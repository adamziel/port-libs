<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/TestRunner.php';

$root = dirname(__DIR__);
$args = array_slice($argv, 1);
$childMode = false;
$requestedIsolation = false;
$paths = [];
foreach ($args as $arg) {
    if ($arg === '--isolated-child') {
        $childMode = true;
        continue;
    }
    if ($arg === '--isolate') {
        $requestedIsolation = true;
        continue;
    }
    $paths[] = $arg;
}
$args = $paths;

if ($childMode && $args === []) {
    fwrite(STDERR, "Isolated test child requires one focused test path.\n");
    exit(2);
}

$isTestFile = static function (string $path): bool {
    return is_file($path) && str_ends_with(basename($path), 'Test.php');
};

$isInsideRoot = static function (string $path) use ($root): bool {
    return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
};

$selectFocusedFiles = static function (array $paths) use ($root, $isTestFile, $isInsideRoot): array {
    $files = [];
    $errors = [];

    foreach ($paths as $path) {
        if ($path === '' || str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $errors[] = "Focused path must be repo-relative: {$path}";
            continue;
        }

        $absolute = realpath($root . DIRECTORY_SEPARATOR . $path);
        if ($absolute === false || !$isInsideRoot($absolute)) {
            $errors[] = "Focused path does not exist in repository: {$path}";
            continue;
        }

        if (is_dir($absolute)) {
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($absolute, FilesystemIterator::SKIP_DOTS)
                );
            } catch (UnexpectedValueException $exception) {
                $errors[] = "Focused path cannot be read: {$path}";
                continue;
            }

            foreach ($iterator as $fileInfo) {
                $file = $fileInfo->getRealPath();
                if ($file !== false && $isTestFile($file)) {
                    $files[$file] = true;
                }
            }
            continue;
        }

        if ($isTestFile($absolute)) {
            $files[$absolute] = true;
        }
    }

    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, $error . "\n");
        }
        exit(2);
    }

    $selected = array_keys($files);
    sort($selected);

    if ($selected === []) {
        fwrite(STDERR, "No PHP test files selected from focused arguments.\n");
        exit(2);
    }

    return $selected;
};

if ($args === [] && !$childMode) {
    $lockDir = $root . '/.upstream-cache';
    if (!is_dir($lockDir)) {
        mkdir($lockDir, 0777, true);
    }

    $lockHandle = fopen($lockDir . '/run-tests.lock', 'c');
    if ($lockHandle === false) {
        throw new RuntimeException('Unable to open root test lock file');
    }

    if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
        fwrite(STDERR, "Another root test run is active; waiting for {$lockDir}/run-tests.lock\n");
        if (!flock($lockHandle, LOCK_EX)) {
            throw new RuntimeException('Unable to acquire root test lock');
        }
    }

    $files = glob($root . '/lanes/*/tests/*Test.php') ?: [];
    sort($files);
} else {
    $files = $selectFocusedFiles($args);
    if (!$childMode) {
        fwrite(STDOUT, 'Focused test run: ' . count($files) . " selected test files (root lock skipped)\n");
    }
}

$shouldIsolateFiles = !$childMode && ($requestedIsolation || $args === []);
if ($shouldIsolateFiles) {
    $assertions = 0;
    $failures = 0;

    foreach ($files as $file) {
        $relative = str_replace($root . '/', '', $file);
        $outputPath = tempnam(sys_get_temp_dir(), 'port-libs-test-child-');
        if (!is_string($outputPath)) {
            $failures++;
            fwrite(STDOUT, "FAIL unable to create isolated output file ({$relative})\n");
            continue;
        }

        $process = proc_open(
            [
                PHP_BINARY,
                '-d',
                'memory_limit=512M',
                __FILE__,
                '--isolated-child',
                $relative,
            ],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $outputPath, 'w'],
                2 => ['file', $outputPath, 'a'],
            ],
            $pipes,
            $root
        );
        if (!is_resource($process)) {
            unlink($outputPath);
            $failures++;
            fwrite(STDOUT, "FAIL unable to start isolated test child ({$relative})\n");
            continue;
        }

        fclose($pipes[0]);
        $exitCode = proc_close($process);
        $output = file_get_contents($outputPath);
        unlink($outputPath);
        $output = is_string($output) ? $output : '';
        if ($output !== '') {
            fwrite(STDOUT, $output);
            if (!str_ends_with($output, "\n")) {
                fwrite(STDOUT, "\n");
            }
        }

        $summary = [];
        if (preg_match('/(?:^|\\R)1 test files, (\\d+) assertions, (\\d+) failures\\s*$/', $output, $summary) !== 1) {
            $failures++;
            fwrite(STDOUT, "FAIL isolated child did not report a test summary ({$relative})\n");
            continue;
        }

        $assertions += (int) $summary[1];
        $childFailures = (int) $summary[2];
        $failures += $childFailures;
        if ($exitCode !== 0 && $childFailures === 0) {
            $failures++;
            fwrite(STDOUT, "FAIL isolated child exited {$exitCode} without a reported test failure ({$relative})\n");
        }
    }

    $count = count($files);
    fwrite(STDOUT, "\n{$count} test files, {$assertions} assertions, {$failures} failures\n");
    exit($failures === 0 ? 0 : 1);
}

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
