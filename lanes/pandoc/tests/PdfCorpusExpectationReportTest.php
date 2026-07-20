<?php

declare(strict_types=1);

if (!defined('PDF_CORPUS_REPORT_LIBRARY_ONLY')) {
    define('PDF_CORPUS_REPORT_LIBRARY_ONLY', true);
}
require_once dirname(__DIR__, 3) . '/tools/pdf-corpus-report.php';

$expectations = [
    'schemaVersion' => 1,
    'status' => 'verified_baseline',
    'baseline' => ['source' => 'synthetic', 'asOf' => '2026-07-17'],
    'reason' => null,
    'expected' => [
        'headings' => [['text' => 'Exact title', 'level' => 1, 'occurrences' => 1]],
        'paragraphs' => [['text' => 'Exact introduction.', 'occurrences' => 1]],
        'listStarts' => [['text' => 'First exact item.', 'ordered' => true, 'start' => 4, 'occurrences' => 1]],
        'tableHeaders' => [['tableIndex' => 0, 'cells' => ['Item', 'Amount']]],
        'tableCells' => [['tableIndex' => 0, 'text' => '$12.00', 'occurrences' => 1]],
        'spans' => [['tableIndex' => 0, 'text' => 'Item', 'rowspan' => 1, 'colspan' => 1, 'occurrences' => 1]],
        'order' => [[
            'sequence' => [
                ['kind' => 'heading', 'text' => 'Exact title'],
                ['kind' => 'paragraph', 'text' => 'Exact introduction.'],
                ['kind' => 'table_cell', 'text' => 'Item'],
                ['kind' => 'table_cell', 'text' => '$12.00'],
            ],
        ]],
        'links' => [['text' => 'Source', 'url' => 'https://example.test/source', 'occurrences' => 1]],
        'pageCoverage' => ['pageCount' => 2, 'processedPages' => [1, 2]],
        'mediaOccurrences' => [[
            'id' => 'pdf-image-p2-n1-o9',
            'page' => 2,
            'object' => 9,
            'disposition' => 'resolved',
        ]],
        'unresolvedDispositions' => [],
    ],
    'forbidden' => [
        'headings' => [['text' => 'Wrong title', 'level' => 1]],
        'paragraphs' => [['text' => 'Gluedintroduction']],
        'listStarts' => [['text' => 'Wrong item.', 'ordered' => true]],
        'tableHeaders' => [['tableIndex' => 0, 'cells' => ['Amount', 'Item']]],
        'tableCells' => [['tableIndex' => 0, 'text' => '$1200']],
        'spans' => [['tableIndex' => 0, 'text' => 'Item', 'rowspan' => 2, 'colspan' => 1]],
        'order' => [['sequence' => [
            ['kind' => 'table_cell', 'text' => '$12.00'],
            ['kind' => 'heading', 'text' => 'Exact title'],
        ]]],
        'links' => [['text' => 'Source', 'url' => 'javascript:alert(1)']],
        'pageCoverage' => [3],
        'mediaOccurrences' => [[
            'id' => 'pdf-image-p2-n1-o9',
            'page' => 2,
            'object' => 9,
            'disposition' => 'unresolved',
        ]],
        'unresolvedDispositions' => [['domain' => 'source'], ['domain' => 'media']],
    ],
    'exactCounts' => [
        'headings' => 1,
        'paragraphs' => 1,
        'listStarts' => 1,
        'tables' => 1,
        'links' => 1,
        'mediaOccurrences' => 1,
        'unresolvedSourceDispositions' => 0,
        'unresolvedMediaDispositions' => 0,
    ],
];

