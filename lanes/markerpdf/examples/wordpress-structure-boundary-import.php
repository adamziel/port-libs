<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$tableToHtml = static function (string $tableMarkdown): string {
    $rows = array_values(array_filter(
        preg_split('/\R/', trim($tableMarkdown)) ?: [],
        static fn (string $row): bool => trim($row, " \t|") !== '' && !preg_match('/^\s*\|?\s*:?-{3,}:?\s*(\|\s*:?-{3,}:?\s*)+\|?\s*$/', $row)
    ));

    $htmlRows = [];
    foreach ($rows as $row) {
        $cells = array_map(
            static fn (string $cell): string => htmlspecialchars(trim($cell), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            explode('|', trim($row, " \t|"))
        );
        $htmlRows[] = '<tr><td>' . implode('</td><td>', $cells) . '</td></tr>';
    }

    return "<!-- wp:table -->\n<figure class=\"wp-block-table\"><table><tbody>"
        . implode('', $htmlRows)
        . "</tbody></table></figure>\n<!-- /wp:table -->\n\n";
};

$path = sys_get_temp_dir() . '/markerpdf-wordpress-structure-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% structure boundary WordPress fixture\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage(0, [
                ['text' => 'Structure import', 'bbox' => [72.0, 60.0, 360.0, 78.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                ['text' => 'Raw table formula image text', 'bbox' => [72.0, 160.0, 420.0, 178.0]],
                ['text' => 'After structure.', 'bbox' => [72.0, 260.0, 420.0, 278.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'layout_results' => [[
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [72.0, 60.0, 360.0, 78.0]],
                    ['label' => 'Table', 'bbox' => [72.0, 150.0, 420.0, 210.0]],
                    ['label' => 'Formula', 'bbox' => [80.0, 158.0, 230.0, 182.0]],
                    ['label' => 'Picture', 'bbox' => [260.0, 158.0, 420.0, 200.0]],
                    ['label' => 'Text', 'bbox' => [72.0, 260.0, 420.0, 278.0]],
                ],
            ]],
            'recognized_tables' => [[
                'rows' => [
                    ['row_id' => 0, 'bbox' => [0.0, 0.0, 300.0, 30.0]],
                    ['row_id' => 1, 'bbox' => [0.0, 30.0, 300.0, 60.0]],
                ],
                'cols' => [
                    ['col_id' => 0, 'bbox' => [0.0, 0.0, 150.0, 60.0]],
                    ['col_id' => 1, 'bbox' => [150.0, 0.0, 300.0, 60.0]],
                ],
                'cells' => [
                    ['bbox' => [0.0, 0.0, 140.0, 25.0], 'text' => 'Metric'],
                    ['bbox' => [150.0, 0.0, 290.0, 25.0], 'text' => 'Value'],
                    ['bbox' => [0.0, 30.0, 140.0, 55.0], 'text' => 'Equation'],
                    ['bbox' => [150.0, 30.0, 290.0, 55.0], 'text' => 'E=mc^2'],
                ],
            ]],
            'table_text_lines' => [['blocks' => []]],
            'equation_predictions' => ['$$E=mc^2$$'],
            'image_payloads' => [['PNG-CHART-BYTES']],
        ]
    );
} finally {
    unlink($path);
}

foreach (preg_split('/\n{2,}/', trim($result['text'])) ?: [] as $block) {
    $block = trim($block);
    if ($block === '') {
        continue;
    }

    if (str_starts_with($block, '# ')) {
        echo "<!-- wp:heading {\"level\":1} -->\n";
        echo '<h1>' . htmlspecialchars(substr($block, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h1>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    if (str_starts_with($block, '|')) {
        echo $tableToHtml($block);
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo json_encode([
    'scenario' => 'wordpress-markerpdf-structure-boundary-import',
    'native_boundary' => 'table layout regions protect nested Formula/Picture regions before Gutenberg rendering',
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'],
    'block_stats' => $result['metadata']['block_stats'],
    'image_count' => count($result['images']),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
