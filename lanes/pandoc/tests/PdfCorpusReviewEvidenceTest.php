<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;

if (!defined('PDF_CORPUS_REPORT_LIBRARY_ONLY')) {
    define('PDF_CORPUS_REPORT_LIBRARY_ONLY', true);
}
require_once dirname(__DIR__, 3) . '/tools/pdf-corpus-report.php';

$removeTree = static function (string $root): void {
    if (!is_dir($root)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($root);
};

$makeReviewFixture = static function (string $verdict = 'pass', bool $sourceComplete = true) use ($removeTree): array {
    $root = sys_get_temp_dir() . '/port-libs-pdf-review-' . bin2hex(random_bytes(8));
    mkdir($root, 0777, true);
    $reportPath = $root . '/execution-report.json';
    $sourceIntegrity = [
        'complete' => $sourceComplete,
        'pdfDocumentComplete' => $sourceComplete,
        'pdfSemanticTextComplete' => $sourceComplete,
        'pdfSourceBindingComplete' => $sourceComplete,
        'pdfSourceEdgeMappingComplete' => $sourceComplete,
        'pdfOrderedSignificantCharactersPreserved' => true,
        'pdfUnresolvedSourceOccurrences' => $sourceComplete ? 0 : 2,
    ];
    $artifactBytes = '%PDF-review-fixture';
    $artifactIdentity = [
        'pinStatus' => 'remote-hash-pinned',
        'bytes' => strlen($artifactBytes),
        'sha256' => hash('sha256', $artifactBytes),
    ];
    $modeOutputs = static function (string $mode) use ($root): array {
        $outputs = [];
        foreach ([
            'plain' => [$mode . '.plain.txt', 'plain'],
            'html' => [$mode . '.html', 'html'],
            'wordpress' => [$mode . '.wordpress.html', 'wordpress'],
        ] as $kind => [$basename, $contents]) {
            file_put_contents($root . '/' . $basename, $contents);
            $outputs[$kind] = [
                'path' => 'outputs/candidate/' . $basename,
                'bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents),
            ];
        }

        return $outputs;
    };
    $reportBytes = json_encode([
        'schemaVersion' => 1,
        'corpusId' => 'candidate',
        'artifact' => $artifactIdentity,
        'executionState' => 'remote_fetched_verified_executed',
        'modes' => [
            'geometry-on' => [
                'ok' => true,
                'outputs' => $modeOutputs('geometry-on'),
                'sourceIntegrity' => $sourceIntegrity,
            ],
            'repair-only' => [
                'ok' => true,
                'outputs' => $modeOutputs('repair-only'),
                'sourceIntegrity' => $sourceIntegrity,
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    file_put_contents($reportPath, $reportBytes);
    $executionReport = [
        'ok' => true,
        'schemaVersion' => 1,
        'path' => 'outputs/candidate/execution-report.json',
        'absolutePath' => $reportPath,
        'bytes' => strlen($reportBytes),
        'sha256' => hash('sha256', $reportBytes),
    ];
    $artifact = [
        'ok' => true,
        'pinned' => true,
        'fetched' => true,
        'pinStatus' => 'remote-hash-pinned',
        'bytes' => strlen($artifactBytes),
        'sha256' => hash('sha256', $artifactBytes),
    ];
    $entry = [
        'id' => 'candidate',
        'semanticExpectations' => ['status' => 'pending_manual_review'],
    ];

    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    if (!is_string($png)) {
        throw new RuntimeException('Unable to decode PNG fixture.');
    }
    $screenshots = [];
    foreach (['desktop', 'mobile'] as $viewport) {
        foreach (['source', 'output'] as $side) {
            $name = $viewport . '-' . $side . '.png';
            file_put_contents($root . '/' . $name, $png);
            $screenshots[$viewport][$side] = [
                'path' => $name,
                'bytes' => strlen($png),
                'sha256' => hash('sha256', $png),
            ];
        }
    }
    $evidence = [
        'schemaVersion' => 1,
        'corpusId' => 'candidate',
        'artifact' => [
            'bytes' => $artifact['bytes'],
            'sha256' => $artifact['sha256'],
        ],
        'executionReport' => [
            'path' => $executionReport['path'],
            'bytes' => $executionReport['bytes'],
            'sha256' => $executionReport['sha256'],
        ],
        'screenshots' => $screenshots,
        'verdict' => [
            'result' => $verdict,
            'reviewer' => 'Fixture Reviewer',
            'reviewedAt' => '2026-07-17T12:00:00Z',
            'notes' => 'Compared the exact source and output at both required viewports.',
        ],
    ];
    file_put_contents(
        $root . '/candidate.json',
        json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
    );

    return [
        'root' => $root,
        'entry' => $entry,
        'artifact' => $artifact,
        'executionReport' => $executionReport,
        'evidence' => $evidence,
        'cleanup' => static fn (): mixed => $removeTree($root),
    ];
};

return [
    'review evidence requires both authoritative conversion modes' => static function (TestRunner $t) use ($makeReviewFixture): void {
        $fixture = $makeReviewFixture();
        try {
            $path = (string) ($fixture['executionReport']['absolutePath'] ?? '');
            $receipt = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $t->same([], pdf_corpus_execution_report_source_integrity_issues(
                $path,
                'candidate',
                $fixture['artifact'],
                'remote_fetched_verified_executed'
            ));
            $t->same(
                ['review-execution-report-source-integrity-corpus-id-mismatch'],
                pdf_corpus_execution_report_source_integrity_issues(
                    $path,
                    'different-candidate',
                    $fixture['artifact'],
                    'remote_fetched_verified_executed'
                )
            );
            file_put_contents($fixture['root'] . '/geometry-on.html', 'changed');
            $t->true(in_array(
                'review-execution-report-geometry-on-output-file-mismatch',
                pdf_corpus_execution_report_source_integrity_issues(
                    $path,
                    'candidate',
                    $fixture['artifact'],
                    'remote_fetched_verified_executed'
                ),
                true
            ));
            file_put_contents($fixture['root'] . '/geometry-on.html', 'html');
            unset($receipt['modes']['geometry-on']['outputs']);
            file_put_contents($path, json_encode($receipt, JSON_THROW_ON_ERROR));
            $t->true(in_array(
                'review-execution-report-geometry-on-outputs-invalid',
                pdf_corpus_execution_report_source_integrity_issues(
                    $path,
                    'candidate',
                    $fixture['artifact'],
                    'remote_fetched_verified_executed'
                ),
                true
            ));
            unset($receipt['modes']['repair-only']);
            file_put_contents($path, json_encode($receipt, JSON_THROW_ON_ERROR));

            $t->same(
                ['review-execution-report-source-integrity-modes-invalid'],
                pdf_corpus_execution_report_source_integrity_issues(
                    $path,
                    'candidate',
                    $fixture['artifact'],
                    'remote_fetched_verified_executed'
                )
            );
        } finally {
            ($fixture['cleanup'])();
        }
    },
    'parent watchdog returns and reaps a successful isolated worker' => static function (TestRunner $t) use ($removeTree): void {
        $root = sys_get_temp_dir() . '/port-libs-pdf-watchdog-success-' . bin2hex(random_bytes(8));
        mkdir($root, 0777, true);
        try {
            $result = run_pdf_corpus_with_watchdog(
                static fn (): array => [
                    'ok' => true,
                    'value' => 'complete',
                    'workerPid' => getmypid(),
                ],
                2,
                'unit-success',
                $root
            );
            if (pdf_corpus_watchdog_missing_capabilities() !== []) {
                $t->same(false, $result['ok'] ?? null);
                $t->same(false, $result['watchdog']['enforced'] ?? null);
                $t->contains('parent watchdog is unavailable', (string) ($result['error'] ?? ''));
                return;
            }

            $t->same(true, $result['ok'] ?? null);
            $t->same('complete', $result['value'] ?? null);
            $t->same(true, $result['watchdog']['enforced'] ?? null);
            $t->same('parent-process-wall-clock', $result['watchdog']['mechanism'] ?? null);
            $t->same(false, $result['watchdog']['timedOut'] ?? null);
            $t->same(true, $result['watchdog']['reaped'] ?? null);
            $workerStatus = 0;
            $t->same(-1, pcntl_waitpid((int) ($result['workerPid'] ?? 0), $workerStatus, WNOHANG));
            $t->same([], glob($root . '/.pdf-corpus-worker-*') ?: []);
        } finally {
            $removeTree($root);
        }
    },

    'parent watchdog turns a worker exception into a bounded mode failure' => static function (TestRunner $t) use ($removeTree): void {
        $root = sys_get_temp_dir() . '/port-libs-pdf-watchdog-exception-' . bin2hex(random_bytes(8));
        mkdir($root, 0777, true);
        try {
            $result = run_pdf_corpus_with_watchdog(
                static function (): array {
                    throw new RuntimeException('worker exploded');
                },
                2,
                'unit-exception',
                $root
            );
            if (pdf_corpus_watchdog_missing_capabilities() !== []) {
                $t->same(false, $result['ok'] ?? null);
                $t->same(false, $result['watchdog']['enforced'] ?? null);
                return;
            }

            $t->same(false, $result['ok'] ?? null);
            $t->contains('RuntimeException: worker exploded', (string) ($result['error'] ?? ''));
            $t->same(false, $result['watchdog']['timedOut'] ?? null);
            $t->same(true, $result['watchdog']['reaped'] ?? null);
            $t->same([], glob($root . '/.pdf-corpus-worker-*') ?: []);
        } finally {
            $removeTree($root);
        }
    },

    'parent watchdog kills and reaps a worker that catches its alarm exception' => static function (TestRunner $t) use ($removeTree): void {
        $root = sys_get_temp_dir() . '/port-libs-pdf-watchdog-timeout-' . bin2hex(random_bytes(8));
        mkdir($root, 0777, true);
        $pidPath = $root . '/worker.pid';
        try {
            $started = microtime(true);
            $result = run_pdf_corpus_with_watchdog(
                static function () use ($pidPath): array {
                    if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
                        pcntl_async_signals(true);
                        pcntl_signal(SIGALRM, static function (): void {
                            throw new RuntimeException('recoverable child alarm');
                        });
                        try {
                            posix_kill(getmypid(), SIGALRM);
                        } catch (RuntimeException) {
                            // Model conversion recovery consuming the one-shot alarm exception.
                        }
                    }
                    if (function_exists('pcntl_signal')) {
                        pcntl_signal(SIGTERM, SIG_IGN);
                    }
                    file_put_contents($pidPath, (string) getmypid());
                    $haystack = range(1, 64);
                    while (true) {
                        in_array(63, $haystack, true);
                        usleep(1_000);
                    }
                },
                1,
                'unit-timeout',
                $root
            );
            $elapsed = microtime(true) - $started;
            if (pdf_corpus_watchdog_missing_capabilities() !== []) {
                $t->same(false, $result['ok'] ?? null);
                $t->same(false, $result['watchdog']['enforced'] ?? null);
                $t->true($elapsed < 1.0, 'Unsupported watchdog capability did not fail closed promptly.');
                return;
            }

            $t->same(false, $result['ok'] ?? null);
            $t->same(true, $result['timedOut'] ?? null);
            $t->same(true, $result['watchdog']['timedOut'] ?? null);
            $t->same(true, $result['watchdog']['termSent'] ?? null);
            if (function_exists('pcntl_signal')) {
                $t->same(true, $result['watchdog']['killSent'] ?? null);
            }
            $t->same(true, $result['watchdog']['reaped'] ?? null);
            $t->contains('unit-timeout exceeded the parent wall-clock timeout of 1 seconds', (string) ($result['error'] ?? ''));
            $t->true($elapsed < 4.0, 'Parent watchdog did not bound the worker wall-clock time.');
            $workerPid = (int) (file_get_contents($pidPath) ?: 0);
            $t->true($workerPid > 0, 'Timed worker did not publish its PID marker.');
            $workerStatus = 0;
            $t->same(-1, pcntl_waitpid($workerPid, $workerStatus, WNOHANG));
            $t->same([], glob($root . '/.pdf-corpus-worker-*') ?: []);
        } finally {
            $removeTree($root);
        }
    },

    'execution receipt binds exact artifact and output identities without timing noise' => static function (TestRunner $t) use ($removeTree): void {
        $root = sys_get_temp_dir() . '/port-libs-pdf-receipt-' . bin2hex(random_bytes(8));
        mkdir($root, 0777, true);
        try {
            $outputPath = $root . '/geometry-on.wordpress.html';
            file_put_contents($outputPath, '<!-- wp:paragraph --><p>Exact</p><!-- /wp:paragraph -->');
            $outputIdentity = immutable_pdf_corpus_file_identity($outputPath, 'outputs/candidate/geometry-on.wordpress.html');
            $artifact = [
                'pinStatus' => 'remote-hash-pinned',
                'bytes' => 77,
                'sha256' => str_repeat('a', 64),
            ];
            $mode = [
                'ok' => true,
                'seconds' => 1.25,
                'outputEvidence' => ['wordpress' => $outputIdentity],
                'tableCount' => 1,
                'listCount' => 0,
                'paragraphCount' => 1,
                'headingCount' => 0,
                'textBytes' => 5,
                'semanticVerification' => ['status' => 'pending_manual_review', 'passed' => null, 'issues' => []],
                'sourceIntegrity' => pdf_corpus_source_integrity_record([
                    'pdfDocumentComplete' => true,
                    'pdfSemanticTextComplete' => true,
                    'pdfSourceBindingComplete' => true,
                    'pdfSourceDisposition' => [
                        'sourceEdgeMappingComplete' => true,
                        'orderedSignificantCharactersPreserved' => true,
                        'unresolvedOccurrenceCount' => 0,
                    ],
                ]),
            ];
            $first = write_pdf_corpus_execution_report(
                'candidate',
                $artifact,
                ['geometry-on' => $mode],
                'remote_fetched_verified_executed',
                $root
            );
            $mode['seconds'] = 99.99;
            $second = write_pdf_corpus_execution_report(
                'candidate',
                $artifact,
                ['geometry-on' => $mode],
                'remote_fetched_verified_executed',
                $root
            );
            $t->same($first['bytes'] ?? null, $second['bytes'] ?? null);
            $t->same($first['sha256'] ?? null, $second['sha256'] ?? null);
            $receipt = json_decode(file_get_contents($root . '/execution-report.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
            $t->same(77, $receipt['artifact']['bytes'] ?? null);
            $t->same(str_repeat('a', 64), $receipt['artifact']['sha256'] ?? null);
            $t->same($outputIdentity, $receipt['modes']['geometry-on']['outputs']['wordpress'] ?? null);
            $t->same(true, $receipt['modes']['geometry-on']['sourceIntegrity']['complete'] ?? null);
            $t->same(false, array_key_exists('seconds', $receipt['modes']['geometry-on'] ?? []));
        } finally {
            $removeTree($root);
        }
    },

    'remote immutable pin without cached bytes is not reported as fetched or executed' => static function (TestRunner $t) use ($removeTree): void {
        $root = sys_get_temp_dir() . '/port-libs-pdf-pin-' . bin2hex(random_bytes(8));
        mkdir($root . '/cache', 0777, true);
        try {
            $artifact = resolve_pinned_artifact([
                'id' => 'remote-candidate',
                'artifact' => [
                    'pinStatus' => 'remote-hash-pinned',
                    'bytes' => 123,
                    'sha256' => str_repeat('a', 64),
                ],
            ], $root, $root . '/cache');
            $t->same(false, $artifact['ok'] ?? null);
            $t->same(true, $artifact['pinned'] ?? null);
            $t->same(false, $artifact['fetched'] ?? null);
            $t->same('remote_pinned_not_fetched', $artifact['availabilityState'] ?? null);
        } finally {
            $removeTree($root);
        }
    },

    'complete exact review evidence can record an explicit human pass' => static function (TestRunner $t) use ($makeReviewFixture): void {
        $fixture = $makeReviewFixture('pass');
        try {
            $review = evaluate_pdf_corpus_manual_review(
                $fixture['entry'],
                $fixture['artifact'],
                $fixture['executionReport'],
                $fixture['root'],
                'remote_fetched_verified_executed'
            );
            $t->same('reviewed_pass', $review['status'] ?? null);
            $t->same(true, $review['reviewed'] ?? null);
            $t->same(true, $review['passed'] ?? null);
            $t->same([], $review['issues'] ?? null);
        } finally {
            ($fixture['cleanup'])();
        }
    },

    'human pass cannot override incomplete automatic source integrity' => static function (TestRunner $t) use ($makeReviewFixture): void {
        $fixture = $makeReviewFixture('pass', false);
        try {
            $review = evaluate_pdf_corpus_manual_review(
                $fixture['entry'],
                $fixture['artifact'],
                $fixture['executionReport'],
                $fixture['root'],
                'remote_fetched_verified_executed'
            );
            $t->same('invalid_review_evidence', $review['status'] ?? null);
            $t->same(false, $review['reviewed'] ?? null);
            $t->same(null, $review['passed'] ?? null);
            $t->contains(
                'review-execution-report-geometry-on-source-integrity-incomplete',
                implode("\n", $review['issues'] ?? [])
            );
        } finally {
            ($fixture['cleanup'])();
        }
    },

    'human fail remains recordable when automatic source integrity is incomplete' => static function (TestRunner $t) use ($makeReviewFixture): void {
        $fixture = $makeReviewFixture('fail', false);
        try {
            $review = evaluate_pdf_corpus_manual_review(
                $fixture['entry'],
                $fixture['artifact'],
                $fixture['executionReport'],
                $fixture['root'],
                'remote_fetched_verified_executed'
            );
            $t->same('reviewed_fail', $review['status'] ?? null);
            $t->same(true, $review['reviewed'] ?? null);
            $t->same(false, $review['passed'] ?? null);
        } finally {
            ($fixture['cleanup'])();
        }
    },

    'explicit human fail is recorded as fail and never promoted to pass' => static function (TestRunner $t) use ($makeReviewFixture): void {
        $fixture = $makeReviewFixture('fail');
        try {
            $review = evaluate_pdf_corpus_manual_review(
                $fixture['entry'],
                $fixture['artifact'],
                $fixture['executionReport'],
                $fixture['root'],
                'remote_fetched_verified_executed'
            );
            $t->same('reviewed_fail', $review['status'] ?? null);
            $t->same(true, $review['reviewed'] ?? null);
            $t->same(false, $review['passed'] ?? null);
        } finally {
            ($fixture['cleanup'])();
        }
    },

    'stale execution report hash invalidates otherwise complete review evidence' => static function (TestRunner $t) use ($makeReviewFixture): void {
        $fixture = $makeReviewFixture('pass');
        try {
            $evidence = $fixture['evidence'];
            $evidence['executionReport']['sha256'] = str_repeat('0', 64);
            file_put_contents(
                $fixture['root'] . '/candidate.json',
                json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
            );
            $review = evaluate_pdf_corpus_manual_review(
                $fixture['entry'],
                $fixture['artifact'],
                $fixture['executionReport'],
                $fixture['root'],
                'remote_fetched_verified_executed'
            );
            $t->same('invalid_review_evidence', $review['status'] ?? null);
            $t->same(false, $review['reviewed'] ?? null);
            $t->same(null, $review['passed'] ?? null);
            $t->contains('review-execution-report-sha256-mismatch', implode("\n", $review['issues'] ?? []));
        } finally {
            ($fixture['cleanup'])();
        }
    },

    'missing mobile output screenshot invalidates a claimed pass' => static function (TestRunner $t) use ($makeReviewFixture): void {
        $fixture = $makeReviewFixture('pass');
        try {
            $evidence = $fixture['evidence'];
            unset($evidence['screenshots']['mobile']['output']);
            file_put_contents(
                $fixture['root'] . '/candidate.json',
                json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
            );
            $review = evaluate_pdf_corpus_manual_review(
                $fixture['entry'],
                $fixture['artifact'],
                $fixture['executionReport'],
                $fixture['root'],
                'remote_fetched_verified_executed'
            );
            $t->same('invalid_review_evidence', $review['status'] ?? null);
            $t->same(false, $review['reviewed'] ?? null);
            $t->contains('review-mobile-screenshot-pair-invalid', implode("\n", $review['issues'] ?? []));
        } finally {
            ($fixture['cleanup'])();
        }
    },

    'fetched execution without sidecar remains pending manual review' => static function (TestRunner $t) use ($makeReviewFixture): void {
        $fixture = $makeReviewFixture('pass');
        try {
            unlink($fixture['root'] . '/candidate.json');
            $review = evaluate_pdf_corpus_manual_review(
                $fixture['entry'],
                $fixture['artifact'],
                $fixture['executionReport'],
                $fixture['root'],
                'remote_fetched_verified_executed'
            );
            $t->same('pending_manual_review', $review['status'] ?? null);
            $t->same(false, $review['reviewed'] ?? null);
            $t->same(null, $review['passed'] ?? null);
        } finally {
            ($fixture['cleanup'])();
        }
    },

    'media representation download link is excluded but an ordinary PDF link remains gated' => static function (TestRunner $t): void {
        $ordinary = new AstNode('link', ['url' => 'https://example.test/source'], [
            new AstNode('text', ['text' => 'Source']),
        ]);
        $mediaFallback = new AstNode('link', [
            'url' => 'media/pdf/image.jp2',
            'attributes' => ['data-pandoc-pdf-image-original' => 'true'],
        ], [
            new AstNode('text', ['text' => 'Download original image.']),
        ]);
        $document = new AstNode('document', [
            'meta' => [
                'pdfPageCount' => 1,
                'pdfProcessedPageNumbers' => [1],
                'pdfMediaOccurrenceDispositions' => [],
                'pdfSourceDisposition' => ['unresolvedOccurrenceCount' => 0],
            ],
        ], [
            new AstNode('paragraph', [], [$ordinary, new AstNode('space'), $mediaFallback]),
        ]);

        $snapshot = semantic_snapshot($document);
        $t->same([['text' => 'Source', 'url' => 'https://example.test/source']], $snapshot['links'] ?? null);
        $t->same(1, $snapshot['exactCounts']['links'] ?? null);
        $orderedLinks = array_values(array_filter(
            $snapshot['order'] ?? [],
            static fn (array $record): bool => ($record['kind'] ?? null) === 'link'
        ));
        $t->same([['kind' => 'link', 'text' => 'Source']], $orderedLinks);
    },

    'generic heuristics cannot approve a pending remote candidate' => static function (TestRunner $t): void {
        $entry = [
            'expectedTables' => 0,
            'success' => ['maxTables' => 0, 'maxCodeBlocks' => 0],
            'semanticExpectations' => ['status' => 'pending_manual_review'],
        ];
        $document = new AstNode('document', [
            'meta' => [
                'pdfSemanticTextComplete' => true,
                'pdfDocumentComplete' => true,
            ],
        ], [
            new AstNode('paragraph', [], [new AstNode('text', ['text' => 'Exact readable text.'])]),
        ]);
        $status = review_status($entry, $document, "Exact readable text.\n", [
            'status' => 'pending_manual_review',
            'passed' => null,
        ]);
        $t->same(true, $status['heuristicCriteriaSatisfied'] ?? null);
        $t->same(false, $status['approvedByHeuristic'] ?? null);
        $t->same(true, $status['manualReviewRequired'] ?? null);
    },

    'summary keeps pin fetch execution and human review states separate' => static function (TestRunner $t): void {
        $summary = summarize_records([
            [
                'executionState' => 'remote_pinned_not_fetched',
                'artifact' => ['ok' => false, 'pinned' => true, 'fetched' => false, 'pinStatus' => 'remote-hash-pinned'],
                'semanticExpectations' => ['status' => 'pending_manual_review'],
                'manualReview' => ['status' => 'pending_execution'],
                'modes' => [],
            ],
            [
                'executionState' => 'remote_fetched_verified_executed',
                'artifact' => ['ok' => true, 'pinned' => true, 'fetched' => true, 'pinStatus' => 'remote-hash-pinned'],
                'semanticExpectations' => ['status' => 'pending_manual_review'],
                'manualReview' => ['status' => 'reviewed_pass'],
                'modes' => [
                    'geometry-on' => ['ok' => true, 'semanticVerification' => ['passed' => null], 'reviewStatus' => []],
                    'repair-only' => ['ok' => true, 'semanticVerification' => ['passed' => null], 'reviewStatus' => []],
                ],
            ],
        ]);
        $t->same(2, $summary['pinnedArtifacts'] ?? null);
        $t->same(1, $summary['remotePinnedNotFetched'] ?? null);
        $t->same(1, $summary['remoteFetchedVerified'] ?? null);
        $t->same(1, $summary['remoteFetchedVerifiedExecuted'] ?? null);
        $t->same(1, $summary['executedDocuments'] ?? null);
        $t->same(1, $summary['manualReviewPendingDocuments'] ?? null);
        $t->same(1, $summary['humanReviewedPassDocuments'] ?? null);
    },

    'current corpus keeps remote candidates pending and license blocks excluded' => static function (TestRunner $t): void {
        $manifestPath = dirname(__DIR__, 3) . '/tools/pdf-corpus-table-manifest.json';
        $manifest = json_decode(file_get_contents($manifestPath) ?: '', true, 512, JSON_THROW_ON_ERROR);
        $remote = array_values(array_filter(
            $manifest,
            static fn (array $entry): bool => ($entry['artifact']['pinStatus'] ?? null) === 'remote-hash-pinned'
        ));
        $blocked = array_values(array_filter(
            $manifest,
            static fn (array $entry): bool => ($entry['artifact']['pinStatus'] ?? null) === 'blocked-license-review'
        ));
        $checkedIn = array_values(array_filter(
            $manifest,
            static fn (array $entry): bool => ($entry['artifact']['pinStatus'] ?? null) === 'checked-in'
        ));
        $t->same(17, count($remote));
        $t->true(array_reduce($remote, static fn (bool $ok, array $entry): bool => $ok
            && ($entry['review']['status'] ?? null) === 'candidate'
            && ($entry['review']['visual'] ?? null) === 'required'
            && ($entry['semanticExpectations']['status'] ?? null) === 'pending_manual_review', true));
        $t->same(3, count($blocked));
        $t->true(array_reduce($blocked, static fn (bool $ok, array $entry): bool => $ok
            && ($entry['review']['status'] ?? null) === 'blocked'
            && ($entry['semanticExpectations']['status'] ?? null) === 'excluded_license_blocked', true));
        $t->same(4, count($checkedIn));
        $t->true(array_reduce($checkedIn, static fn (bool $ok, array $entry): bool => $ok
            && ($entry['review']['status'] ?? null) === 'baseline-recorded'
            && ($entry['semanticExpectations']['status'] ?? null) === 'verified_baseline', true));
    },

    'remote pin cannot claim a recorded review in the executable report policy' => static function (TestRunner $t): void {
        $manifestPath = dirname(__DIR__, 3) . '/tools/pdf-corpus-table-manifest.json';
        $manifest = json_decode(file_get_contents($manifestPath) ?: '', true, 512, JSON_THROW_ON_ERROR);
        $candidate = current(array_filter(
            $manifest,
            static fn (array $entry): bool => ($entry['artifact']['pinStatus'] ?? null) === 'remote-hash-pinned'
        ));
        if (!is_array($candidate)) {
            throw new RuntimeException('Remote review-policy fixture is unavailable.');
        }
        $candidate['review']['status'] = 'baseline-recorded';
        $candidate['review']['visual'] = 'screenshots-recorded';
        $t->throws(RuntimeException::class, static fn (): mixed => assert_report_manifest_semantic_states([$candidate]));
    },

    'checked-in review evidence schema requires both viewports and a human verdict' => static function (TestRunner $t): void {
        $schemaPath = dirname(__DIR__, 3) . '/tools/pdf-corpus-review-evidence.schema.json';
        $schema = json_decode(file_get_contents($schemaPath) ?: '', true, 512, JSON_THROW_ON_ERROR);
        $t->same(false, $schema['additionalProperties'] ?? null);
        $t->same(
            ['schemaVersion', 'corpusId', 'artifact', 'executionReport', 'screenshots', 'verdict'],
            $schema['required'] ?? null
        );
        $t->same(['desktop', 'mobile'], $schema['properties']['screenshots']['required'] ?? null);
        $t->same(['result', 'reviewer', 'reviewedAt', 'notes'], $schema['properties']['verdict']['required'] ?? null);
        $t->same(['pass', 'fail'], $schema['properties']['verdict']['properties']['result']['enum'] ?? null);
    },
];
