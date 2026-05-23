<?php

declare(strict_types=1);

$span = static function (
    string $text,
    array $bbox,
    string $font = 'Times-Roman',
    int $weight = 400,
    int $size = 11,
    int $flags = 0
): array {
    return [
        'text' => $text,
        'bbox' => $bbox,
        'font' => [
            'name' => $font,
            'flags' => $flags,
            'weight' => $weight,
            'size' => $size,
        ],
    ];
};

$line = static function (
    string $text,
    array $bbox,
    string $font = 'Times-Roman',
    int $weight = 400,
    int $size = 11,
    int $flags = 0
) use ($span): array {
    return [
        'bbox' => $bbox,
        'spans' => [$span($text, $bbox, $font, $weight, $size, $flags)],
    ];
};

$pdftextPages = [[
    'page' => 0,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'rotation' => 0,
    'blocks' => [
        ['lines' => [
            $line(
                '2.4 Improved Training And Fine-Tuning Techniques',
                [72.0, 54.0, 480.0, 70.0],
                'Times-Bold',
                700,
                14,
                1 << 18
            ),
        ]],
        ['lines' => [
            $line(
                'Further, low precision formats like bfloat16 can exacerbate issues in the softmax computation for our router.',
                [72.0, 96.0, 540.0, 110.0]
            ),
            $line(
                'Table 1 highlights the speed-quality tradeoff for Switch and MoE models.',
                [72.0, 114.0, 540.0, 128.0]
            ),
        ]],
        ['lines' => [
            $line('Model Capacity Quality after Time to Quality Speed', [72.0, 154.0, 540.0, 166.0]),
            $line('Switch-Base 1.0 -1.561 62.8 1000', [72.0, 178.0, 540.0, 190.0]),
        ]],
        ['lines' => [
            $line(
                'Table 1: Benchmarking Switch versus MoE. Head-to-head comparison measuring per step and per time benefits of the Switch Transformer over the MoE Transformer and T5 dense baselines.',
                [72.0, 474.0, 540.0, 504.0]
            ),
        ]],
    ],
]];

$layout = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Section-header', 'bbox' => [68.0, 50.0, 500.0, 76.0]],
        ['label' => 'Text', 'bbox' => [68.0, 90.0, 544.0, 134.0]],
        ['label' => 'Table', 'bbox' => [68.0, 146.0, 544.0, 462.0]],
        ['label' => 'Caption', 'bbox' => [68.0, 468.0, 544.0, 512.0]],
    ],
];

$order = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 0, 'bbox' => [68.0, 50.0, 500.0, 76.0]],
        ['position' => 1, 'bbox' => [68.0, 90.0, 544.0, 134.0]],
        ['position' => 2, 'bbox' => [68.0, 146.0, 544.0, 462.0]],
        ['position' => 3, 'bbox' => [68.0, 468.0, 544.0, 512.0]],
    ],
];

$rows = [];
for ($row = 0; $row < 12; $row++) {
    $top = (float) ($row * 25);
    $rows[] = ['row_id' => $row, 'bbox' => [0.0, $top, 650.0, $top + 21.0]];
}

$cols = [
    ['col_id' => 0, 'bbox' => [0.0, 0.0, 165.0, 300.0]],
    ['col_id' => 1, 'bbox' => [165.0, 0.0, 300.0, 300.0]],
    ['col_id' => 2, 'bbox' => [300.0, 0.0, 430.0, 300.0]],
    ['col_id' => 3, 'bbox' => [430.0, 0.0, 570.0, 300.0]],
    ['col_id' => 4, 'bbox' => [570.0, 0.0, 650.0, 300.0]],
];

$cell = static function (int $row, int $col, string $text) use ($cols): array {
    $top = (float) ($row * 25 + 4);
    $colBox = $cols[$col]['bbox'];

    return [
        'bbox' => [$colBox[0] + 4.0, $top, $colBox[2] - 4.0, $top + 15.0],
        'text' => $text,
    ];
};

