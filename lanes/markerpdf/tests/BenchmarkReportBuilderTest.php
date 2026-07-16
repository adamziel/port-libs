<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportBuilder;
use PortLibs\MarkerPDF\BenchmarkReportVerifier;

$longText = str_repeat('WordPress import content stays aligned with the reference. ', 12);

return [
    'builds upstream overall.py report shape from supplied benchmark runs' => static function (TestRunner $t) use ($longText): void {
        $report = (new BenchmarkReportBuilder())->build([
            [
                'method' => 'marker',
                'document' => 'multicolcnn.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 2.0,
                'pages' => 2,
            ],
            [
                'method' => 'marker',
                'document' => 'switch_trans.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 6.0,
                'pages' => 6,
            ],
        ]);

        $t->same(['multicolcnn.pdf', 'switch_trans.pdf'], array_keys($report['marker']['files']));
        $t->same(['time' => 2.0, 'score' => 1.0, 'pages' => 2], $report['marker']['files']['multicolcnn.pdf']);
        $t->same(1.0, $report['marker']['avg_score']);
        $t->same(1.0, $report['marker']['time_per_page']);
        $t->same(4.0, $report['marker']['time_per_doc']);
    },
    'exports upstream overall.py json report and tabulate source rows' => static function (TestRunner $t) use ($longText): void {
        $builder = new BenchmarkReportBuilder();
        $report = $builder->build([
            [
                'method' => 'marker',
                'document' => 'multicolcnn.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 2.0,
                'pages' => 2,
            ],
            [
                'method' => 'marker',
                'document' => 'switch_trans.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 6.0,
                'pages' => 6,
            ],
            [
                'method' => 'naive',
                'document' => 'multicolcnn.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 4.0,
                'pages' => 2,
            ],
            [
                'method' => 'naive',
                'document' => 'switch_trans.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 4.0,
                'pages' => 6,
            ],
        ]);
        $outputFile = sys_get_temp_dir() . '/markerpdf-overall-report-' . bin2hex(random_bytes(4)) . '.json';

        try {
            $tables = $builder->outputTables($report);
            $builder->writeJsonReport($outputFile, $report);
            $decoded = json_decode((string) file_get_contents($outputFile), true, flags: JSON_THROW_ON_ERROR);

            $t->same(['Method', 'Average Score', 'Time per page', 'Time per document'], $tables['summary_headers']);
            $t->same(['Method', 'multicolcnn.pdf', 'switch_trans.pdf'], $tables['score_headers']);
            $t->same(['marker', 1.0, 1.0, 4.0], $tables['summary_rows'][0]);
            $t->same(['naive', 1.0, 1.0], $tables['score_rows'][1]);
            $t->same(
                $report['marker']['files']['multicolcnn.pdf']['score'],
                (float) $decoded['marker']['files']['multicolcnn.pdf']['score']
            );
            $t->contains('"files": {', (string) file_get_contents($outputFile));
        } finally {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }
    },
    'uses unique benchmark document pages for all methods like upstream total_pages' => static function (TestRunner $t) use ($longText): void {
        $report = (new BenchmarkReportBuilder())->build([
            [
                'method' => 'marker',
                'document' => 'multicolcnn.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 2.0,
                'pages' => 2,
            ],
            [
                'method' => 'marker',
                'document' => 'switch_trans.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 6.0,
                'pages' => 6,
            ],
            [
                'method' => 'nougat',
                'document' => 'multicolcnn.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 10.0,
                'pages' => 2,
            ],
            [
                'method' => 'nougat',
                'document' => 'switch_trans.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 14.0,
                'pages' => 6,
            ],
        ]);

        $t->same(1.0, $report['marker']['time_per_page']);
        $t->same(3.0, $report['nougat']['time_per_page']);
        $t->same(12.0, $report['nougat']['time_per_doc']);
    },
    'builds and verifies actual CI benchmark excerpt report for WordPress gates' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/upstream-ci-benchmark-short.php';
        $runs = [];
        foreach ($fixture['benchmarkPairs'] as $index => $pair) {
            $runs[] = [
                'method' => 'marker',
                'document' => $pair['document'],
                'hypothesis' => $pair['markerExcerpt'],
                'reference' => $pair['referenceExcerpt'],
                'time' => (float) ($index + 1),
                'pages' => $index + 2,
                'chunkLength' => $pair['chunkLength'],
            ];
        }

        $report = (new BenchmarkReportBuilder())->build($runs);
        (new BenchmarkReportVerifier())->verifyMarkerScores($report);

        $t->same(2, count($report['marker']['files']));
        $t->true($report['marker']['files']['multicolcnn.pdf']['score'] > 0.34);
        $t->true($report['marker']['files']['switch_trans.pdf']['score'] > 0.40);
        $t->same(0.6, $report['marker']['time_per_page']);
    },
    'rejects malformed benchmark report rows before quality gates consume them' => static function (TestRunner $t) use ($longText): void {
        $builder = new BenchmarkReportBuilder();

        $t->throws(InvalidArgumentException::class, static fn () => $builder->build([]));
        $t->throws(InvalidArgumentException::class, static fn () => $builder->build([
            [
                'method' => 'marker',
                'document' => 'multicolcnn.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 1.0,
                'pages' => 0,
            ],
        ]));
        $t->throws(InvalidArgumentException::class, static fn () => $builder->build([
            [
                'method' => 'marker',
                'document' => 'multicolcnn.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 1.0,
                'pages' => 1,
            ],
            [
                'method' => 'marker',
                'document' => 'multicolcnn.pdf',
                'hypothesis' => $longText,
                'reference' => $longText,
                'time' => 2.0,
                'pages' => 1,
            ],
        ]));
    },
];