$snapshot = [
    'normalization' => 'trim-and-collapse-unicode-whitespace',
    'headings' => [['text' => 'Exact title', 'level' => 1]],
    'paragraphs' => [['text' => 'Exact introduction.']],
    'listStarts' => [['text' => 'First exact item.', 'ordered' => true, 'start' => 4]],
    'tableHeaders' => [['tableIndex' => 0, 'cells' => ['Item', 'Amount']]],
    'tableCells' => [
        ['tableIndex' => 0, 'text' => 'Item', 'rowspan' => 1, 'colspan' => 1],
        ['tableIndex' => 0, 'text' => '$12.00', 'rowspan' => 1, 'colspan' => 1],
    ],
    'spans' => [
        ['tableIndex' => 0, 'text' => 'Item', 'rowspan' => 1, 'colspan' => 1],
        ['tableIndex' => 0, 'text' => '$12.00', 'rowspan' => 1, 'colspan' => 1],
    ],
    'order' => [
        ['kind' => 'heading', 'text' => 'Exact title'],
        ['kind' => 'paragraph', 'text' => 'Exact introduction.'],
        ['kind' => 'table_cell', 'text' => 'Item'],
        ['kind' => 'table_cell', 'text' => 'Amount'],
        ['kind' => 'table_cell', 'text' => '$12.00'],
        ['kind' => 'list_start', 'text' => 'First exact item.'],
        ['kind' => 'link', 'text' => 'Source'],
    ],
    'links' => [['text' => 'Source', 'url' => 'https://example.test/source']],
    'pageCoverage' => ['pageCount' => 2, 'processedPages' => [1, 2]],
    'mediaOccurrences' => [[
        'id' => 'pdf-image-p2-n1-o9',
        'page' => 2,
        'object' => 9,
        'paintOrder' => 1,
        'disposition' => 'resolved',
        'reason' => 'placed-media-attachment',
    ]],
    'unresolvedDispositions' => [],
    'exactCounts' => [
        'headings' => 1,
        'paragraphs' => 1,
        'listStarts' => 1,
        'tables' => 1,
        'links' => 1,
        'mediaOccurrences' => 1,
        'unresolvedSourceDispositions' => 0,
        'unresolvedMediaDispositions' => 0,
    ],
];

$issues = static fn (array $verification): string => implode("\n", $verification['issues'] ?? []);

