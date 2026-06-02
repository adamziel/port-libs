<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BatchConverter;
use PortLibs\MarkerPDF\OutputWriter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-batch-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf batch test folder.');
    }

    return $path;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            unlink($child);
        }
    }

    rmdir($path);
};

$writePdf = static function (string $path, string $text): void {
    $content = 'BT /F1 12 Tf 72 720 Td (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text) . ') Tj ET';
    $pdf = "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
    file_put_contents($path, $pdf);
};

return [
    'plans convert.py chunk tasks with ceil chunking max slicing and basename metadata' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePdf): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            foreach (['a.pdf', 'b.pdf', 'c.pdf', 'd.pdf', 'e.pdf'] as $name) {
                $writePdf($input . DIRECTORY_SEPARATOR . $name, 'Document ' . $name);
            }

            $tasks = (new BatchConverter())->planTasks(
                $input,
                $output,
                1,
                2,
                1,
                ['d.pdf' => ['languages' => ['Spanish']]],
                50
            );

            $t->same(1, count($tasks));
            $t->same('d.pdf', basename($tasks[0]['filepath']));
            $t->same($output, $tasks[0]['out_folder']);
            $t->same(['languages' => ['Spanish']], $tasks[0]['metadata']);
            $t->same(50, $tasks[0]['min_length']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'loads convert.py metadata_file json keyed by basename' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $input = $makeTempDir();
        try {
            $metadataPath = $input . DIRECTORY_SEPARATOR . 'metadata.json';
            file_put_contents($metadataPath, json_encode([
                'annual-report.pdf' => ['languages' => ['Spanish'], 'title' => 'Annual Report'],
            ], JSON_THROW_ON_ERROR));

            $batch = new BatchConverter();
            $metadata = $batch->loadMetadataFile($metadataPath);

            $t->same(['Spanish'], $metadata['annual-report.pdf']['languages']);
            $t->same('Annual Report', $metadata['annual-report.pdf']['title']);
            $t->same([], $batch->loadMetadataFile(null));
            $t->throws(InvalidArgumentException::class, static fn (): array => $batch->loadMetadataFile($input . DIRECTORY_SEPARATOR . 'missing.json'));
        } finally {
            $removeTree($input);
        }
    },
    'skips existing unsupported and short files before invoking supplied conversion' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePdf): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            $writePdf($input . DIRECTORY_SEPARATOR . 'existing.pdf', 'Already converted');
            $writePdf($input . DIRECTORY_SEPARATOR . 'short.pdf', 'Tiny');
            file_put_contents($input . DIRECTORY_SEPARATOR . 'archive.pdf', "PK\x03\x04not a pdf");

            $writer = new OutputWriter();
            $writer->saveMarkdown($output, 'existing.pdf', 'old output', [], []);

            $calls = 0;
            $converter = static function () use (&$calls): string {
                $calls++;
                return 'should not run';
            };
            $batch = new BatchConverter();

            $t->same('skipped-existing', $batch->processFile($input . DIRECTORY_SEPARATOR . 'existing.pdf', $output, null, 5, $converter)['status']);
            $t->same('skipped-unsupported-filetype', $batch->processFile($input . DIRECTORY_SEPARATOR . 'archive.pdf', $output, null, 5, $converter)['status']);

            $short = $batch->processFile($input . DIRECTORY_SEPARATOR . 'short.pdf', $output, null, 10, $converter);
            $t->same('skipped-short-text', $short['status']);
            $t->same(4, $short['text_length']);
            $t->same(0, $calls);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'saves non-empty upstream tuple conversion output and skips empty output' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePdf): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            $writePdf($input . DIRECTORY_SEPARATOR . 'wp-import.pdf', 'WordPress import');
            $writePdf($input . DIRECTORY_SEPARATOR . 'empty.pdf', 'Empty');

            $batch = new BatchConverter();
            $converted = $batch->processFile(
                $input . DIRECTORY_SEPARATOR . 'wp-import.pdf',
                $output,
                ['languages' => ['English']],
                null,
                static fn (string $filepath, ?array $metadata): array => [
                    "<!-- wp:paragraph -->\n<p>Imported " . basename($filepath) . "</p>\n<!-- /wp:paragraph -->",
                    ['0_image_0.png' => 'PNG'],
                    ['languages' => $metadata['languages'] ?? []],
                ]
            );
            $empty = $batch->processFile(
                $input . DIRECTORY_SEPARATOR . 'empty.pdf',
                $output,
                null,
                null,
                static fn (): array => ['', [], []]
            );

            $t->same('converted', $converted['status']);
            $t->true(is_file($converted['markdown']));
            $t->contains('Imported wp-import.pdf', (string) file_get_contents($converted['markdown']));
            $t->contains('"languages": [', (string) file_get_contents(dirname($converted['markdown']) . DIRECTORY_SEPARATOR . 'wp-import_meta.json'));
            $t->same(['0_image_0.png'], $converted['images']);
            $t->same('skipped-empty-output', $empty['status']);
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'captures convert.py process_single_pdf errors without writing WordPress markdown' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePdf): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            $pdfPath = $input . DIRECTORY_SEPARATOR . 'broken.pdf';
            $writePdf($pdfPath, 'Broken model import');

            $result = (new BatchConverter())->processFile(
                $pdfPath,
                $output,
                ['languages' => ['English']],
                null,
                static fn (): string => throw new RuntimeException('surya model boundary unavailable')
            );

            $t->same('error', $result['status']);
            $t->same('broken.pdf', $result['filename']);
            $t->same('surya model boundary unavailable', $result['error']);
            $t->same(false, $result['writes_markdown']);
            $t->same(true, $result['error_output']['review_only']);
            $t->contains('Error converting ' . $pdfPath . ': surya model boundary unavailable', $result['error_output']['message_line']);
            $t->contains('RuntimeException: surya model boundary unavailable', $result['error_output']['traceback']);
            $t->same(false, is_file($output . DIRECTORY_SEPARATOR . 'broken' . DIRECTORY_SEPARATOR . 'broken.md'));
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
    'processes a WordPress batch with basename metadata and convert.py summary counts' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writePdf): void {
        $input = $makeTempDir();
        $output = $makeTempDir();
        try {
            $writePdf($input . DIRECTORY_SEPARATOR . 'alpha.pdf', 'Alpha import');
            $writePdf($input . DIRECTORY_SEPARATOR . 'beta.pdf', 'Beta import');
            $metadataPath = $output . DIRECTORY_SEPARATOR . 'metadata.json';
            file_put_contents($metadataPath, json_encode([
                'alpha.pdf' => ['title' => 'Alpha Data Liberation'],
                'beta.pdf' => ['title' => 'Beta Data Liberation'],
            ], JSON_THROW_ON_ERROR));

            $seenMetadata = [];
            $batch = new BatchConverter();
            $summary = $batch->processFolder(
                $input,
                $output,
                static function (string $filepath, ?array $metadata) use (&$seenMetadata): array {
                    $seenMetadata[basename($filepath)] = $metadata;

                    return [
                        'text' => "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($metadata['title'] ?? basename($filepath), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->",
                        'images' => [],
                        'metadata' => ['title' => $metadata['title'] ?? basename($filepath)],
                    ];
                },
                metadataByFilename: $batch->loadMetadataFile($metadataPath)
            );

            $t->same(2, $summary['converted']);
            $t->same(0, $summary['skipped']);
            $t->same(0, $summary['errors']);
            $t->same('Alpha Data Liberation', $seenMetadata['alpha.pdf']['title']);
            $t->true(is_file($output . DIRECTORY_SEPARATOR . 'beta' . DIRECTORY_SEPARATOR . 'beta.md'));
        } finally {
            $removeTree($input);
            $removeTree($output);
        }
    },
];
