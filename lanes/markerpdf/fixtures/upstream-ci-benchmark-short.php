<?php

declare(strict_types=1);

return [
    'archive' => [
        'downloadId' => '1NHrdYatR1rtqs2gPVfdvO0BAvocH8CJi',
        'filename' => 'benchmark_data_short.zip',
        'bytes' => 6212657,
        'sha256' => 'c7511a4f5055e949a7a7c293be5541942433059d7841965f056d7f9b441a41ad',
        'source' => '.github/workflows/tests.yml Download benchmark data step',
    ],
    'benchmarkPairs' => [
        [
            'document' => 'multicolcnn.pdf',
            'sourceCommit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
            'pdfPath' => 'benchmark_data/pdfs/multicolcnn.pdf',
            'pdfBytes' => 851968,
            'pdfSha256' => '2b0e8314ff2c2680dd309ce46a49d740084d66eb39549337d2daa91215c426f8',
            'markerPath' => 'data/examples/marker/multicolcnn.md',
            'referencePath' => 'benchmark_data/references/multicolcnn.md',
            'referenceBytes' => 30542,
            'referenceSha256' => '3ff96757b43e82595410f0fa50643945fec7c5c51f7c3d7562edefbd555aaa96',
            'referenceKind' => 'external-ci-benchmark-reference',
            'scoreThreshold' => 0.34,
            'chunkLength' => 500,
            'markerExcerpt' => <<<'MARKER'
Learning to count the number of objects in an image is a deceptively difficult problem with many interesting applications, such as surveillance [20], traffic monitoring [14] and medical image analysis [22]. In many of these application areas, the objects to be counted vary widely in appearance, size and shape, and labeled training data is typically sparse.

These factors pose a significant computer vision and machine learning challenge.

Lempitsky et al. [15] showed that it is possible to learn to count without learning to explicitly detect and localize individual objects. Instead, they propose learning to predict a density map whose integral over the image equals the number of objects in the image. This approach has been adopted by many later works (Cf. [18, 28]).

However, in many counting problems, such as those counting cells in a microscope image, pedestrians in a crowd, or vehicles in a traffic jam, regressors trained on a single image scale are not reliable [18].
MARKER,
            'referenceExcerpt' => <<<'REFERENCE'
Learning to count the number of objects in an image is a deceptively difficult problem with many interesting applications, such as surveillance , traffic monitoring and medical image analysis . In many of these application areas, the objects to be counted vary widely in appearance, size and shape, and labeled training data is typically sparse. These factors pose a significant computer vision and machine learning challenge.

Lempitsky et al.  showed that it is possible to learn to count without learning to explicitly detect and localize individual objects. Instead, they propose learning to predict a density map whose integral over the image equals the number of objects in the image. This approach has been adopted by many later works (Cf. ).

However, in many counting problems, such as those counting cells in a microscope image, pedestrians in a crowd, or vehicles in a traffic jam, regressors trained on a single image scale are not reliable .
REFERENCE,
        ],
        [
            'document' => 'switch_trans.pdf',
            'sourceCommit' => 'da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34',
            'pdfPath' => 'benchmark_data/pdfs/switch_trans.pdf',
            'pdfBytes' => 1304157,
            'pdfSha256' => 'f340f6ace31abf7d0730ef461404279f40d3c890e9cc2daeb7068b3304afdbd6',
            'markerPath' => 'data/examples/marker/switch_transformers.md',
            'referencePath' => 'benchmark_data/references/switch_trans.md',
            'referenceBytes' => 82456,
            'referenceSha256' => '74f8f6cbe23873304aa182b3a46edc8df896d35542aeade95bb93254823d58e1',
            'referenceKind' => 'external-ci-benchmark-reference',
            'scoreThreshold' => 0.40,
            'chunkLength' => 500,
            'markerExcerpt' => <<<'MARKER'
Large scale training has been an effective path towards flexible and powerful neural language models (Radford et al., 2018; Kaplan et al., 2020; Brown et al., 2020). Simple architectures— backed by a generous computational budget, data set size and parameter count—surpass more complicated algorithms (Sutton, 2019). An approach followed in Radford et al. (2018);
Raffel et al. (2019); Brown et al. (2020) expands the model size of a densely-activated Transformer (Vaswani et al., 2017). While effective, it is also extremely computationally intensive (Strubell et al., 2019). Inspired by the success of model scale, but seeking greater computational efficiency, we instead propose a *sparsely-activated* expert model: the Switch Transformer. In our case the sparsity comes from activating a *subset* of the neural network weights for each incoming example.
MARKER,
            'referenceExcerpt' => <<<'REFERENCE'
Large scale training has been an effective path towards flexible and powerful neural language models . Simple architectures—backed by a generous computational budget, data set size and parameter count—surpass more complicated algorithms . An approach followed in expands the model size of a densely-activated Transformer . While effective, it is also extremely computationally intensive . Inspired by the success of model scale, but seeking greater computational efficiency, we instead propose a sparsely-activated expert model: the Switch Transformer. In our case the sparsity comes from activating a subset of the neural network weights for each incoming example.
REFERENCE,
        ],
    ],
];
