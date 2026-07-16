<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkRunner;
use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$pdftextPage = static function (array $lines): array {
    return [
        'page' => 0,
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
                            'flags' => 0,
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

$base = sys_get_temp_dir() . '/markerpdf-wordpress-supplied-document-' . bin2hex(random_bytes(4));
$pdfFolder = $base . '/pdfs';
$referenceFolder = $base . '/references';
$markdownFolder = $base . '/markdown';
mkdir($pdfFolder, 0777, true);
mkdir($referenceFolder, 0777, true);
mkdir($markdownFolder, 0777, true);

try {
    $document = 'wordpress-import-packet.pdf';
    $pdfPath = $pdfFolder . '/' . $document;
    file_put_contents($pdfPath, "%PDF-1.4\n% WordPress supplied document conversion\n%%EOF");

    $pdftextPages = [
        $pdftextPage([
            ['text' => 'WordPress import packet', 'bbox' => [72.0, 48.0, 380.0, 72.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
            ['text' => 'First column import summary.', 'bbox' => [72.0, 110.0, 280.0, 126.0]],
            ['text' => 'Second column media checklist.', 'bbox' => [330.0, 110.0, 560.0, 126.0]],
            ['text' => 'Feature Status', 'bbox' => [72.0, 190.0, 360.0, 212.0]],
            ['text' => 'Chart OCR overlay should stay out of post text.', 'bbox' => [390.0, 190.0, 560.0, 232.0]],
        ]),
    ];
    $layout = [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [72.0, 48.0, 380.0, 72.0]],
            ['label' => 'Text', 'bbox' => [72.0, 100.0, 280.0, 140.0]],
            ['label' => 'Text', 'bbox' => [330.0, 100.0, 560.0, 140.0]],
            ['label' => 'Table', 'bbox' => [72.0, 180.0, 360.0, 240.0]],
            ['label' => 'Picture', 'bbox' => [380.0, 180.0, 570.0, 250.0]],
        ],
    ];
    $order = [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 0, 'bbox' => [72.0, 48.0, 380.0, 72.0]],
            ['position' => 1, 'bbox' => [72.0, 100.0, 280.0, 140.0]],
            ['position' => 2, 'bbox' => [330.0, 100.0, 560.0, 140.0]],
            ['position' => 3, 'bbox' => [72.0, 180.0, 360.0, 240.0]],
            ['position' => 4, 'bbox' => [380.0, 180.0, 570.0, 250.0]],
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

    $converter = new SuppliedDocumentConverter();
    $converted = $converter->convert(
        $pdfPath,
        $pdftextPages,
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [$layout],
            'order_results' => [$order],
            'recognized_tables' => [$recognizedTable],
            'table_text_lines' => [['blocks' => []]],
            'image_payloads' => [['PNG-CHART-BYTES']],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => true])
    );

    file_put_contents($referenceFolder . '/wordpress-import-packet.md', $converted['text']);
    $report = (new BenchmarkRunner())->run(
        $pdfFolder,
        $referenceFolder,
        ['marker' => static fn (): string => $converted['text']],
        static fn (): int => 1,
        $markdownFolder
    )['report'];
    $passesGate = ($report['marker']['files']['wordpress-import-packet.pdf']['score'] ?? 0.0) > 0.99;

    echo json_encode([
        'scenario' => 'wordpress-supplied-document-benchmark',
        'purpose' => 'Convert supplied pdftext/layout/order/table dictionaries into block-ready Markdown and run it through the upstream benchmark report shape.',
        'blockPreview' => [
            [
                'blockName' => 'core/paragraph',
                'innerHTML' => '<p>' . htmlspecialchars('First column import summary. Second column media checklist.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
            ],
            [
                'blockName' => 'core/image',
                'attrs' => ['url' => '0_image_0.png'],
            ],
        ],
        'metadata' => [
            'languages' => $converted['metadata']['languages'] ?? [],
            'page_range' => $converted['metadata']['page_range'],
            'supplied_boundaries' => $converted['metadata']['supplied_boundaries'],
            'table_count' => $converted['metadata']['block_stats']['table'],
            'image_count' => count($converted['images']),
        ],
        'report' => $report,
        'passes_wordpress_gate' => $passesGate,
        'markdown' => $converted['text'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} finally {
    $removeTree($base);
}
