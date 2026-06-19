<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\BenchmarkScorer;

return [
    'verifies marker benchmark report thresholds like upstream script' => static function (TestRunner $t): void {
        $verifier = new BenchmarkReportVerifier();
        $report = [
            'marker' => [
                'files' => [
                    'multicolcnn.pdf' => ['score' => 0.3401],
                    'switch_trans.pdf' => ['score' => 0.4001],
                ],
            ],
        ];

        $verifier->verifyMarkerScores($report);

        $t->same(['multicolcnn.pdf' => 0.34, 'switch_trans.pdf' => 0.40], $verifier->markerThresholds());
        $t->throws(
            RuntimeException::class,
            static fn () => $verifier->verifyMarkerScores([
                'marker' => [
                    'files' => [
                        'multicolcnn.pdf' => ['score' => 0.34],
                        'switch_trans.pdf' => ['score' => 0.8],
                    ],
                ],
            ])
        );
        $t->throws(
            RuntimeException::class,
            static fn () => $verifier->verifyMarkerScores([
                'marker' => [
                    'files' => [
                        'multicolcnn.pdf' => ['score' => 0.8],
                        'switch_trans.pdf' => ['score' => 0.40],
                    ],
                ],
            ])
        );
    },
    'rejects malformed marker reports before WordPress quality gates trust them' => static function (TestRunner $t): void {
        $verifier = new BenchmarkReportVerifier();

        $t->throws(InvalidArgumentException::class, static fn () => $verifier->verifyMarkerScores([]));
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $verifier->verifyMarkerScores([
                'marker' => [
                    'files' => [
                        'multicolcnn.pdf' => ['score' => 'not-a-number'],
                        'switch_trans.pdf' => ['score' => 0.9],
                    ],
                ],
            ])
        );
    },
    'verifies table benchmark average threshold like upstream script' => static function (TestRunner $t): void {
        $verifier = new BenchmarkReportVerifier();

        $verifier->verifyTableScores([
            ['score' => 0.7],
            ['score' => 0.72],
            ['score' => 0.68],
        ]);

        $t->same(0.7, $verifier->tableAverageThreshold());
        $t->throws(RuntimeException::class, static fn () => $verifier->verifyTableScores([
            ['score' => 0.69],
            ['score' => 0.70],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $verifier->verifyTableScores([]));
        $t->throws(InvalidArgumentException::class, static fn () => $verifier->verifyTableScores([['cells' => 4]]));
    },
    'scores actual CI benchmark reference excerpts and verifies marker report' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-ci-benchmark-short.php';
        $scorer = new BenchmarkScorer();
        $report = ['marker' => ['files' => []]];

        $t->same('benchmark_data_short.zip', $fixture['archive']['filename']);
        $t->same('c7511a4f5055e949a7a7c293be5541942433059d7841965f056d7f9b441a41ad', $fixture['archive']['sha256']);
        $t->same(2, count($fixture['benchmarkPairs']));

        foreach ($fixture['benchmarkPairs'] as $pair) {
            $score = $scorer->scoreText($pair['markerExcerpt'], $pair['referenceExcerpt'], $pair['chunkLength']);
            $report['marker']['files'][$pair['document']] = ['score' => $score];

            $t->same('external-ci-benchmark-reference', $pair['referenceKind']);
            $t->true($score > $pair['scoreThreshold'], $pair['document'] . ' excerpt score did not clear the upstream CI threshold.');
        }

        (new BenchmarkReportVerifier())->verifyMarkerScores($report);
        $t->true($report['marker']['files']['multicolcnn.pdf']['score'] > 0.34);
        $t->true($report['marker']['files']['switch_trans.pdf']['score'] > 0.40);
    },
    'maps upstream CI benchmark fixture evidence and heavy runtime exclusions' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-ci-benchmark-short.php';
        $scorer = new BenchmarkScorer();
        $report = ['marker' => ['files' => []]];
        foreach ($fixture['benchmarkPairs'] as $pair) {
            $report['marker']['files'][$pair['document']] = [
                'score' => $scorer->scoreText($pair['markerExcerpt'], $pair['referenceExcerpt'], $pair['chunkLength']),
            ];
        }

        $evidence = (new BenchmarkReportVerifier())->verifyUpstreamCiBenchmarkEvidence($fixture, $report);

        $t->same('benchmark_data_short.zip', $evidence['archive']['filename']);
        $t->same('c7511a4f5055e949a7a7c293be5541942433059d7841965f056d7f9b441a41ad', $evidence['archive']['sha256']);
        $t->same(2, $evidence['mapped_native_fixture_count']);
        $t->same(2, $evidence['required_document_count']);
        $t->same(['multicolcnn.pdf', 'switch_trans.pdf'], array_column($evidence['documents'], 'document'));
        $t->same('benchmark_data/pdfs/multicolcnn.pdf', $evidence['documents'][0]['pdf_path']);
        $t->same('data/examples/marker/switch_transformers.md', $evidence['documents'][1]['marker_example_path']);
        $t->true($evidence['passes_upstream_ci_marker_thresholds']);
        $t->same(false, $evidence['executes_python_or_models']);
        $t->same(false, $evidence['executes_external_pdf_tools']);
        $t->true(in_array('Surya/Torch OCR, layout, table, and recognition models', $evidence['heavy_runtime_exclusions'], true));
    },
];
