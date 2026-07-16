<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportBuilder;
use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\ConversionFinalizer;
use PortLibs\MarkerPDF\CorePdfConverter;
use PortLibs\MarkerPDF\MarkerSettings;

$makeTempFile = static function (string $bytes, string $suffix = '.pdf'): string {
    $path = sys_get_temp_dir() . '/markerpdf-core-convert-' . bin2hex(random_bytes(4)) . $suffix;
    if (file_put_contents($path, $bytes) === false) {
        throw new RuntimeException('Unable to write temporary markerPDF core conversion fixture.');
    }

    return $path;
};

$line = static function (string $text, int $index, string $document): array {
    $top = 72.0 + ($index * 16.0);

    return [
        'bbox' => [72.0, $top, 540.0, $top + 12.0],
        'spans' => [[
            'span_id' => $document . '_' . $index,
            'text' => $text,
            'font' => 'Times-Roman',
            'font_weight' => 400.0,
            'font_size' => 12.0,
            'bbox' => [72.0, $top, 540.0, $top + 12.0],
        ]],
    ];
};

$pageFromText = static function (string $text, string $document) use ($line): array {
    $lines = [];
    foreach (preg_split('/\R+/', trim($text)) ?: [] as $index => $part) {
        if ($part !== '') {
            $lines[] = $line($part, $index, $document);
        }
    }

    return [
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'blocks' => [[
            'block_type' => 'Text',
            'bbox' => [72.0, 72.0, 540.0, 740.0],
            'lines' => $lines,
        ]],
    ];
};

