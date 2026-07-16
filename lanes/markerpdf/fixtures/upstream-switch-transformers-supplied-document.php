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

$styledLine = static function (array $spans, array $bbox): array {
    return [
        'bbox' => $bbox,
        'spans' => $spans,
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
                [54.0, 44.0, 558.0, 62.0],
                'Times-Bold',
                700,
                18,
                1 << 18
            ),
        ]],
        ['lines' => [
            $line('William Fedus', [90.0, 90.0, 250.0, 102.0]),
            $line('liamfedus@google.com Barret Zoph', [90.0, 106.0, 410.0, 118.0]),
            $line(
                'barretzoph@google.com Noam Shazeer noam@google.com Google, Mountain View, CA 94043, USA',
                [90.0, 122.0, 552.0, 134.0]
            ),
            $line('Editor: Alexander Clark', [90.0, 138.0, 330.0, 150.0]),
        ]],
        ['lines' => [
            $line('Abstract', [72.0, 178.0, 138.0, 192.0], 'Times-Bold', 700, 14, 1 << 18),
        ]],
        ['lines' => [
            $line(
                'In deep learning, models typically reuse the same parameters for all inputs.',
                [72.0, 212.0, 540.0, 224.0]
            ),
            $styledLine([
                $span(
                    'Mixture of Experts (MoE) models defy this and instead select ',
                    [72.0, 227.0, 342.0, 239.0]
                ),
                $span('different', [342.0, 227.0, 390.0, 239.0], 'Times-Italic', 400, 11, 1 << 6),
                $span(' parameters for each incoming example.', [390.0, 227.0, 540.0, 239.0]),
            ], [72.0, 227.0, 540.0, 239.0]),
            $line(
                'The result is a sparsely-activated model with an outrageous number of parameters but a constant computational cost.',
                [72.0, 242.0, 540.0, 254.0]
            ),
            $line(
                'However, despite several notable successes of MoE, widespread adoption has been hindered by complexity, communication costs, and training instability.',
                [72.0, 257.0, 540.0, 269.0]
            ),
            $line(
                'We address these with the introduction of the Switch Transformer.',
                [72.0, 272.0, 540.0, 284.0]
            ),
            $line(
                'We simplify the MoE routing algorithm and design intuitive improved models with reduced communication and computational costs.',
                [72.0, 287.0, 540.0, 299.0]
            ),
            $line(
                'Our proposed training techniques mitigate the instabilities, and we show large sparse models may be trained with lower precision formats.',
                [72.0, 302.0, 540.0, 314.0]
            ),
            $line(
                'We design models based off T5-Base and T5-Large to obtain up to 7x increases in pre-training speed with the same computational resources.',
                [72.0, 317.0, 540.0, 329.0]
            ),
        ]],
        ['lines' => [
            $line('1. Introduction', [72.0, 364.0, 190.0, 378.0], 'Times-Bold', 700, 14, 1 << 18),
        ]],
        ['lines' => [
            $line(
                'Large scale training has been an effective path towards flexible and powerful neural language models.',
                [72.0, 398.0, 540.0, 410.0]
            ),
            $line(
                'Simple architectures backed by a generous computational budget, data set size and parameter count surpass more complicated algorithms.',
                [72.0, 413.0, 540.0, 425.0]
            ),
            $line(
                'Inspired by the success of model scale, but seeking greater computational efficiency, we instead propose a sparsely-activated expert model: the Switch Transformer.',
                [72.0, 428.0, 540.0, 440.0]
            ),
            $line(
                'These class of algorithms are broadly valuable in natural language and across pre-training, fine-tuning and multi-task training.',
                [72.0, 443.0, 540.0, 455.0]
            ),
        ]],
    ],
]];

$layout = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Title', 'bbox' => [52.0, 40.0, 560.0, 68.0]],
        ['label' => 'Text', 'bbox' => [86.0, 84.0, 556.0, 156.0]],
        ['label' => 'Section-header', 'bbox' => [68.0, 174.0, 170.0, 198.0]],
        ['label' => 'Text', 'bbox' => [68.0, 206.0, 544.0, 334.0]],
        ['label' => 'Section-header', 'bbox' => [68.0, 360.0, 230.0, 384.0]],
        ['label' => 'Text', 'bbox' => [68.0, 392.0, 544.0, 462.0]],
    ],
];

