<?php

declare(strict_types=1);

use PortLibs\Pandoc\PdfReader;

return [
    'bounded default content token budget completes the dense Grand Canyon page stream' => static function (
        TestRunner $t
    ): void {
        $path = dirname(__DIR__, 3)
            . '/pandoc-showcase/samples/'
            . 'pdf-grand-canyon-north-rim-map-grand-canyon-north-rim-pocket-map.pdf';
        $pdf = file_get_contents($path);
        if (!is_string($pdf)) {
            throw new RuntimeException('The pinned Grand Canyon PDF sample is unavailable.');
        }

        $t->same(
            'e3092e0cbe1f62eb92463160f1dc39d5accb495d23904a4c94ca5a3a059d8b11',
            hash('sha256', $pdf),
            'The dense-stream regression must remain bound to the reviewed public source.'
        );

        $document = (new PdfReader([
            'maxTextBytes' => 100_000,
            'pdfGeometryTables' => true,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $meta = $document->attr('meta', []);
        $tokenLimitIssues = array_values(array_filter(
            is_array($meta['pdfPageExtractionIssues'] ?? null) ? $meta['pdfPageExtractionIssues'] : [],
            static fn (array $issue): bool => ($issue['reason'] ?? null) === 'content_stream_token_limit'
        ));

        $t->same([], $tokenLimitIssues);
        $t->same([], $meta['pdfPageExtractionIssues'] ?? null);
        $t->same(true, $meta['pdfTextComplete'] ?? null);
        $t->same(true, $meta['pdfRangeComplete'] ?? null);
        $t->same(true, $meta['pdfDocumentComplete'] ?? null);
        $t->same(2, $meta['pdfPageCount'] ?? null);
        $t->same([1, 2], $meta['pdfProcessedPageNumbers'] ?? null);
        $t->true(!in_array('page-extraction', $meta['pdfLimitReasons'] ?? [], true));
    },
];
