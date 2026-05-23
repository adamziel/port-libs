<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\BenchmarkRunner;
use PortLibs\MarkerPDF\BenchmarkScorer;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-supplied-document-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf supplied document folder.');
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

$pdftextPage = static function (int $page, array $lines): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [[
            'lines' => array_map(
                static fn (array $line): array => [
                    'bbox' => $line['bbox'],
                    'spans' => [[
                        'text' => $line['text'],
                        'bbox' => $line['bbox'],
                        'font' => [
                            'name' => $line['font'] ?? 'Times-Roman',
                            'flags' => $line['flags'] ?? 0,
                            'weight' => $line['weight'] ?? 400,
                            'size' => $line['size'] ?? 12,
                        ],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$ciPdftextPage = static function (string $text) use ($pdftextPage): array {
    $lines = [];
    foreach (preg_split('/\R+/', trim($text)) ?: [] as $index => $part) {
        if ($part === '') {
            continue;
        }
        $top = 72.0 + ($index * 16.0);
        $lines[] = [
            'text' => $part,
            'bbox' => [72.0, $top, 540.0, $top + 12.0],
        ];
    }

    return $pdftextPage(0, $lines);
};

return [
    'converts supplied pdftext layout order and table dictionaries into markdown' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-supplied-document-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% supplied document pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'WordPress import packet', 'bbox' => [72.0, 48.0, 380.0, 72.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Second column media checklist.', 'bbox' => [330.0, 110.0, 560.0, 126.0]],
                ['text' => 'First column import summary.', 'bbox' => [72.0, 110.0, 280.0, 126.0]],
                ['text' => 'Feature Status', 'bbox' => [72.0, 190.0, 360.0, 212.0]],
            ]);

            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 48.0, 380.0, 72.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 100.0, 280.0, 140.0]],
                    ['label' => 'Text', 'bbox' => [330.0, 100.0, 560.0, 140.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 180.0, 360.0, 240.0]],
                ],
            ];
            $order = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 0, 'bbox' => [72.0, 48.0, 380.0, 72.0]],
                    ['position' => 1, 'bbox' => [72.0, 100.0, 280.0, 140.0]],
                    ['position' => 2, 'bbox' => [330.0, 100.0, 560.0, 140.0]],
                    ['position' => 3, 'bbox' => [72.0, 180.0, 360.0, 240.0]],
                ],
            ];
            $recognizedTable = [
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 600.0, 40.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 40.0, 600.0, 80.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 80.0]],
                    ['col_id' => 1, 'bbox' => [300.0, 0.0, 600.0, 80.0]],
                ],
                'cells' => [
                    ['bbox' => [10.0, 5.0, 290.0, 30.0], 'text' => 'Feature'],
                    ['bbox' => [310.0, 5.0, 590.0, 30.0], 'text' => 'Status'],
                    ['bbox' => [10.0, 45.0, 290.0, 70.0], 'text' => 'Images'],
                    ['bbox' => [310.0, 45.0, 590.0, 70.0], 'text' => 'Needs review'],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'toc' => [['title' => 'WordPress import packet', 'level' => 1, 'page_index' => 0]],
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'order_results' => [$order],
                    'recognized_tables' => [$recognizedTable],
                    'table_text_lines' => [['blocks' => []]],
                    'batch_multiplier' => 2,
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('# Wordpress Import Packet', $result['text']);
            $t->contains('First column import summary.', $result['text']);
            $t->true(strpos($result['text'], 'First column import summary.') < strpos($result['text'], 'Second column media checklist.'));
            $t->contains('| Feature | Status       |', $result['text']);
            $t->contains('| Images  | Needs review |', $result['text']);
            $t->same(['layout', 'order', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same(1, $result['metadata']['block_stats']['table']);
            $t->same(1, $result['metadata']['inserted_tables']);
            $t->same([0], $result['metadata']['page_range']);
            $t->same(2.0, $result['metadata']['context']['batch_multiplier']);
        } finally {
            unlink($path);
        }
    },
    'converts a fuller multicolcnn supplied dictionary excerpt with upstream finalization metadata' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-multicolcnn-supplied-document.php';
        $path = sys_get_temp_dir() . '/markerpdf-multicolcnn-supplied-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% multicolcnn supplied dictionary fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                $fixture['pdftextPages'],
                $fixture['options'],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $score = (new BenchmarkScorer())->scoreText(
                $result['text'],
                $fixture['referenceExcerpt'],
                $fixture['chunkLength']
            );

            $t->same($fixture['expectedMarkdown'], $result['text']);
            $t->contains('Perspective-Free Counting', $result['text']);
            $t->true($score > $fixture['scoreThreshold']);
            $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries']);
            $t->same([
                'ocr_pages' => 0,
                'ocr_failed' => 0,
                'ocr_success' => 0,
                'ocr_engine' => 'none',
            ], $result['metadata']['ocr_stats']);
            $t->same(0, $result['metadata']['block_stats']['table']);
            $t->same(0, $result['metadata']['block_stats']['code']);
            $t->same(0, $result['metadata']['block_stats']['header_footer']);
            $t->same([0], $result['metadata']['page_range']);
            $t->same(1, $result['metadata']['context']['lowres_image_count']);
            $t->same('Abstract', $result['metadata']['pdf_toc'][0]['title']);
            $t->same('An Aggregated Multicolumn Dilated Convolution Network For Perspective-Free Counting', $result['metadata']['computed_toc'][0]['title']);
        } finally {
            unlink($path);
        }
    },
    'converts a fuller switch transformer supplied dictionary excerpt with styled spans' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-switch-transformers-supplied-document.php';
        $path = sys_get_temp_dir() . '/markerpdf-switch-transformers-supplied-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% switch transformer supplied dictionary fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                $fixture['pdftextPages'],
                $fixture['options'],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $score = (new BenchmarkScorer())->scoreText(
                $result['text'],
                $fixture['referenceExcerpt'],
                $fixture['chunkLength']
            );

            $t->same($fixture['expectedMarkdown'], $result['text']);
            $t->contains('select *different* parameters', $result['text']);
            $t->true($score > $fixture['scoreThreshold']);
            $t->same(['layout', 'order'], $result['metadata']['supplied_boundaries']);
            $t->same([
                'ocr_pages' => 0,
                'ocr_failed' => 0,
                'ocr_success' => 0,
                'ocr_engine' => 'none',
            ], $result['metadata']['ocr_stats']);
            $t->same([0], $result['metadata']['page_range']);
            $t->same(1, $result['metadata']['context']['lowres_image_count']);
            $t->same('Abstract', $result['metadata']['pdf_toc'][0]['title']);
            $t->same('Switch Transformers: Scaling To Trillion Parameter Models With Simple And Efficient Sparsity', $result['metadata']['computed_toc'][0]['title']);
        } finally {
            unlink($path);
        }
    },
    'converts supplied equation dictionaries inside the document-level pipeline' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-formula-supplied-document.php';
        $path = sys_get_temp_dir() . '/markerpdf-formula-supplied-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% formula supplied dictionary fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                $fixture['pdftextPages'],
                $fixture['options'],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->same($fixture['expectedMarkdown'], $result['text']);
            $t->contains('$$E=mc^2$$', $result['text']);
            $t->true(!str_contains($result['text'], 'E = m c ^ 2'));
            $t->same(['layout', 'order', 'equation-recognition'], $result['metadata']['supplied_boundaries']);
            $t->same([
                'successful_ocr' => 1,
                'unsuccessful_ocr' => 0,
                'equations' => 1,
            ], $result['metadata']['block_stats']['equations']);
            $t->same('$$E=mc^2$$', $result['metadata']['converted_equation_spans'][0]['text']);
            $t->same('WordPress math migration', $result['metadata']['pdf_toc'][0]['title']);
            $t->same('WordPress math migration', $result['metadata']['computed_toc'][0]['title']);
        } finally {
            unlink($path);
        }
    },
    'short-circuits supplied documents with no extracted blocks like convert_single_pdf' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/markerpdf-empty-supplied-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% empty supplied dictionary fixture\n%%EOF");

        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [[
                    'page' => 0,
                    'bbox' => [0.0, 0.0, 612.0, 792.0],
                    'rotation' => 0,
                    'blocks' => [],
                ]],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Text', 'bbox' => [72.0, 72.0, 540.0, 120.0]],
                        ],
                    ]],
                    'order_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 0, 'bbox' => [72.0, 72.0, 540.0, 120.0]],
                        ],
                    ]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->same('', $result['text']);
            $t->same([], $result['images']);
            $t->same(true, $result['metadata']['empty_text_blocks']);
            $t->same([], $result['metadata']['supplied_boundaries']);
            $t->same([
                'ocr_pages' => 0,
                'ocr_failed' => 0,
                'ocr_success' => 0,
                'ocr_engine' => 'none',
            ], $result['metadata']['ocr_stats']);
            $t->same([0], $result['metadata']['page_range']);
        } finally {
            unlink($path);
        }
    },
    'threads supplied page images through the upstream extract_images boundary after bad span filtering' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-supplied-images-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% supplied image pipeline\n%%EOF");
        try {
            $page = $pdftextPage(0, [
                ['text' => 'Import overview paragraph.', 'bbox' => [72.0, 60.0, 360.0, 78.0]],
                ['text' => 'Chart OCR should not render.', 'bbox' => [84.0, 180.0, 310.0, 216.0]],
            ]);
            $layout = [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Text', 'bbox' => [72.0, 60.0, 360.0, 78.0]],
                    ['label' => 'Picture', 'bbox' => [80.0, 170.0, 330.0, 230.0]],
                ],
            ];

            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [$page],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [$layout],
                    'image_payloads' => [['PNG-CHART-BYTES']],
                ]
            );

            $t->contains('Import overview paragraph.', $result['text']);
            $t->contains('![0_image_0.png](0_image_0.png)', $result['text']);
            $t->true(!str_contains($result['text'], 'Chart OCR should not render.'));
            $t->same(['0_image_0.png' => 'PNG-CHART-BYTES'], $result['images']);
            $t->same(['layout', 'image-extraction'], $result['metadata']['supplied_boundaries']);
            $t->same(1, $result['metadata']['block_stats']['images']);
        } finally {
            unlink($path);
        }
    },
    'acts as a benchmark runner callback for actual CI reference excerpts' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $ciPdftextPage): void {
        $base = $makeTempDir();
        $pdfFolder = $base . '/pdfs';
        $referenceFolder = $base . '/references';
        $markdownFolder = $base . '/markdown';
        mkdir($pdfFolder);
        mkdir($referenceFolder);
        mkdir($markdownFolder);

        try {
            $fixture = require __DIR__ . '/../fixtures/upstream-ci-benchmark-short.php';
            $pairsByDocument = [];
            foreach ($fixture['benchmarkPairs'] as $pair) {
                $pairsByDocument[$pair['document']] = $pair;
                file_put_contents($pdfFolder . '/' . $pair['document'], "%PDF-1.4\n% " . $pair['document'] . "\n%%EOF");
                file_put_contents($referenceFolder . '/' . preg_replace('/\.[^.]*$/', '.md', $pair['document']), $pair['referenceExcerpt']);
            }

            $converter = new SuppliedDocumentConverter();
            $result = (new BenchmarkRunner())->run(
                $pdfFolder,
                $referenceFolder,
                [
                    'marker' => static fn (string $pdfPath, string $document): string => $converter->convert(
                        $pdfPath,
                        [$ciPdftextPage($pairsByDocument[$document]['markerExcerpt'])],
                        [
                            'metadata' => ['languages' => ['English']],
                            'layout_results' => [[
                                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                                'bboxes' => [
                                    ['label' => 'Text', 'bbox' => [72.0, 72.0, 540.0, 760.0]],
                                ],
                            ]],
                        ],
                        new MarkerSettings(['EXTRACT_IMAGES' => false])
                    )['text'],
                ],
                static fn (string $pdfPath): int => str_contains($pdfPath, 'switch_trans') ? 4 : 3,
                $markdownFolder,
                array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument)
            );

            (new BenchmarkReportVerifier())->verifyMarkerScores($result['report']);
            $t->same(['multicolcnn.pdf', 'switch_trans.pdf'], $result['benchmark_files']);
            $t->true($result['report']['marker']['files']['multicolcnn.pdf']['score'] > 0.34);
            $t->true($result['report']['marker']['files']['switch_trans.pdf']['score'] > 0.40);
            $t->contains('Learning to count', (string) file_get_contents($markdownFolder . '/marker_multicolcnn.md'));
        } finally {
            $removeTree($base);
        }
    },
    'rejects malformed supplied document options before benchmark import' => static function (TestRunner $t) use ($pdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-supplied-document-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n%%EOF");
        try {
            $converter = new SuppliedDocumentConverter();
            $page = $pdftextPage(0, [
                ['text' => 'Option validation source text.', 'bbox' => [72.0, 72.0, 360.0, 84.0]],
            ]);
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $converter->convert($path, [$page], ['layout_results' => ['not' => 'a-list']])
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $converter->convert($path, [$page], ['batch_multiplier' => 'fast'])
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $converter->convert($path, [$page], ['image_payloads' => ['not-a-page-list']])
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $converter->convert($path, [$page], ['ocr_stats' => 'none'])
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $converter->convert($path, [$page], ['equation_results' => [['score' => 1.0]]])
            );
        } finally {
            unlink($path);
        }
    },
];