$recognizedTable = [
    'rows' => $rows,
    'cols' => $cols,
    'cells' => [
        $cell(0, 0, 'Model'),
        $cell(0, 1, 'Capacity'),
        $cell(0, 2, 'Quality after'),
        $cell(0, 3, 'Time to Quality'),
        $cell(0, 4, 'Speed (↑)'),
        $cell(1, 0, 'Factor'),
        $cell(1, 1, '100k steps (↑)'),
        $cell(1, 2, 'Threshold (↓)'),
        $cell(1, 3, '(examples/sec)'),
        $cell(1, 4, ''),
        $cell(2, 0, '(Neg. Log Perp.)'),
        $cell(2, 1, '(hours)'),
        $cell(2, 2, ''),
        $cell(2, 3, ''),
        $cell(2, 4, ''),
        $cell(3, 0, 'T5-Base'),
        $cell(3, 1, '-'),
        $cell(3, 2, '-1.731'),
        $cell(3, 3, 'Not achieved†'),
        $cell(3, 4, '1600'),
        $cell(4, 0, 'T5-Large'),
        $cell(4, 1, '-'),
        $cell(4, 2, '-1.550'),
        $cell(4, 3, '131.1'),
        $cell(4, 4, '470'),
        $cell(5, 0, 'MoE-Base'),
        $cell(5, 1, '2.0'),
        $cell(5, 2, '-1.547'),
        $cell(5, 3, '68.7'),
        $cell(5, 4, '840'),
        $cell(6, 0, 'Switch-Base'),
        $cell(6, 1, '2.0'),
        $cell(6, 2, '-1.554'),
        $cell(6, 3, '72.8'),
        $cell(6, 4, '860'),
        $cell(7, 0, 'MoE-Base'),
        $cell(7, 1, '1.25'),
        $cell(7, 2, '-1.559'),
        $cell(7, 3, '80.7'),
        $cell(7, 4, '790'),
        $cell(8, 0, 'Switch-Base'),
        $cell(8, 1, '1.25'),
        $cell(8, 2, '-1.553'),
        $cell(8, 3, '65.0'),
        $cell(8, 4, '910'),
        $cell(9, 0, 'MoE-Base'),
        $cell(9, 1, '1.0'),
        $cell(9, 2, '-1.572'),
        $cell(9, 3, '80.1'),
        $cell(9, 4, '860'),
        $cell(10, 0, 'Switch-Base'),
        $cell(10, 1, '1.0'),
        $cell(10, 2, '-1.561'),
        $cell(10, 3, '62.8'),
        $cell(10, 4, '1000'),
        $cell(11, 0, 'Switch-Base+'),
        $cell(11, 1, '1.0'),
        $cell(11, 2, '-1.534'),
        $cell(11, 3, '67.6'),
        $cell(11, 4, '780'),
    ],
];

return [
    'document' => 'switch_trans.pdf',
    'sourceCommit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
    'pdfPath' => 'benchmark_data/pdfs/switch_trans.pdf',
    'pdfSha256' => 'f340f6ace31abf7d0730ef461404279f40d3c890e9cc2daeb7068b3304afdbd6',
    'markerPath' => 'data/examples/marker/switch_transformers.md',
    'referenceKind' => 'committed-marker-output-table1-supplied-page-slice',
    'scoreThreshold' => 0.50,
    'chunkLength' => 600,
    'pdftextPages' => $pdftextPages,
    'options' => [
        'metadata' => ['languages' => ['English']],
        'toc' => [
            ['title' => '2.4 Improved Training And Fine-Tuning Techniques', 'level' => 2, 'page_index' => 0],
        ],
        'layout_results' => [$layout],
        'order_results' => [$order],
        'recognized_tables' => [$recognizedTable],
        'table_text_lines' => [['blocks' => []]],
        'document_page_count' => 33,
    ],
    'markerExcerpt' => <<<'MARKDOWN'
## 2.4 Improved Training And Fine-Tuning Techniques

Further, low precision formats like bfloat16 can exacerbate issues in the softmax computation for our router.

| Model            | Capacity       | Quality after   | Time to Quality   | Speed (↑)   |
|------------------|----------------|-----------------|-------------------|-------------|
| Factor           | 100k steps (↑) | Threshold (↓)   | (examples/sec)    |             |
| (Neg. Log Perp.) | (hours)        |                 |                   |             |
| T5-Base          | -              | -1.731          | Not achieved†     | 1600        |
| T5-Large         | -              | -1.550          | 131.1             | 470         |
| MoE-Base         | 2.0            | -1.547          | 68.7              | 840         |
| Switch-Base      | 2.0            | -1.554          | 72.8              | 860         |
| MoE-Base         | 1.25           | -1.559          | 80.7              | 790         |
| Switch-Base      | 1.25           | -1.553          | 65.0              | 910         |
| MoE-Base         | 1.0            | -1.572          | 80.1              | 860         |
| Switch-Base      | 1.0            | -1.561          | 62.8              | 1000        |
| Switch-Base+     | 1.0            | -1.534          | 67.6              | 780         |

Table 1: Benchmarking Switch versus MoE. Head-to-head comparison measuring per step and per time benefits of the Switch Transformer over the MoE Transformer and T5 dense baselines.
MARKDOWN,
];
