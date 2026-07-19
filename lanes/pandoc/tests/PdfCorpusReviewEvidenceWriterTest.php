<?php

declare(strict_types=1);

if (!defined('PDF_CORPUS_REVIEW_EVIDENCE_LIBRARY_ONLY')) {
    define('PDF_CORPUS_REVIEW_EVIDENCE_LIBRARY_ONLY', true);
}
require_once dirname(__DIR__, 3) . '/tools/pdf-corpus-review-evidence.php';

if (!defined('PDF_CORPUS_REPORT_LIBRARY_ONLY')) {
    define('PDF_CORPUS_REPORT_LIBRARY_ONLY', true);
}
require_once dirname(__DIR__, 3) . '/tools/pdf-corpus-report.php';

$removeReviewWriterTree = static function (string $root): void {
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

$makeReviewWriterFixture = static function (bool $sourceComplete = true) use ($removeReviewWriterTree): array {
    $root = sys_get_temp_dir() . '/port-libs-pdf-review-writer-' . bin2hex(random_bytes(8));
    mkdir($root, 0777, true);
    $artifactPath = $root . '/candidate.pdf';
    $artifactBytes = "%PDF-1.4\n% review evidence fixture\n";
    file_put_contents($artifactPath, $artifactBytes);
    $executionPath = $root . '/execution-report.json';
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
    $executionBytes = json_encode([
        'schemaVersion' => 1,
        'corpusId' => 'candidate',
        'artifact' => $artifactIdentity,
        'executionState' => 'remote_fetched_verified_executed',
        'modes' => [
            'geometry-on' => [
                'ok' => true,
                'outputs' => $modeOutputs('geometry-on'),
                'sourceIntegrity' => [
                    'complete' => $sourceComplete,
                    'pdfDocumentComplete' => $sourceComplete,
                    'pdfSemanticTextComplete' => $sourceComplete,
                    'pdfSourceBindingComplete' => $sourceComplete,
                    'pdfSourceEdgeMappingComplete' => $sourceComplete,
                    'pdfOrderedSignificantCharactersPreserved' => true,
                    'pdfUnresolvedSourceOccurrences' => $sourceComplete ? 0 : 1,
                ],
            ],
            'repair-only' => [
                'ok' => true,
                'outputs' => $modeOutputs('repair-only'),
                'sourceIntegrity' => [
                    'complete' => $sourceComplete,
                    'pdfDocumentComplete' => $sourceComplete,
                    'pdfSemanticTextComplete' => $sourceComplete,
                    'pdfSourceBindingComplete' => $sourceComplete,
                    'pdfSourceEdgeMappingComplete' => $sourceComplete,
                    'pdfOrderedSignificantCharactersPreserved' => true,
                    'pdfUnresolvedSourceOccurrences' => $sourceComplete ? 0 : 1,
                ],
            ],
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    file_put_contents($executionPath, $executionBytes);

    $png = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    if (!is_string($png)) {
        throw new RuntimeException('Unable to decode the PNG fixture.');
    }
    $screenshots = [];
    foreach (['desktop-source', 'desktop-output', 'mobile-source', 'mobile-output'] as $label) {
        $screenshots[$label] = $root . '/' . $label . '.png';
        file_put_contents($screenshots[$label], $png . $label);
    }

    $artifact = [
        'ok' => true,
        'pinned' => true,
        'fetched' => true,
        'verified' => true,
        'pinStatus' => 'remote-hash-pinned',
        'path' => $artifactPath,
        'bytes' => strlen($artifactBytes),
        'sha256' => hash('sha256', $artifactBytes),
    ];
    $executionReport = [
        'ok' => true,
        'schemaVersion' => 1,
        'path' => 'outputs/candidate/execution-report.json',
        'absolutePath' => $executionPath,
        'bytes' => strlen($executionBytes),
        'sha256' => hash('sha256', $executionBytes),
    ];
    $entry = [
        'id' => 'candidate',
        'semanticExpectations' => ['status' => 'pending_manual_review'],
    ];
    $record = $entry + [
        'artifact' => $artifact,
        'executionReport' => $executionReport,
        'executionState' => 'remote_fetched_verified_executed',
    ];
    $reportPath = $root . '/report.json';
    file_put_contents(
        $reportPath,
        json_encode(['records' => [$record]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n"
    );

    return [
        'root' => $root,
        'reportPath' => $reportPath,
        'outputDir' => $root . '/review-evidence',
        'screenshots' => $screenshots,
        'entry' => $entry,
        'artifact' => $artifact,
        'executionReport' => $executionReport,
        'record' => $record,
        'cleanup' => static fn (): mixed => $removeReviewWriterTree($root),
    ];
};

$reviewWriterOptions = static function (array $fixture): array {
    return [
        'report' => $fixture['reportPath'],
        'id' => 'candidate',
        'desktop-source' => $fixture['screenshots']['desktop-source'],
        'desktop-output' => $fixture['screenshots']['desktop-output'],
        'mobile-source' => $fixture['screenshots']['mobile-source'],
        'mobile-output' => $fixture['screenshots']['mobile-output'],
        'verdict' => 'pass',
        'reviewer' => 'Named Human Reviewer',
        'reviewed-at' => '2026-07-17T12:34:56Z',
        'notes' => 'Compared the exact source and output at both required viewports.',
        'output-dir' => $fixture['outputDir'],
    ];
};

return [
    'review writer requires both authoritative conversion modes' => static function (TestRunner $t) use ($makeReviewWriterFixture): void {
        $fixture = $makeReviewWriterFixture();
        try {
            $path = (string) ($fixture['executionReport']['absolutePath'] ?? '');
            $receipt = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $t->same([], pdf_corpus_review_execution_source_integrity_issues(
                $path,
                'candidate',
                $fixture['artifact'],
                'remote_fetched_verified_executed'
            ));
            $t->same(
                ['execution-report-corpus-id-mismatch'],
                pdf_corpus_review_execution_source_integrity_issues(
                    $path,
                    'different-candidate',
                    $fixture['artifact'],
                    'remote_fetched_verified_executed'
                )
            );
            file_put_contents($fixture['root'] . '/geometry-on.html', 'changed');
            $t->true(in_array(
                'geometry-on-output-file-mismatch',
                pdf_corpus_review_execution_source_integrity_issues(
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
                'geometry-on-outputs-invalid',
                pdf_corpus_review_execution_source_integrity_issues(
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
                ['execution-report-modes-invalid'],
                pdf_corpus_review_execution_source_integrity_issues(
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
    'renders source execution and output links relative to the corpus report directory' => static function (
        TestRunner $t
    ): void {
        $repositoryRoot = dirname(__DIR__, 3);
        $t->same(
            'outputs/candidate/geometry-on.wordpress.html',
            pdf_corpus_report_href(
                '.port-libs/pdf-corpus/outputs/candidate/geometry-on.wordpress.html',
                '.port-libs/pdf-corpus'
            )
        );
        $t->same(
            '../pdf-corpus-pinned/candidate%20source.pdf',
            pdf_corpus_report_href(
                $repositoryRoot . '/.port-libs/pdf-corpus-pinned/candidate source.pdf',
                '.port-libs/pdf-corpus'
            )
        );
        $t->same(null, pdf_corpus_report_href('/tmp/outside-repository.pdf', '.port-libs/pdf-corpus'));

        $mode = [
            'ok' => true,
            'tableCount' => 1,
            'listCount' => 0,
            'spacingReview' => ['heuristicScore' => 0],
            'reviewStatus' => ['issues' => []],
            'semanticVerification' => ['status' => 'pending_manual_review', 'issues' => []],
            'outputs' => [
                'wordpress' => '.port-libs/pdf-corpus/outputs/candidate/geometry-on.wordpress.html',
            ],
        ];
        $html = render_html_report([
            'generatedAt' => '2026-07-17T12:00:00Z',
            'workDir' => '.port-libs/pdf-corpus',
            'summary' => [],
            'records' => [[
                'id' => 'candidate',
                'kind' => 'table',
                'artifact' => [
                    'availabilityState' => 'remote_fetched_verified',
                    'path' => $repositoryRoot . '/.port-libs/pdf-corpus-pinned/candidate.pdf',
                ],
                'executionState' => 'remote_fetched_verified_executed',
                'executionReport' => [
                    'absolutePath' => '.port-libs/pdf-corpus/outputs/candidate/execution-report.json',
                ],
                'manualReview' => ['status' => 'pending_manual_review'],
                'modes' => ['geometry-on' => $mode, 'repair-only' => $mode],
            ]],
        ]);
        $t->contains('href="../pdf-corpus-pinned/candidate.pdf">Source PDF</a>', $html);
        $t->contains('href="outputs/candidate/execution-report.json">Execution report</a>', $html);
        $t->contains('href="outputs/candidate/geometry-on.wordpress.html">WordPress blocks</a>', $html);
        $t->true(!str_contains($html, 'href=".port-libs/pdf-corpus/outputs/'));
    },

    'writes a validator-compatible immutable review receipt with content-addressed PNGs' => static function (
        TestRunner $t
    ) use ($makeReviewWriterFixture, $reviewWriterOptions): void {
        $fixture = $makeReviewWriterFixture();
        try {
            $result = build_pdf_corpus_review_evidence($reviewWriterOptions($fixture));
            $t->true(is_file($result['path']));
            $t->same(hash_file('sha256', $result['path']), $result['sha256']);
            $t->same('candidate', $result['evidence']['corpusId'] ?? null);
            $t->same('Named Human Reviewer', $result['evidence']['verdict']['reviewer'] ?? null);

            $paths = [];
            foreach (['desktop', 'mobile'] as $viewport) {
                foreach (['source', 'output'] as $side) {
                    $identity = $result['evidence']['screenshots'][$viewport][$side] ?? [];
                    $paths[] = $identity['path'] ?? null;
                    $t->same(
                        1,
                        preg_match(
                            '#^assets/candidate/' . $viewport . '-' . $side . '-[a-f0-9]{16}\.png$#',
                            (string) ($identity['path'] ?? '')
                        )
                    );
                    $absolute = $fixture['outputDir'] . '/' . ($identity['path'] ?? '');
                    $t->same($identity['bytes'] ?? null, filesize($absolute));
                    $t->same($identity['sha256'] ?? null, hash_file('sha256', $absolute));
                }
            }
            $t->same(4, count(array_unique($paths)));

            $review = evaluate_pdf_corpus_manual_review(
                $fixture['entry'],
                $fixture['artifact'],
                $fixture['executionReport'],
                $fixture['outputDir'],
                'remote_fetched_verified_executed'
            );
            $t->same('reviewed_pass', $review['status'] ?? null);
            $t->same(true, $review['passed'] ?? null);

            $again = build_pdf_corpus_review_evidence($reviewWriterOptions($fixture));
            $t->same($result['sha256'], $again['sha256']);
            $t->same(null, $again['archivedPath']);
        } finally {
            ($fixture['cleanup'])();
        }
    },

    'review writer refuses a pass receipt for incomplete automatic source integrity' => static function (
        TestRunner $t
    ) use ($makeReviewWriterFixture, $reviewWriterOptions): void {
        $fixture = $makeReviewWriterFixture(false);
        try {
            $t->throws(
                RuntimeException::class,
                static fn (): array => build_pdf_corpus_review_evidence($reviewWriterOptions($fixture))
            );
        } finally {
            ($fixture['cleanup'])();
        }
    },

    'requires replace and archives a superseded human verdict' => static function (
        TestRunner $t
    ) use ($makeReviewWriterFixture, $reviewWriterOptions): void {
        $fixture = $makeReviewWriterFixture();
        try {
            $first = build_pdf_corpus_review_evidence($reviewWriterOptions($fixture));
            $changed = $reviewWriterOptions($fixture);
            $changed['verdict'] = 'fail';
            $changed['notes'] = 'The mobile output loses a required table relationship.';
            $changed['reviewed-at'] = '2026-07-17T12:35:56Z';
            $t->throws(
                RuntimeException::class,
                static fn (): array => build_pdf_corpus_review_evidence($changed)
            );

            $changed['replace'] = true;
            $second = build_pdf_corpus_review_evidence($changed);
            $t->true(is_string($second['archivedPath']) && is_file($second['archivedPath']));
            $t->same($first['sha256'], hash_file('sha256', (string) $second['archivedPath']));
            $t->true($first['sha256'] !== $second['sha256']);

            $review = evaluate_pdf_corpus_manual_review(
                $fixture['entry'],
                $fixture['artifact'],
                $fixture['executionReport'],
                $fixture['outputDir'],
                'remote_fetched_verified_executed'
            );
            $t->same('reviewed_fail', $review['status'] ?? null);
            $t->same(false, $review['passed'] ?? null);
        } finally {
            ($fixture['cleanup'])();
        }
    },

    'refuses unexecuted candidates and non-PNG captures' => static function (
        TestRunner $t
    ) use ($makeReviewWriterFixture, $reviewWriterOptions): void {
        $fixture = $makeReviewWriterFixture();
        try {
            $report = json_decode((string) file_get_contents($fixture['reportPath']), true, 512, JSON_THROW_ON_ERROR);
            $report['records'][0]['executionState'] = 'remote_fetched_verified_execution_failed';
            file_put_contents($fixture['reportPath'], json_encode($report, JSON_THROW_ON_ERROR));
            $t->throws(
                RuntimeException::class,
                static fn (): array => build_pdf_corpus_review_evidence($reviewWriterOptions($fixture))
            );

            $report['records'][0]['executionState'] = 'remote_fetched_verified_executed';
            file_put_contents($fixture['reportPath'], json_encode($report, JSON_THROW_ON_ERROR));
            file_put_contents($fixture['screenshots']['mobile-output'], 'not a png');
            $t->throws(
                RuntimeException::class,
                static fn (): array => build_pdf_corpus_review_evidence($reviewWriterOptions($fixture))
            );
        } finally {
            ($fixture['cleanup'])();
        }
    },
];
