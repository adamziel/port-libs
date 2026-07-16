<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/tools/generate-pdf-resource-fixture.php';

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

/**
 * Build an 8–10 MiB, 250-page searchable PDF with 5,000 visible positioned
 * lines. Legal PDF comments fill only the remaining upload-size envelope.
 *
 * @return array{path:string,pages:int,linesPerPage:int,textLines:int,lastLineMarker:string,bytes:int,sha256:string}
 */
$largeSearchablePdfFixture = static function (): array {
    $path = tempnam(sys_get_temp_dir(), 'port-libs-pdf-250-');
    if ($path === false) {
        throw new RuntimeException('Could not allocate the 250-page PDF resource fixture.');
    }
    try {
        return port_libs_generate_searchable_pdf_resource_fixture($path);
    } catch (Throwable $error) {
        @unlink($path);
        throw $error;
    }
};

/**
 * Independently inventory the visible per-line markers without retaining the
 * complete 8–10 MiB source in the test process.
 *
 * @return array{pages:int,textLines:int,minLinesPerPage:int,maxLinesPerPage:int,lastLineMarker:string}
 */
$searchablePdfLineInventory = static function (string $path): array {
    $stream = fopen($path, 'rb');
    if (!is_resource($stream)) {
        throw new RuntimeException('Could not inspect the searchable PDF resource fixture.');
    }
    $pageLines = [];
    $lastLineMarker = '';
    try {
        while (($sourceLine = fgets($stream)) !== false) {
            preg_match_all(
                '/RESOURCE PAGE ([0-9]{3,4}) LINE ([0-9]{2})/',
                $sourceLine,
                $matches,
                PREG_SET_ORDER
            );
            foreach ($matches as $match) {
                $page = (int) $match[1];
                $line = (int) $match[2];
                $pageLines[$page][$line] = true;
                $lastLineMarker = $match[0];
            }
        }
    } finally {
        fclose($stream);
    }
    if ($pageLines === []) {
        throw new RuntimeException('The searchable PDF resource fixture has no visible line inventory.');
    }
    ksort($pageLines, SORT_NUMERIC);
    $lineCounts = array_map('count', $pageLines);

    return [
        'pages' => count($pageLines),
        'textLines' => array_sum($lineCounts),
        'minLinesPerPage' => min($lineCounts),
        'maxLinesPerPage' => max($lineCounts),
        'lastLineMarker' => $lastLineMarker,
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
        $t->same(94099, $result['outputBytes'] ?? null);
        $t->same('e4ee9b3e862ca513a2d90a1950ca4b60d579ad52a772c5e880038fcb5225a1cc', $result['outputSha256'] ?? null);
        $t->true((int) ($result['peakBytes'] ?? PHP_INT_MAX) <= 44 * 1024 * 1024, 'The PDF geometry and prose repair path should remain inside its 48 MiB process budget.');
    },
    'keeps an eight megabyte 250 page searchable PDF below the large import PHP ceiling' => static function (
        TestRunner $t
    ) use ($measure, $largeSearchablePdfFixture, $searchablePdfLineInventory): void {
        $fixture = $largeSearchablePdfFixture();
        $path = $fixture['path'];
        try {
            $sourceBytes = filesize($path);
            $t->true(is_int($sourceBytes) && $sourceBytes >= 8_000_000 && $sourceBytes <= 10_500_000);
            $t->same(250, $fixture['pages']);
            $t->same(20, $fixture['linesPerPage']);
            $t->same(5_000, $fixture['textLines']);
            $t->same($sourceBytes, $fixture['bytes']);
            $t->same('ff2e9236f516800ccc482c5832c6c867a8bbd4997538153953bd50b35dbafdfc', $fixture['sha256']);
            $t->same('RESOURCE PAGE 250 LINE 20', $fixture['lastLineMarker']);
            $inventory = $searchablePdfLineInventory($path);
            $t->same(250, $inventory['pages']);
            $t->same(5_000, $inventory['textLines']);
            $t->same(20, $inventory['minLinesPerPage']);
            $t->same(20, $inventory['maxLinesPerPage']);
            $t->same('RESOURCE PAGE 250 LINE 20', $inventory['lastLineMarker']);
            $run = $measure('128M', [
                'input' => $path,
                'from' => 'pdf',
                'to' => 'wordpress',
                'mode' => 'file',
                'reader-options' => json_encode([
                    'pdfGeometryTables' => true,
                    'pdfRepairProseText' => true,
                ], JSON_THROW_ON_ERROR),
            ], 180);
            $result = $run['result'];

            $t->same(false, $run['timedOut'], $run['raw']);
            $t->same(0, $run['exitCode'], $run['raw']);
            $t->true(is_array($result), 'Expected isolated resource measurements for the 250-page PDF.');
            $t->same($sourceBytes, $result['sourceBytes'] ?? null);
            $t->true((int) ($result['outputBytes'] ?? 0) > 500_000, 'The dense searchable workload should contribute substantial output.');
            $t->true((int) ($result['nodeCount'] ?? 0) >= 10_000, 'The AST must retain the 5,000-line workload across all 250 pages.');
            $t->true((int) ($result['peakBytes'] ?? PHP_INT_MAX) < 128 * 1024 * 1024, 'The import must retain substantial headroom below the 384 MiB contract ceiling.');
            $t->true((float) ($result['totalElapsedMs'] ?? INF) < 120_000, 'The isolated conversion must finish inside a bounded request window.');
        } finally {
            @unlink($path);
        }
    },
];
