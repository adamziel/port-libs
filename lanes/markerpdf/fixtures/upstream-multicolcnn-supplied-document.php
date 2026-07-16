<?php

declare(strict_types=1);

$line = static function (
    string $text,
    array $bbox,
    string $font = 'Times-Roman',
    int $weight = 400,
    int $size = 11
): array {
    return [
        'bbox' => $bbox,
        'spans' => [[
            'text' => $text,
            'bbox' => $bbox,
            'font' => [
                'name' => $font,
                'flags' => $weight >= 600 ? (1 << 18) : 0,
                'weight' => $weight,
                'size' => $size,
            ],
        ]],
    ];
};

$pdftextPages = [[
    'page' => 0,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'rotation' => 0,
    'blocks' => [
        ['lines' => [
            $line(
                'An Aggregated Multicolumn Dilated Convolution Network For Perspective-Free Counting',
                [54.0, 44.0, 558.0, 62.0],
                'Times-Bold',
                700,
                18
            ),
        ]],
        ['lines' => [
            $line(
                'Diptodip Deb Georgia Institute of Technology diptodipdeb@gatech.edu',
                [86.0, 90.0, 506.0, 102.0]
            ),
            $line(
                'Jonathan Ventura University of Colorado Colorado Springs jventura@uccs.edu',
                [86.0, 106.0, 526.0, 118.0]
            ),
        ]],
        ['lines' => [
            $line('Abstract', [72.0, 150.0, 138.0, 164.0], 'Times-Bold', 700, 14),
        ]],
        ['lines' => [
            $line(
                'We propose the use of dilated filters to construct an aggregation module in a multicolumn',
                [72.0, 184.0, 540.0, 196.0]
            ),
            $line(
                'convolutional neural network for perspective-free counting. Counting is a common problem in computer',
                [72.0, 199.0, 540.0, 211.0]
            ),
            $line(
                'vision (e.g. traffic on the street or pedestrians in a crowd). Modern approaches to the counting problem',
                [72.0, 214.0, 540.0, 226.0]
            ),
            $line(
                'involve the production of a density map via regression whose integral is equal to the number of objects in the image.',
                [72.0, 229.0, 540.0, 241.0]
            ),
            $line(
                'However, objects in the image can occur at different scales (e.g. due to perspective effects) which can make',
                [72.0, 244.0, 540.0, 256.0]
            ),
            $line(
                'it difficult for a learning agent to learn the proper density map. While the use of multiple columns to extract',
                [72.0, 259.0, 540.0, 271.0]
            ),
            $line(
                'multiscale information from images has been shown before, our approach aggregates the multiscale information gathered by the multicolumn convolutional neural network to improve performance.',
                [72.0, 274.0, 540.0, 286.0]
            ),
        ]],
        ['lines' => [
            $line('1. Introduction', [72.0, 322.0, 190.0, 336.0], 'Times-Bold', 700, 14),
        ]],
        ['lines' => [
            $line(
                'Learning to count the number of objects in an image is a deceptively difficult problem with many interesting',
                [72.0, 356.0, 540.0, 368.0]
            ),
            $line(
                'applications, such as surveillance, traffic monitoring and medical image analysis. In many of these application areas,',
                [72.0, 371.0, 540.0, 383.0]
            ),
            $line(
                'the objects to be counted vary widely in appearance, size and shape, and labeled training data is typically sparse.',
                [72.0, 386.0, 540.0, 398.0]
            ),
        ]],
    ],
]];

$layout = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Title', 'bbox' => [54.0, 40.0, 558.0, 68.0]],
        ['label' => 'Text', 'bbox' => [80.0, 84.0, 532.0, 124.0]],
        ['label' => 'Section-header', 'bbox' => [68.0, 146.0, 180.0, 170.0]],
        ['label' => 'Text', 'bbox' => [68.0, 176.0, 544.0, 292.0]],
        ['label' => 'Section-header', 'bbox' => [68.0, 318.0, 230.0, 342.0]],
        ['label' => 'Text', 'bbox' => [68.0, 348.0, 544.0, 404.0]],
    ],
];

$order = [
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 0, 'bbox' => [54.0, 40.0, 558.0, 68.0]],
        ['position' => 1, 'bbox' => [80.0, 84.0, 532.0, 124.0]],
        ['position' => 2, 'bbox' => [68.0, 146.0, 180.0, 170.0]],
        ['position' => 3, 'bbox' => [68.0, 176.0, 544.0, 292.0]],
        ['position' => 4, 'bbox' => [68.0, 318.0, 230.0, 342.0]],
        ['position' => 5, 'bbox' => [68.0, 348.0, 544.0, 404.0]],
    ],
];

return [
    'document' => 'multicolcnn.pdf',
    'sourceCommit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
    'pdfPath' => 'benchmark_data/pdfs/multicolcnn.pdf',
    'pdfSha256' => '2b0e8314ff2c2680dd309ce46a49d740084d66eb39549337d2daa91215c426f8',
    'markerPath' => 'data/examples/marker/multicolcnn.md',
    'referencePath' => 'data/examples/nougat/multicolcnn.md',
    'referenceKind' => 'committed-marker-output-supplied-dictionary-excerpt',
    'scoreThreshold' => 0.80,
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
        'document_page_count' => 9,
    ],
    'expectedMarkdown' => <<<'MARKDOWN'
# An Aggregated Multicolumn Dilated Convolution Network For Perspective-Free Counting

Diptodip Deb Georgia Institute of Technology diptodipdeb@gatech.edu Jonathan Ventura University of Colorado Colorado Springs jventura@uccs.edu

## Abstract

We propose the use of dilated filters to construct an aggregation module in a multicolumn convolutional neural network for perspective-free counting. Counting is a common problem in computer vision (e.g. traffic on the street or pedestrians in a crowd). Modern approaches to the counting problem involve the production of a density map via regression whose integral is equal to the number of objects in the image. However, objects in the image can occur at different scales (e.g. due to perspective effects) which can make it difficult for a learning agent to learn the proper density map. While the use of multiple columns to extract multiscale information from images has been shown before, our approach aggregates the multiscale information gathered by the multicolumn convolutional neural network to improve performance.

## 1. Introduction

Learning to count the number of objects in an image is a deceptively difficult problem with many interesting applications, such as surveillance, traffic monitoring and medical image analysis. In many of these application areas, the objects to be counted vary widely in appearance, size and shape, and labeled training data is typically sparse.
MARKDOWN,
    'referenceExcerpt' => <<<'REFERENCE'
# An Aggregated Multicolumn Dilated Convolution Network

for Perspective-Free Counting

Diptodip Deb

Georgia Institute of Technology

diptodipdeb@gatech.edu

Jonathan Ventura

University of Colorado Colorado Springs

jventura@uccs.edu

###### Abstract

We propose the use of dilated filters to construct an aggregation module in a multicolumn convolutional neural network for perspective-free counting. Counting is a common problem in computer vision (e.g. traffic on the street or pedestrians in a crowd). Modern approaches to the counting problem involve the production of a density map via regression whose integral is equal to the number of objects in the image. However, objects in the image can occur at different scales (e.g. due to perspective effects) which can make it difficult for a learning agent to learn the proper density map. While the use of multiple columns to extract multiscale information from images has been shown before, our approach aggregates the multiscale information gathered by the multicolumn convolutional neural network to improve performance.

## 1 Introduction

Learning to count the number of objects in an image is a deceptively difficult problem with many interesting applications, such as surveillance, traffic monitoring and medical image analysis. In many of these application areas, the objects to be counted vary widely in appearance, size and shape, and labeled training data is typically sparse.
REFERENCE,
];
