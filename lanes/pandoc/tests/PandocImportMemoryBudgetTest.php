<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);

/**
 * @param array<string, mixed> $arguments
 * @return array<string, mixed>
 */
$measure = static function (string $memoryLimit, array $arguments) use ($root): array {
    $command = escapeshellarg(PHP_BINARY)
        . ' -d memory_limit=' . escapeshellarg($memoryLimit)
        . ' ' . escapeshellarg($root . '/tools/measure-pandoc-import-memory.php');
    foreach ($arguments as $name => $value) {
        $command .= ' --' . $name . '=' . escapeshellarg((string) $value);
    }
    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);
    $raw = implode("\n", $output);
    $result = json_decode($raw, true);

    return [
        'exitCode' => $exitCode,
        'raw' => $raw,
        'result' => is_array($result) ? $result : null,
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

        $t->same(0, $run['exitCode'], $run['raw']);
        $t->true(is_array($result), 'Expected JSON memory measurements for the PDF import.');
        $t->same(93120, $result['outputBytes'] ?? null);
        $t->same('744780ccadf3d82ea1b521c155775e5de90a5c1ba2211f2143602b28ce24ab7d', $result['outputSha256'] ?? null);
        $t->true((int) ($result['peakBytes'] ?? PHP_INT_MAX) <= 44 * 1024 * 1024, 'The PDF geometry and prose repair path should remain inside its 48 MiB process budget.');
    },
];
