<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\BenchmarkReportVerifier;
use PortLibs\MarkerPDF\BenchmarkRunner;

$makeTempDir = static function (): string {
    $path = sys_get_temp_dir() . '/markerpdf-benchmark-runner-' . bin2hex(random_bytes(4));
    if (!mkdir($path, 0777, true) && !is_dir($path)) {
        throw new RuntimeException('Unable to create temporary markerpdf benchmark runner folder.');
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

$prepareCiFolders = static function (string $pdfFolder, string $referenceFolder): array {
    $fixture = require __DIR__ . '/../fixtures/upstream-ci-benchmark-short.php';
    $pairsByDocument = [];

    foreach ($fixture['benchmarkPairs'] as $pair) {
        $pairsByDocument[$pair['document']] = $pair;
        file_put_contents($pdfFolder . DIRECTORY_SEPARATOR . $pair['document'], "%PDF-1.4\n% " . $pair['document'] . "\n%%EOF");
        file_put_contents(
            $referenceFolder . DIRECTORY_SEPARATOR . preg_replace('/\.[^.]*$/', '.md', $pair['document']),
            $pair['referenceExcerpt']
        );
    }
    file_put_contents($pdfFolder . DIRECTORY_SEPARATOR . 'ignore.txt', 'not a benchmark pdf');

    return $pairsByDocument;
};

return [
    'runs upstream overall.py style marker benchmark loop over actual CI pairs' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $prepareCiFolders): void {
        $pdfFolder = $makeTempDir();
        $referenceFolder = $makeTempDir();
        $markdownFolder = $makeTempDir();
        try {
            $pairsByDocument = $prepareCiFolders($pdfFolder, $referenceFolder);
            $runner = new BenchmarkRunner();

            $result = $runner->run(
                $pdfFolder,
                $referenceFolder,
                [
                    'marker' => static fn (string $pdfPath, string $document): string => $pairsByDocument[$document]['markerExcerpt'],
                ],
                static fn (string $pdfPath): int => str_contains($pdfPath, 'switch_trans') ? 4 : 3,
                $markdownFolder,
                array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument)
            );

            (new BenchmarkReportVerifier())->verifyMarkerScores($result['report']);

            $t->same(['multicolcnn.pdf', 'switch_trans.pdf'], $result['benchmark_files']);
            $t->same(2, count($result['runs']));
            $t->same(3, $result['report']['marker']['files']['multicolcnn.pdf']['pages']);
            $t->same(4, $result['report']['marker']['files']['switch_trans.pdf']['pages']);
            $t->true($result['report']['marker']['files']['multicolcnn.pdf']['score'] > 0.34);
            $t->true($result['report']['marker']['files']['switch_trans.pdf']['score'] > 0.40);
            $t->same(2, count($result['written_markdown']));
            $t->contains('Learning to count', (string) file_get_contents($markdownFolder . DIRECTORY_SEPARATOR . 'marker_multicolcnn.md'));
            $t->contains('Switch Transformer', (string) file_get_contents($markdownFolder . DIRECTORY_SEPARATOR . 'marker_switch_trans.md'));
        } finally {
            $removeTree($pdfFolder);
            $removeTree($referenceFolder);
            $removeTree($markdownFolder);
        }
    },
    'supports supplied comparison methods while sharing per-document page counts' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $prepareCiFolders): void {
        $pdfFolder = $makeTempDir();
        $referenceFolder = $makeTempDir();
        try {
            $pairsByDocument = $prepareCiFolders($pdfFolder, $referenceFolder);

            $result = (new BenchmarkRunner())->run(
                $pdfFolder,
                $referenceFolder,
                [
                    'marker' => static fn (string $pdfPath, string $document): array => ['text' => $pairsByDocument[$document]['markerExcerpt']],
                    'nougat' => static fn (string $pdfPath, string $document, string $reference): string => $reference,
                ],
                static fn (): int => 2
            );

            $t->same(['marker', 'nougat'], array_values(array_keys($result['report'])));
            $t->same(4, count($result['runs']));
            $t->same(1.0, $result['report']['nougat']['avg_score']);
            $t->true($result['report']['marker']['time_per_page'] >= 0.0);
            $t->true($result['report']['nougat']['time_per_doc'] >= 0.0);
        } finally {
            $removeTree($pdfFolder);
            $removeTree($referenceFolder);
        }
    },
    'maps current-base overall.py runtime options into supplied benchmark API callbacks' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $prepareCiFolders): void {
        $pdfFolder = $makeTempDir();
        $referenceFolder = $makeTempDir();
        $markdownFolder = $makeTempDir();
        try {
            $pairsByDocument = $prepareCiFolders($pdfFolder, $referenceFolder);
            $contexts = [];

            $result = (new BenchmarkRunner())->run(
                $pdfFolder,
                $referenceFolder,
                [
                    'nougat' => static function (string $pdfPath, string $document, string $reference, array $context) use (&$contexts): string {
                        $contexts[] = $context;

                        return $reference;
                    },
                    'marker' => static function (string $pdfPath, string $document, string $reference, array $context) use (&$contexts, $pairsByDocument): string {
                        $contexts[] = $context;

                        return $pairsByDocument[$document]['markerExcerpt'];
                    },
                ],
                static fn (): int => 2,
                $markdownFolder,
                array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument),
                null,
                [
                    'nougat' => true,
                    'marker_batch_multiplier' => '3',
                    'nougat_batch_size' => 2,
                    'profile_memory' => true,
                ]
            );

            $t->same(['marker', 'nougat'], $result['runtime']['methods']);
            $t->same(['marker', 'nougat'], array_values(array_keys($result['report'])));
            $t->same(3, $result['runtime']['marker_batch_multiplier']);
            $t->same(2, $result['runtime']['nougat_batch_size']);
            $t->same(true, $result['runtime']['profile_memory']);
            $t->same('model_load.pickle', $result['runtime']['model_load_snapshot']);
            $t->same(false, $result['runtime']['executes_external_tools']);
            $t->same(true, $result['runtime']['callback_sandbox']['enabled']);
            $t->same(['pdf', 'reference', 'markdown_output_folder'], $result['runtime']['callback_sandbox']['watched_inputs']);
            $t->same(true, $result['runtime']['callback_sandbox']['runner_writes_markdown_after_callback']);
            $t->same(2, count($result['runtime']['conversion_snapshots']));
            $t->same('marker_memory_0.pickle', $result['runtime']['conversion_snapshots'][0]['snapshot']);
            $t->same('marker_memory_1.pickle', $result['runtime']['conversion_snapshots'][1]['snapshot']);
            $t->same('marker', $contexts[0]['method']);
            $t->same('marker_memory_0.pickle', $contexts[0]['memory_snapshot']);
            $t->same(true, $contexts[0]['callback_sandbox']);
            $t->same(3, $contexts[0]['batch_multiplier']);
            $t->same('nougat', $contexts[1]['method']);
            $t->same(2, $contexts[1]['batch_size']);
            $t->true(!array_key_exists('memory_snapshot', $contexts[1]));
            $t->same(['marker_multicolcnn.md', 'nougat_multicolcnn.md', 'marker_switch_trans.md', 'nougat_switch_trans.md'], array_map('basename', $result['written_markdown']));
        } finally {
            $removeTree($pdfFolder);
            $removeTree($referenceFolder);
            $removeTree($markdownFolder);
        }
    },
    'sandboxes supplied benchmark callbacks from mutating staged WordPress benchmark files' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $prepareCiFolders): void {
        $caseFactories = [
            'page counter tampers with pdf bytes' => static fn (string $referenceFolder, string $markdownFolder): array => [
                'converter' => static fn (): string => 'safe output',
                'pageCounter' => static function (string $pdfPath): int {
                    file_put_contents($pdfPath, "%PDF-1.4\n% tampered by page counter\n%%EOF");

                    return 1;
                },
            ],
            'converter tampers with pdf bytes' => static fn (string $referenceFolder, string $markdownFolder): array => [
                'converter' => static function (string $pdfPath): string {
                    file_put_contents($pdfPath, "%PDF-1.4\n% tampered by converter\n%%EOF");

                    return 'unsafe output';
                },
                'pageCounter' => static fn (): int => 1,
            ],
            'converter tampers with reference markdown' => static fn (string $referenceFolder, string $markdownFolder): array => [
                'converter' => static function (string $pdfPath, string $document, string $reference, array $context) use ($referenceFolder): string {
                    file_put_contents($referenceFolder . DIRECTORY_SEPARATOR . preg_replace('/\.[^.]*$/', '.md', $document), 'changed reference');

                    return 'unsafe output';
                },
                'pageCounter' => static fn (): int => 1,
            ],
            'converter writes rogue markdown before runner output' => static fn (string $referenceFolder, string $markdownFolder): array => [
                'converter' => static function () use ($markdownFolder): string {
                    file_put_contents($markdownFolder . DIRECTORY_SEPARATOR . 'rogue.md', 'callback artifact');

                    return 'unsafe output';
                },
                'pageCounter' => static fn (): int => 1,
            ],
        ];

        foreach ($caseFactories as $caseFactory) {
            $pdfFolder = $makeTempDir();
            $referenceFolder = $makeTempDir();
            $markdownFolder = $makeTempDir();
            try {
                $prepareCiFolders($pdfFolder, $referenceFolder);
                $case = $caseFactory($referenceFolder, $markdownFolder);

                $t->throws(
                    RuntimeException::class,
                    static fn (): array => (new BenchmarkRunner())->run(
                        $pdfFolder,
                        $referenceFolder,
                        ['marker' => $case['converter']],
                        $case['pageCounter'],
                        $markdownFolder
                    )
                );
            } finally {
                $removeTree($pdfFolder);
                $removeTree($referenceFolder);
                $removeTree($markdownFolder);
            }
        }
    },
    'allows explicit benchmark callback sandbox opt out for diagnostic fixtures' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $prepareCiFolders): void {
        $pdfFolder = $makeTempDir();
        $referenceFolder = $makeTempDir();
        $markdownFolder = $makeTempDir();
        try {
            $pairsByDocument = $prepareCiFolders($pdfFolder, $referenceFolder);

            $result = (new BenchmarkRunner())->run(
                $pdfFolder,
                $referenceFolder,
                [
                    'marker' => static function (string $pdfPath, string $document) use ($pairsByDocument, $markdownFolder): string {
                        file_put_contents($markdownFolder . DIRECTORY_SEPARATOR . 'diagnostic-' . $document . '.txt', 'unsafe diagnostic');

                        return $pairsByDocument[$document]['markerExcerpt'];
                    },
                ],
                static fn (): int => 1,
                $markdownFolder,
                array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument),
                null,
                ['sandbox_callbacks' => false]
            );

            $t->same(false, $result['runtime']['callback_sandbox']['enabled']);
            $t->same(['diagnostic-multicolcnn.pdf.txt', 'diagnostic-switch_trans.pdf.txt'], array_values(array_filter(
                array_map(
                    static fn (string $path): string => basename($path),
                    glob($markdownFolder . DIRECTORY_SEPARATOR . 'diagnostic-*.txt') ?: []
                )
            )));
            $t->same(['marker_multicolcnn.md', 'marker_switch_trans.md'], array_map('basename', $result['written_markdown']));
        } finally {
            $removeTree($pdfFolder);
            $removeTree($referenceFolder);
            $removeTree($markdownFolder);
        }
    },
    'writes overall.py style report output file and exposes score table rows' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $prepareCiFolders): void {
        $pdfFolder = $makeTempDir();
        $referenceFolder = $makeTempDir();
        $markdownFolder = $makeTempDir();
        try {
            $pairsByDocument = $prepareCiFolders($pdfFolder, $referenceFolder);
            $reportOutput = $markdownFolder . DIRECTORY_SEPARATOR . 'overall.json';

            $result = (new BenchmarkRunner())->run(
                $pdfFolder,
                $referenceFolder,
                [
                    'marker' => static fn (string $pdfPath, string $document): string => $pairsByDocument[$document]['markerExcerpt'],
                    'nougat' => static fn (string $pdfPath, string $document, string $reference): string => $reference,
                ],
                static fn (string $pdfPath): int => str_contains($pdfPath, 'switch_trans') ? 4 : 3,
                $markdownFolder,
                array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument),
                $reportOutput
            );
            $decoded = json_decode((string) file_get_contents($reportOutput), true, flags: JSON_THROW_ON_ERROR);

            $t->same($reportOutput, $result['report_output']);
            $t->true(is_file($reportOutput));
            $t->same(['Method', 'multicolcnn.pdf', 'switch_trans.pdf'], $result['output_tables']['score_headers']);
            $t->same(['marker', 'nougat'], array_column($result['output_tables']['summary_rows'], 0));
            $t->same($result['report']['marker']['avg_score'], $decoded['marker']['avg_score']);
            $t->same(4, count($result['written_markdown']));
            $t->contains('"nougat"', (string) file_get_contents($reportOutput));
        } finally {
            $removeTree($pdfFolder);
            $removeTree($referenceFolder);
            $removeTree($markdownFolder);
        }
    },
    'records upstream stop_memory_profiling snapshot errors as review-only runtime metadata' => static function (TestRunner $t): void {
        $report = (new BenchmarkRunner())->memorySnapshotFailureReport(
            'marker_memory_0.pickle',
            new RuntimeException('CUDA snapshot unavailable')
        );

        $t->same('marker_memory_0.pickle', $report['snapshot']);
        $t->same('CUDA snapshot unavailable', $report['error']);
        $t->contains('Failed to capture memory snapshot CUDA snapshot unavailable', $report['log_line']);
        $t->same(true, $report['continues_after_failure']);
        $t->same(true, $report['recording_disabled_after_error']);
        $t->same(false, $report['executes_cuda_memory_history']);
        $t->same(true, $report['review_only']);
    },
    'records benchmark method and page-counter failures as review-only runtime telemetry' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $prepareCiFolders): void {
        $pdfFolder = $makeTempDir();
        $referenceFolder = $makeTempDir();
        $markdownFolder = $makeTempDir();
        try {
            $pairsByDocument = $prepareCiFolders($pdfFolder, $referenceFolder);

            $methodFailure = (new BenchmarkRunner())->runWithErrorTelemetry(
                $pdfFolder,
                $referenceFolder,
                [
                    'marker' => static function (string $pdfPath, string $document, string $reference, array $context) use ($pairsByDocument): string {
                        if ($document === 'switch_trans.pdf') {
                            throw new RuntimeException('surya model boundary unavailable');
                        }

                        return $pairsByDocument[$document]['markerExcerpt'];
                    },
                ],
                static fn (): int => 2,
                $markdownFolder,
                array_map(static fn (array $pair): int => $pair['chunkLength'], $pairsByDocument),
                null,
                ['profile_memory' => true]
            );

            $t->same(false, $methodFailure['success']);
            $t->same(null, $methodFailure['result']);
            $telemetry = $methodFailure['telemetry'];
            $t->same('converter', $telemetry['phase']);
            $t->same('marker', $telemetry['method']);
            $t->same('switch_trans.pdf', $telemetry['document']);
            $t->same(1, $telemetry['benchmark_index']);
            $t->same('marker_memory_1.pickle', $telemetry['memory_snapshot']);
            $t->same(true, $telemetry['callback_sandbox']);
            $t->contains('Benchmark method marker failed for switch_trans.pdf: surya model boundary unavailable', $telemetry['message_line']);
            $t->contains('RuntimeException: surya model boundary unavailable', $telemetry['traceback']);
            $t->same(true, $telemetry['default_runner_fails_fast']);
            $t->same(false, $telemetry['continues_after_failure']);
            $t->same(false, $telemetry['writes_markdown_after_failure']);
            $t->same(false, $telemetry['executes_external_tools']);
            $t->same(false, $telemetry['executes_python_or_models']);
            $t->same(true, $telemetry['review_only']);
            $t->true(is_file($markdownFolder . DIRECTORY_SEPARATOR . 'marker_multicolcnn.md'));
            $t->same(false, is_file($markdownFolder . DIRECTORY_SEPARATOR . 'marker_switch_trans.md'));

            $pageCounterFailure = (new BenchmarkRunner())->runWithErrorTelemetry(
                $pdfFolder,
                $referenceFolder,
                [
                    'marker' => static fn (): string => 'unused',
                ],
                static fn (): int => throw new RuntimeException('PDFium page count unavailable'),
                $markdownFolder
            );

            $t->same(false, $pageCounterFailure['success']);
            $t->same('page_counter', $pageCounterFailure['telemetry']['phase']);
            $t->same(null, $pageCounterFailure['telemetry']['method']);
            $t->same('multicolcnn.pdf', $pageCounterFailure['telemetry']['document']);
            $t->same(null, $pageCounterFailure['telemetry']['memory_snapshot']);
            $t->same(true, $pageCounterFailure['telemetry']['callback_sandbox']);
            $t->contains('Benchmark page counter failed for multicolcnn.pdf: PDFium page count unavailable', $pageCounterFailure['telemetry']['message_line']);
            $t->same(false, $pageCounterFailure['telemetry']['continues_after_failure']);
        } finally {
            $removeTree($pdfFolder);
            $removeTree($referenceFolder);
            $removeTree($markdownFolder);
        }
    },
    'rejects malformed benchmark runner supplied boundaries' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $pdfFolder = $makeTempDir();
        $referenceFolder = $makeTempDir();
        try {
            file_put_contents($pdfFolder . DIRECTORY_SEPARATOR . 'missing-reference.pdf', "%PDF-1.4\n%%EOF");

            $runner = new BenchmarkRunner();
            $t->throws(InvalidArgumentException::class, static fn (): array => $runner->run($pdfFolder, $referenceFolder, []));
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $runner->run(
                    $pdfFolder,
                    $referenceFolder,
                    ['marker' => static fn (): string => 'unused']
                )
            );

            file_put_contents($referenceFolder . DIRECTORY_SEPARATOR . 'missing-reference.md', 'expected');
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $runner->run(
                    $pdfFolder,
                    $referenceFolder,
                    ['marker' => static fn (): array => ['images' => []]]
                )
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $runner->run(
                    $pdfFolder,
                    $referenceFolder,
                    ['marker' => static fn (): string => 'ok'],
                    static fn (): int => 0
                )
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $runner->run(
                    $pdfFolder,
                    $referenceFolder,
                    ['marker' => static fn (): string => 'ok'],
                    runtimeOptions: ['nougat' => true]
                )
            );
            $t->throws(
                InvalidArgumentException::class,
                static fn (): array => $runner->run(
                    $pdfFolder,
                    $referenceFolder,
                    ['marker' => static fn (): string => 'ok'],
                    runtimeOptions: ['marker_batch_multiplier' => 0]
                )
            );
        } finally {
            $removeTree($pdfFolder);
            $removeTree($referenceFolder);
        }
    },
];
