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
                'Switch Transformers: Scaling To Trillion Parameter Models With Simple And Efficient Sparsity',
                [54.0, 42.0, 558.0, 62.0],
                'Times-Bold',
                700,
                18,
                1 << 18
            ),
        ]],
        ['lines' => [
            $line('Abstract', [72.0, 104.0, 138.0, 118.0], 'Times-Bold', 700, 14, 1 << 18),
        ]],
        ['lines' => [
            $line(
                'In deep learning, models typically reuse the same parameters for all inputs.',
                [72.0, 138.0, 540.0, 150.0]
            ),
            $line(
                'Mixture of Experts models select different parameters for each incoming example.',
                [72.0, 153.0, 540.0, 165.0]
            ),
            $line(
                'Finally, we advance the current scale of language models by pre-training up to trillion parameter models.',
                [72.0, 168.0, 540.0, 180.0]
            ),
        ]],
        ['lines' => [
            $line('Contents', [72.0, 216.0, 150.0, 230.0], 'Times-Bold', 700, 14, 1 << 18),
        ]],
        ['lines' => [
            $line('1 Introduction 3', [74.0, 258.0, 520.0, 270.0]),
            $line('2 Switch Transformer 4', [74.0, 274.0, 520.0, 286.0]),
            $line('2.1 Simplifying Sparse Routing 5', [74.0, 290.0, 520.0, 302.0]),
            $line('2.2 Efficient Sparse Routing 6', [74.0, 306.0, 520.0, 318.0]),
            $line('3 Scaling Properties 11', [74.0, 322.0, 520.0, 334.0]),
            $line('5 Designing Models with Data, Model, and Expert-Parallelism 18', [74.0, 338.0, 520.0, 350.0]),
            $line('B Preventing Token Dropping with No-Token-Left-Behind 29', [74.0, 354.0, 520.0, 366.0]),
        ]],
    ],
]];

$layout = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Title', 'bbox' => [52.0, 38.0, 560.0, 68.0]],
        ['label' => 'Section-header', 'bbox' => [68.0, 100.0, 170.0, 124.0]],
        ['label' => 'Text', 'bbox' => [68.0, 132.0, 544.0, 186.0]],
        ['label' => 'Section-header', 'bbox' => [68.0, 212.0, 170.0, 236.0]],
        ['label' => 'Table', 'bbox' => [68.0, 248.0, 544.0, 374.0]],
    ],
];

$order = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 0, 'bbox' => [52.0, 38.0, 560.0, 68.0]],
        ['position' => 1, 'bbox' => [68.0, 100.0, 170.0, 124.0]],
        ['position' => 2, 'bbox' => [68.0, 132.0, 544.0, 186.0]],
        ['position' => 3, 'bbox' => [68.0, 212.0, 170.0, 236.0]],
        ['position' => 4, 'bbox' => [68.0, 248.0, 544.0, 374.0]],
    ],
];

$rows = [];
for ($row = 0; $row < 7; $row++) {
    $top = (float) ($row * 28);
    $rows[] = ['row_id' => $row, 'bbox' => [0.0, $top, 560.0, $top + 24.0]];
}

$cols = [
    ['col_id' => 0, 'bbox' => [0.0, 0.0, 58.0, 196.0]],
    ['col_id' => 1, 'bbox' => [58.0, 0.0, 432.0, 196.0]],
    ['col_id' => 2, 'bbox' => [432.0, 0.0, 496.0, 196.0]],
    ['col_id' => 3, 'bbox' => [496.0, 0.0, 560.0, 196.0]],
];

$cell = static function (int $row, int $col, string $text) use ($cols): array {
    $top = (float) ($row * 28 + 4);
    $colBox = $cols[$col]['bbox'];

    return [
        'bbox' => [$colBox[0] + 4.0, $top, $colBox[2] - 4.0, $top + 16.0],
        'text' => $text,
    ];
};

