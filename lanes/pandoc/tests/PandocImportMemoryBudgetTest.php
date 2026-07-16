<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

/**
 * @param array<string, mixed> $arguments
 * @return array{exitCode: int, raw: string, result: array<string, mixed>|null, timedOut: bool}
 */
$measure = static function (string $memoryLimit, array $arguments, int $timeoutSeconds = 60) use ($root): array {
    $command = [
        PHP_BINARY,
        '-d',
        'memory_limit=' . $memoryLimit,
        $root . '/tools/measure-pandoc-import-memory.php',
    ];
    foreach ($arguments as $name => $value) {
        $command[] = '--' . $name . '=' . (string) $value;
    }

    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $root);
    if (!is_resource($process)) {
        return [
            'exitCode' => 127,
            'raw' => 'Unable to start memory measurement subprocess.',
            'result' => null,
            'timedOut' => false,
        ];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $exitCode = null;
    $timedOut = false;
    $startedAt = microtime(true);

    while (true) {
        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = $status['exitcode'];
            break;
        }

        if (microtime(true) - $startedAt >= $timeoutSeconds) {
            $timedOut = true;
            proc_terminate($process);
            usleep(200000);
            $status = proc_get_status($process);
            if ($status['running']) {
                proc_terminate($process, 9);
            }
            break;
        }

        usleep(100000);
    }

    $stdout .= stream_get_contents($pipes[1]) ?: '';
    $stderr .= stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closedExitCode = proc_close($process);
    if (!is_int($exitCode) || $exitCode < 0) {
        $exitCode = $timedOut ? 124 : $closedExitCode;
    }
    if ($timedOut) {
        $stderr = rtrim($stderr) . "\nMemory measurement timed out after {$timeoutSeconds} seconds.";
    }

    $raw = trim($stdout . ($stderr === '' ? '' : "\n" . $stderr));
    $result = json_decode($raw, true);

    return [
        'exitCode' => $exitCode,
        'raw' => $raw,
        'result' => is_array($result) ? $result : null,
        'timedOut' => $timedOut,
    ];
};

return [
    'keeps a one megabyte HTML import below the compact reader memory budget' => static function (TestRunner $t) use ($measure, $root): void {
        $run = $measure('16M', [
            'input' => $root . '/lanes/readability/fixtures/mozilla/guardian-1/source.html',
            'from' => 'html',
            'to' => 'wordpress',
            'mode' => 'file',
        ]);
        $result = $run['result'];

        $t->same(false, $run['timedOut'], $run['raw']);
        $t->same(0, $run['exitCode'], $run['raw']);
        $t->true(is_array($result), 'Expected JSON memory measurements for the HTML import.');
        $t->same(false, $result['streamOutput'] ?? null);
        $t->same(95096, $result['outputBytes'] ?? null);
        $t->same('2966b3be9c2b8e5d449e70a22b4ffcc32ddf8bd4775d914d821442ccafec89dd', $result['outputSha256'] ?? null);
        $t->true((int) ($result['peakBytes'] ?? PHP_INT_MAX) <= 12 * 1024 * 1024, 'The compact HTML reader should stay well below its 16 MiB process budget.');
    },
    'streams a full EPUB to WordPress blocks within the low-memory budget' => static function (TestRunner $t) use ($measure, $root): void {
        $run = $measure('20M', [
            'input' => $root . '/pandoc-showcase/samples/epub-gutenberg-ulysses-ulysses.epub',
            'from' => 'epub',
            'to' => 'wordpress',
            'mode' => 'file',
            'stream-output' => 'on',
        ]);
        $result = $run['result'];

        $t->same(false, $run['timedOut'], $run['raw']);
        $t->same(0, $run['exitCode'], $run['raw']);
        $t->true(is_array($result), 'Expected JSON memory measurements for the EPUB import.');
        $t->same(true, $result['streamOutput'] ?? null);
        $t->same(1967878, $result['outputBytes'] ?? null);
        $t->same('c86432786c484d8e90d771e9ae45160b0472fc4f1497d2548828c8489fd46930', $result['outputSha256'] ?? null);
        $t->true(array_key_exists('nodeCount', $result), 'The streaming measurement should report its lack of a retained AST.');
        $t->same(null, $result['nodeCount'], 'The streaming path must not retain a document tree for counting.');
        $t->true((int) ($result['peakBytes'] ?? PHP_INT_MAX) <= 16 * 1024 * 1024, 'The streamed EPUB path should stay below 16 MiB.');
    },
    'keeps the repaired geometry PDF path under its shared-hosting memory budget' => static function (TestRunner $t) use ($measure, $root): void {
        $run = $measure('48M', [
            'input' => $root . '/pandoc-showcase/samples/pdf-tracemonkey-tracemonkey.pdf',
            'from' => 'pdf',
            'to' => 'wordpress',
            'mode' => 'file',
            'reader-options' => json_encode([
                'maxTextBytes' => 100000,
                'pdfGeometryTables' => true,
                'pdfRepairProseText' => true,
            ], JSON_THROW_ON_ERROR),
        ]);
        $result = $run['result'];

        $t->same(false, $run['timedOut'], $run['raw']);
        $t->same(0, $run['exitCode'], $run['raw']);
        $t->true(is_array($result), 'Expected JSON memory measurements for the PDF import.');
        $t->same(94043, $result['outputBytes'] ?? null);
        $t->same('7d0265298de9d36c1acc6afae81ca631e7f00f28470756fc27df2e671892eb0c', $result['outputSha256'] ?? null);
        $t->true((int) ($result['peakBytes'] ?? PHP_INT_MAX) <= 44 * 1024 * 1024, 'The PDF geometry and prose repair path should remain inside its 48 MiB process budget.');
    },
];