return [
    'pdf corpus exact semantic expectation fixture passes' => static function (TestRunner $t) use ($expectations, $snapshot): void {
        $verification = evaluate_semantic_expectations($expectations, $snapshot);
        $t->same(true, $verification['passed'] ?? null);
        $t->same('verified_pass', $verification['status'] ?? null);
        $t->same([], $verification['issues'] ?? null);
    },

    'pdf corpus wrong heading fails the exact baseline' => static function (TestRunner $t) use ($expectations, $snapshot, $issues): void {
        $wrong = $snapshot;
        $wrong['headings'][0]['text'] = 'Almost exact title';
        $verification = evaluate_semantic_expectations($expectations, $wrong);
        $t->same(false, $verification['passed'] ?? null);
        $t->contains('expected-heading-missing-or-count-mismatch', $issues($verification));
    },

    'pdf corpus ordered-list start mismatch fails the exact baseline' => static function (TestRunner $t) use ($expectations, $snapshot, $issues): void {
        $wrong = $snapshot;
        $wrong['listStarts'][0]['start'] = 1;
        $verification = evaluate_semantic_expectations($expectations, $wrong);
        $t->same(false, $verification['passed'] ?? null);
        $t->contains('expected-list-start-missing-or-count-mismatch', $issues($verification));
    },

    'pdf corpus semantic snapshot exposes explicit ordered-list starts' => static function (TestRunner $t): void {
        $item = new \PortLibs\Pandoc\AstNode('list_item', [], [
            new \PortLibs\Pandoc\AstNode('paragraph', ['text' => 'Seventh item.'], [
                new \PortLibs\Pandoc\AstNode('text', ['text' => 'Seventh item.']),
            ]),
        ]);
        $document = new \PortLibs\Pandoc\AstNode('document', [], [
            new \PortLibs\Pandoc\AstNode('ordered_list', ['start' => 7], [$item]),
        ]);

        $snapshot = semantic_snapshot($document);
        $t->same([[
            'text' => 'Seventh item.',
            'ordered' => true,
            'start' => 7,
        ]], $snapshot['listStarts'] ?? null);
    },

    'pdf corpus wrong table cell and order both fail' => static function (TestRunner $t) use ($expectations, $snapshot, $issues): void {
        $wrong = $snapshot;
        $wrong['tableCells'][1]['text'] = '$21.00';
        $wrong['order'] = array_reverse($wrong['order']);
        $verification = evaluate_semantic_expectations($expectations, $wrong);
        $t->same(false, $verification['passed'] ?? null);
        $t->contains('expected-table-cell-missing-or-count-mismatch', $issues($verification));
        $t->contains('expected-order-missing', $issues($verification));
    },

    'pdf corpus missing processed page fails coverage' => static function (TestRunner $t) use ($expectations, $snapshot, $issues): void {
        $wrong = $snapshot;
        $wrong['pageCoverage']['processedPages'] = [1];
        $verification = evaluate_semantic_expectations($expectations, $wrong);
        $t->same(false, $verification['passed'] ?? null);
        $t->contains('expected-page-coverage-mismatch', $issues($verification));
    },

    'pdf corpus missing media occurrence fails exact occurrence accounting' => static function (TestRunner $t) use ($expectations, $snapshot, $issues): void {
        $wrong = $snapshot;
        $wrong['mediaOccurrences'] = [];
        $wrong['exactCounts']['mediaOccurrences'] = 0;
        $verification = evaluate_semantic_expectations($expectations, $wrong);
        $t->same(false, $verification['passed'] ?? null);
        $t->contains('expected-media-occurrence-missing-or-count-mismatch', $issues($verification));
        $t->contains('exact-count-mismatch:mediaOccurrences', $issues($verification));
    },

    'pdf corpus unresolved required source occurrence fails closed' => static function (TestRunner $t) use ($expectations, $snapshot, $issues): void {
        $wrong = $snapshot;
        $wrong['unresolvedDispositions'][] = [
            'domain' => 'source',
            'id' => 'pdf-source-p2-o7',
            'reason' => 'No emitted occurrence was available.',
        ];
        $wrong['exactCounts']['unresolvedSourceDispositions'] = 1;
        $verification = evaluate_semantic_expectations($expectations, $wrong);
        $t->same(false, $verification['passed'] ?? null);
        $t->contains('forbidden-unresolved-disposition-present', $issues($verification));
        $t->contains('exact-count-mismatch:unresolvedSourceDispositions', $issues($verification));
    },

    'pdf corpus manifest distinguishes verified pending and excluded documents' => static function (TestRunner $t): void {
        $path = dirname(__DIR__, 3) . '/tools/pdf-corpus-table-manifest.json';
        $manifest = json_decode(file_get_contents($path) ?: '', true, 512, JSON_THROW_ON_ERROR);
        $statuses = array_count_values(array_map(
            static fn (array $entry): string => (string) ($entry['semanticExpectations']['status'] ?? 'missing'),
            $manifest
        ));
        $t->same(24, count($manifest));
        $t->same(4, $statuses['verified_baseline'] ?? 0);
        $t->same(17, $statuses['pending_manual_review'] ?? 0);
        $t->same(3, $statuses['excluded_license_blocked'] ?? 0);
    },

    'pdf corpus report summary never counts license exclusions as executions' => static function (TestRunner $t): void {
        $summary = summarize_records([
            [
                'excluded' => true,
                'artifact' => ['ok' => false],
                'semanticExpectations' => ['status' => 'excluded_license_blocked'],
                'modes' => [],
            ],
            [
                'artifact' => ['ok' => true],
                'semanticExpectations' => ['status' => 'pending_manual_review'],
                'modes' => [
                    'geometry-on' => ['ok' => true, 'tableCount' => 1, 'semanticVerification' => ['passed' => null]],
                    'repair-only' => ['ok' => true, 'tableCount' => 0, 'semanticVerification' => ['passed' => null]],
                ],
            ],
        ]);
        $t->same(2, $summary['pdfCount'] ?? null);
        $t->same(1, $summary['excludedLicenseBlocked'] ?? null);
        $t->same(1, $summary['executedDocuments'] ?? null);
        $t->same(1, $summary['pendingManualReviewDocuments'] ?? null);
        $t->same(2, $summary['convertedModes'] ?? null);
    },

    'pdf corpus repair-only mode remains diagnostic not a contradictory table baseline' => static function (TestRunner $t) use ($expectations, $snapshot): void {
        $verification = evaluate_semantic_expectations($expectations, $snapshot, 'repair-only');
        $t->true(array_key_exists('passed', $verification));
        $t->same(null, $verification['passed']);
        $t->same('diagnostic_not_gated', $verification['status'] ?? null);
    },

    'pdf corpus source-integrity receipt is strict about every publication invariant' => static function (TestRunner $t): void {
        $complete = pdf_corpus_source_integrity_record([
            'pdfDocumentComplete' => true,
            'pdfSemanticTextComplete' => true,
            'pdfSourceBindingComplete' => true,
            'pdfSourceDisposition' => [
                'sourceEdgeMappingComplete' => true,
                'orderedSignificantCharactersPreserved' => true,
                'unresolvedOccurrenceCount' => 0,
            ],
        ]);
        $t->same(true, $complete['complete'] ?? null);

        $partialDocument = pdf_corpus_source_integrity_record([
            'pdfDocumentComplete' => false,
            'pdfSemanticTextComplete' => true,
            'pdfSourceBindingComplete' => true,
            'pdfSourceDisposition' => [
                'sourceEdgeMappingComplete' => true,
                'orderedSignificantCharactersPreserved' => true,
                'unresolvedOccurrenceCount' => 0,
            ],
        ]);
        $t->same(false, $partialDocument['complete'] ?? null);
        $t->same(false, $partialDocument['pdfDocumentComplete'] ?? null);

        $missingEdgeProof = pdf_corpus_source_integrity_record([
            'pdfDocumentComplete' => true,
            'pdfSemanticTextComplete' => true,
            'pdfSourceBindingComplete' => true,
            'pdfSourceDisposition' => [
                'orderedSignificantCharactersPreserved' => true,
                'unresolvedOccurrenceCount' => 0,
            ],
        ]);
        $t->same(false, $missingEdgeProof['complete'] ?? null);
        $t->same(false, $missingEdgeProof['pdfSourceEdgeMappingComplete'] ?? null);
    },

    'pdf corpus rechecks immutable artifact bytes immediately before conversion' => static function (TestRunner $t): void {
        $bytes = "%PDF-1.4\nverified\n";
        $identity = [
            'bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
        ];

        $t->same(true, pdf_corpus_bytes_match_artifact_identity($bytes, $identity));
        $t->same(false, pdf_corpus_bytes_match_artifact_identity($bytes . 'changed', $identity));
        $t->same(false, pdf_corpus_bytes_match_artifact_identity($bytes, [
            'bytes' => strlen($bytes),
            'sha256' => str_repeat('0', 64),
        ]));
    },

    'pdf corpus removes a stale optional native artifact after bounded omission' => static function (TestRunner $t): void {
        $path = sys_get_temp_dir() . '/port-libs-stale-native-' . bin2hex(random_bytes(8)) . '.native';
        file_put_contents($path, 'stale native output');
        try {
            pdf_corpus_remove_stale_optional_native($path);
            $t->same(false, is_file($path));
            pdf_corpus_remove_stale_optional_native($path);
            $t->same(false, is_file($path));
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },

    'pdf corpus optional native metadata preflight fails closed before materializing an oversized dump' => static function (TestRunner $t): void {
        $bounded = pdf_corpus_native_metadata_preflight([
            'title' => 'Bounded',
            'counts' => [1, 2, 3],
        ], 20, 1024);
        $t->same(true, $bounded['allowed'] ?? null);
        $t->same(null, $bounded['reason'] ?? null);

        $tooManyValues = pdf_corpus_native_metadata_preflight([
            'items' => range(1, 10),
        ], 5, 1024);
        $t->same(false, $tooManyValues['allowed'] ?? null);
        $t->same('metadata-value-limit-exceeded', $tooManyValues['reason'] ?? null);

        $tooManyBytes = pdf_corpus_native_metadata_preflight([
            'payload' => str_repeat('x', 64),
        ], 20, 16);
        $t->same(false, $tooManyBytes['allowed'] ?? null);
        $t->same('metadata-scalar-byte-limit-exceeded', $tooManyBytes['reason'] ?? null);
    },
];