$recognizedTable = [
    'rows' => $rows,
    'cols' => $cols,
    'cells' => [
        $cell(0, 0, '1'),
        $cell(0, 1, 'Introduction'),
        $cell(0, 2, '3'),
        $cell(1, 0, '2'),
        $cell(1, 1, 'Switch Transformer'),
        $cell(1, 2, '4'),
        $cell(2, 0, '2.1'),
        $cell(2, 1, 'Simplifying Sparse Routing'),
        $cell(2, 3, '5'),
        $cell(3, 0, '2.2'),
        $cell(3, 1, 'Efficient Sparse Routing'),
        $cell(3, 3, '6'),
        $cell(4, 0, '3'),
        $cell(4, 1, 'Scaling Properties'),
        $cell(4, 2, '11'),
        $cell(5, 0, '5'),
        $cell(5, 1, 'Designing Models with Data, Model, and Expert-Parallelism'),
        $cell(5, 2, '18'),
        $cell(6, 0, 'B'),
        $cell(6, 1, 'Preventing Token Dropping with No-Token-Left-Behind'),
        $cell(6, 2, '29'),
    ],
];

return [
    'document' => 'switch_trans.pdf',
    'sourceCommit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
    'pdfPath' => 'benchmark_data/pdfs/switch_trans.pdf',
    'pdfSha256' => 'f340f6ace31abf7d0730ef461404279f40d3c890e9cc2daeb7068b3304afdbd6',
    'markerPath' => 'data/examples/marker/switch_transformers.md',
    'referencePath' => 'data/examples/nougat/switch_transformers.md',
    'referenceKind' => 'committed-marker-output-toc-table-supplied-page-slice',
    'scoreThreshold' => 0.50,
    'chunkLength' => 500,
    'pdftextPages' => $pdftextPages,
    'options' => [
        'metadata' => ['languages' => ['English']],
        'toc' => [
            ['title' => 'Abstract', 'level' => 1, 'page_index' => 0],
            ['title' => 'Contents', 'level' => 1, 'page_index' => 0],
        ],
        'layout_results' => [$layout],
        'order_results' => [$order],
        'recognized_tables' => [$recognizedTable],
        'table_text_lines' => [['blocks' => []]],
        'document_page_count' => 33,
    ],
    'expectedMarkdown' => <<<'MARKDOWN'
# Switch Transformers: Scaling To Trillion Parameter Models With Simple And Efficient Sparsity

## Abstract

In deep learning, models typically reuse the same parameters for all inputs. Mixture of Experts models select different parameters for each incoming example. Finally, we advance the current scale of language models by pre-training up to trillion parameter models.

## Contents

| 1   | Introduction                                               | 3  |   |
|-----|------------------------------------------------------------|----|---|
| 2   | Switch Transformer                                         | 4  |   |
| 2.1 | Simplifying Sparse Routing                                 |    | 5 |
| 2.2 | Efficient Sparse Routing                                   |    | 6 |
| 3   | Scaling Properties                                         | 11 |   |
| 5   | Designing Models with Data, Model, and Expert\-Parallelism | 18 |   |
| B   | Preventing Token Dropping with No\-Token\-Left\-Behind     | 29 |   |
MARKDOWN . "\n",
    'markerExcerpt' => <<<'MARKDOWN'
Contents

| 1   | Introduction                                              | 3   |    |
|-----|-----------------------------------------------------------|-----|----|
| 2   | Switch Transformer                                        | 4   |    |
| 2.1 | Simplifying Sparse Routing                                |     | 5  |
| 2.2 | Efficient Sparse Routing                                  |     | 6  |
| 3   | Scaling Properties                                        | 11  |    |
| 5   | Designing Models with Data, Model, and Expert-Parallelism | 18  |    |
| B   | Preventing Token Dropping with No-Token-Left-Behind       | 29  |    |
MARKDOWN,
    'referenceExcerpt' => <<<'REFERENCE'
###### Contents

* 1 Introduction
* 2 Switch Transformer
	* 2.1 Simplifying Sparse Routing
	* 2.2 Efficient Sparse Routing
* 3 Scaling Properties
* 5 Designing Models with Data, Model, and Expert-Parallelism
* B Preventing Token Dropping with _No-Token-Left-Behind_
REFERENCE,
];