$order = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 0, 'bbox' => [52.0, 40.0, 560.0, 68.0]],
        ['position' => 1, 'bbox' => [86.0, 84.0, 556.0, 156.0]],
        ['position' => 2, 'bbox' => [68.0, 174.0, 170.0, 198.0]],
        ['position' => 3, 'bbox' => [68.0, 206.0, 544.0, 334.0]],
        ['position' => 4, 'bbox' => [68.0, 360.0, 230.0, 384.0]],
        ['position' => 5, 'bbox' => [68.0, 392.0, 544.0, 462.0]],
    ],
];

return [
    'document' => 'switch_trans.pdf',
    'sourceCommit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
    'pdfPath' => 'benchmark_data/pdfs/switch_trans.pdf',
    'pdfSha256' => 'f340f6ace31abf7d0730ef461404279f40d3c890e9cc2daeb7068b3304afdbd6',
    'markerPath' => 'data/examples/marker/switch_transformers.md',
    'referencePath' => 'data/examples/nougat/switch_transformers.md',
    'referenceKind' => 'committed-marker-output-supplied-dictionary-excerpt',
    'scoreThreshold' => 0.75,
    'chunkLength' => 500,
    'pdftextPages' => $pdftextPages,
    'options' => [
        'metadata' => ['languages' => ['English']],
        'toc' => [
            ['title' => 'Abstract', 'level' => 1, 'page_index' => 0],
            ['title' => '1. Introduction', 'level' => 1, 'page_index' => 0],
        ],
        'layout_results' => [$layout],
        'order_results' => [$order],
        'document_page_count' => 33,
    ],
    'expectedMarkdown' => <<<'MARKDOWN'
# Switch Transformers: Scaling To Trillion Parameter Models With Simple And Efficient Sparsity

William Fedus liamfedus@google.com Barret Zoph barretzoph@google.com Noam Shazeer noam@google.com Google, Mountain View, CA 94043, USA Editor: Alexander Clark

## Abstract

In deep learning, models typically reuse the same parameters for all inputs. Mixture of Experts (MoE) models defy this and instead select *different* parameters for each incoming example. The result is a sparsely-activated model with an outrageous number of parameters but a constant computational cost. However, despite several notable successes of MoE, widespread adoption has been hindered by complexity, communication costs, and training instability. We address these with the introduction of the Switch Transformer. We simplify the MoE routing algorithm and design intuitive improved models with reduced communication and computational costs. Our proposed training techniques mitigate the instabilities, and we show large sparse models may be trained with lower precision formats. We design models based off T5-Base and T5-Large to obtain up to 7x increases in pre-training speed with the same computational resources.

## 1. Introduction

Large scale training has been an effective path towards flexible and powerful neural language models. Simple architectures backed by a generous computational budget, data set size and parameter count surpass more complicated algorithms. Inspired by the success of model scale, but seeking greater computational efficiency, we instead propose a sparsely-activated expert model: the Switch Transformer. These class of algorithms are broadly valuable in natural language and across pre-training, fine-tuning and multi-task training.
MARKDOWN,
    'referenceExcerpt' => <<<'REFERENCE'
# Switch Transformers: Scaling to Trillion Parameter Models with Simple and Efficient Sparsity

William Fedus

Barret Zoph

Noam Shazeer

Google, Mountain View, CA 94043, USA

###### Abstract

In deep learning, models typically reuse the same parameters for all inputs. Mixture of Experts (MoE) models defy this and instead select _different_ parameters for each incoming example. The result is a sparsely-activated model--with an outrageous number of parameters--but a constant computational cost. However, despite several notable successes of MoE, widespread adoption has been hindered by complexity, communication costs, and training instability. We address these with the introduction of the Switch Transformer. We simplify the MoE routing algorithm and design intuitive improved models with reduced communication and computational costs. Our proposed training techniques mitigate the instabilities, and we show large sparse models may be trained, for the first time, with lower precision (bfloat16) formats. We design models based off T5-Base and T5-Large (Raffel et al., 2019) to obtain up to 7x increases in pre-training speed with the same computational resources.

## 1 Introduction

Large scale training has been an effective path towards flexible and powerful neural language models. Simple architectures backed by a generous computational budget, data set size and parameter count surpass more complicated algorithms. Inspired by the success of model scale, but seeking greater computational efficiency, we instead propose a sparsely-activated expert model: the Switch Transformer. These class of algorithms are broadly valuable in natural language and across pre-training, fine-tuning and multi-task training.
REFERENCE,
];