return [
    'prepares convert_single_pdf language filetype page and lowres image metadata' => static function (TestRunner $t) use ($makeTempFile): void {
        $path = $makeTempFile("%PDF-1.4\n% core conversion preflight\n%%EOF");
        try {
            $seen = [];
            $result = (new CorePdfConverter())->convertWithSuppliedPages(
                $path,
                [
                    ['pnum' => 1, 'blocks' => []],
                    ['pnum' => 2, 'blocks' => []],
                ],
                [['title' => 'Appendix', 'level' => 1, 'page_index' => 1]],
                static function (array $pages, array $context) use (&$seen): array {
                    $seen = ['pages' => $pages, 'context' => $context];

                    return [
                        'text' => '# Imported appendix',
                        'images' => ['1_image_0.png' => 'PNG'],
                        'metadata' => [
                            'ocr_stats' => ['ocr_pages' => 0, 'ocr_failed' => 0, 'ocr_success' => 0, 'ocr_engine' => 'none'],
                            'block_stats' => ['table' => 0],
                        ],
                    ];
                },
                maxPages: 2,
                startPage: 1,
                metadata: ['languages' => ['Spanish']],
                langs: ['English'],
                batchMultiplier: 3,
                ocrAllPages: false,
                documentPageCount: 5,
                settings: new MarkerSettings(['OCR_ENGINE' => 'ocrmypdf', 'OCR_ALL_PAGES' => true])
            );

            $t->same(['spa'], $seen['context']['langs']);
            $t->same(true, $seen['context']['ocr_all_pages']);
            $t->same(3.0, $seen['context']['batch_multiplier']);
            $t->same('supplied-pages', $seen['context']['stage']);
            $t->same(4, $seen['context']['trimmed_document_page_count']);
            $t->same([
                ['doc_page_index' => 0, 'dpi' => 96],
                ['doc_page_index' => 1, 'dpi' => 96],
            ], $seen['context']['lowres_image_plan']);
            $t->same('pdf', $result['metadata']['filetype']);
            $t->same(['spa'], $result['metadata']['languages']);
            $t->same(2, $result['metadata']['pages']);
            $t->same('Appendix', $result['metadata']['pdf_toc'][0]['title']);
            $t->same(['1_image_0.png' => 'PNG'], $result['images']);
            $t->same('# Imported appendix', $result['text']);
        } finally {
            unlink($path);
        }
    },
    'short-circuits unsupported files before supplied model pipeline runs' => static function (TestRunner $t) use ($makeTempFile): void {
        $path = $makeTempFile("PK\x03\x04fake docx payload", '.pdf');
        try {
            $calls = 0;
            $result = (new CorePdfConverter())->convertWithSuppliedPages(
                $path,
                [],
                [],
                static function () use (&$calls): string {
                    $calls++;

                    return 'should not run';
                }
            );

            $t->same(0, $calls);
            $t->same('', $result['text']);
            $t->same([], $result['images']);
            $t->same('other', $result['metadata']['filetype']);
            $t->same(null, $result['metadata']['languages']);
            $t->same('unsupported-filetype', $result['context']['stage']);
        } finally {
            unlink($path);
        }
    },
    'short-circuits encrypted PDFs before supplied model pipeline runs' => static function (TestRunner $t) use ($makeTempFile): void {
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "5 0 obj\n<< /Filter /Standard /V 4 /R 4 /Length 128 /P -20 /O <00> /U <01> >>\nendobj\n"
            . "trailer << /Root 1 0 R /Encrypt 5 0 R >>\n%%EOF\n";
        $path = $makeTempFile($pdf);
        try {
            $calls = 0;
            $result = (new CorePdfConverter())->convertWithSuppliedPages(
                $path,
                [['pnum' => 0, 'blocks' => []]],
                [],
                static function () use (&$calls): array {
                    $calls++;

                    return ['text' => 'should not run', 'images' => [], 'metadata' => []];
                }
            );

            $t->same(0, $calls);
            $t->same('', $result['text']);
            $t->same([], $result['images']);
            $t->same('pdf', $result['metadata']['filetype']);
            $t->same('encrypted-pdf-preflight', $result['context']['stage']);
            $t->same(true, $result['metadata']['pdf_security']['encrypted']);
            $t->same(false, $result['metadata']['pdf_security']['permission_allows_text_extraction']);
            $t->same(false, $result['context']['pdf_security']['should_queue_models']);
        } finally {
            unlink($path);
        }
    },
    'runs actual CI benchmark excerpts through the core supplied-page boundary' => static function (TestRunner $t) use ($makeTempFile, $pageFromText): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-ci-benchmark-short.php';
        $converter = new CorePdfConverter();
        $finalizer = new ConversionFinalizer();
        $runs = [];

        foreach ($fixture['benchmarkPairs'] as $index => $pair) {
            $path = $makeTempFile("%PDF-1.4\n% " . $pair['document'] . "\n%%EOF");
            try {
                $result = $converter->convertWithSuppliedPages(
                    $path,
                    [$pageFromText($pair['markerExcerpt'], $pair['document'])],
                    [],
                    static fn (array $pages): array => $finalizer->finalizePages(
                        $pages,
                        [],
                        new MarkerSettings(['EXTRACT_IMAGES' => false])
                    ),
                    metadata: ['languages' => ['English']],
                    documentPageCount: 1
                );

                $runs[] = [
                    'method' => 'marker',
                    'document' => $pair['document'],
                    'hypothesis' => $result['text'],
                    'reference' => $pair['referenceExcerpt'],
                    'time' => (float) ($index + 1),
                    'pages' => 1,
                    'chunkLength' => $pair['chunkLength'],
                ];
            } finally {
                unlink($path);
            }
        }

        $report = (new BenchmarkReportBuilder())->build($runs);
        (new BenchmarkReportVerifier())->verifyMarkerScores($report);

        $t->true($report['marker']['files']['multicolcnn.pdf']['score'] > 0.34);
        $t->true($report['marker']['files']['switch_trans.pdf']['score'] > 0.40);
    },
    'rejects malformed supplied core pipeline conversion payloads' => static function (TestRunner $t) use ($makeTempFile): void {
        $path = $makeTempFile("%PDF-1.4\n%%EOF");
        try {
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => (new CorePdfConverter())->convertWithSuppliedPages(
                    $path,
                    [],
                    [],
                    static fn (): array => ['text' => 'bad', 'images' => 'not-an-array', 'metadata' => []]
                )
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => (new CorePdfConverter())->convertWithSuppliedPages(
                    $path,
                    [],
                    [],
                    static fn (): string => 'unused',
                    metadata: ['languages' => 'English']
                )
            );
        } finally {
            unlink($path);
        }
    },
];
