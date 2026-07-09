<?php

declare(strict_types=1);

use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$pdfPageWithNoText = static function (): string {
    return "%PDF-1.4\n"
        . "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        . "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        . "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Resources<<>>>>endobj\n"
        . "trailer<</Root 1 0 R>>\n%%EOF";
};

$plainText = static function (string $html): string {
    return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?? '';
};

$pdfSamplePaths = static function (): array {
    $root = dirname(__DIR__, 3);

    return [
        'archive-book' => $root . '/pandoc-showcase/samples/pdf-archive-motograph-book-motograph-moving-picture-book.pdf',
        'cdc-brochure' => $root . '/pandoc-showcase/samples/pdf-cdc-hand-hygiene-brochure-cdc-handhygiene-brochure.pdf',
        'grand-canyon-map' => $root . '/pandoc-showcase/samples/pdf-grand-canyon-north-rim-map-grand-canyon-north-rim-pocket-map.pdf',
        'irs-w4-form' => $root . '/pandoc-showcase/samples/pdf-irs-w4-irs-form-w4.pdf',
        'muir-brochure' => $root . '/pandoc-showcase/samples/pdf-muir-beach-brochure-muir-beach-brochure.pdf',
        'tracemonkey-paper' => $root . '/pandoc-showcase/samples/pdf-tracemonkey-tracemonkey.pdf',
    ];
};

$readPdfSample = static function (string $path, array $options = []): array {
    $document = (new PdfReader(array_replace([
        'maxTextBytes' => 80000,
        'pdfRepairProseText' => true,
        'pdfGeometryTables' => true,
    ], $options)))->read(file_get_contents($path) ?: '');
    $blocks = PandocConverter::write($document, 'blocks');

    return [
        'document' => $document,
        'meta' => $document->attr('meta'),
        'blocks' => $blocks,
        'paragraphs' => substr_count($blocks, '<!-- wp:paragraph'),
        'headings' => preg_match_all('/<!-- wp:heading\b/', $blocks),
        'lists' => substr_count($blocks, '<!-- wp:list'),
        'tables' => substr_count($blocks, '<!-- wp:table'),
    ];
};

return [
    'pdf corpus gate reads article and brochure samples without crashing' => static function (TestRunner $t): void {
        $root = dirname(__DIR__, 3);
        $cases = [
            'article' => $root . '/pandoc-showcase/samples/pdf-tracemonkey-tracemonkey.pdf',
            'brochure' => $root . '/pandoc-showcase/samples/pdf-cdc-hand-hygiene-brochure-cdc-handhygiene-brochure.pdf',
        ];

        foreach ($cases as $kind => $path) {
            $document = (new PdfReader([
                'maxTextBytes' => 30000,
                'pdfRepairProseText' => true,
                'pdfGeometryTables' => true,
            ]))->read(file_get_contents($path) ?: '');
            $meta = $document->attr('meta');

            $t->true(count($document->children) > 0, "{$kind} PDF should produce document blocks.");
            $t->true(($meta['pdfTextLines'] ?? 0) > 0, "{$kind} PDF should expose searchable text lines.");
            $t->true(array_key_exists('pdfTableReconstruction', $meta), "{$kind} PDF should report table reconstruction mode.");
        }
    },
    'pdf corpus gate preserves invoice and bank statement tables as tables' => static function (TestRunner $t) use ($pdfWithContent): void {
        $invoice = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (Item) Tj 1 0 0 1 220 720 Tm (Qty) Tj 1 0 0 1 320 720 Tm (Total) Tj '
            . '1 0 0 1 72 704 Tm (Consulting) Tj 1 0 0 1 220 704 Tm (2) Tj 1 0 0 1 320 704 Tm ($400.00) Tj '
            . '1 0 0 1 72 688 Tm (Hosting) Tj 1 0 0 1 220 688 Tm (1) Tj 1 0 0 1 320 688 Tm ($50.00) Tj '
            . 'ET'
        );
        $statement = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (Date) Tj 1 0 0 1 160 720 Tm (Description) Tj 1 0 0 1 350 720 Tm (Amount) Tj 1 0 0 1 460 720 Tm (Balance) Tj '
            . '1 0 0 1 72 704 Tm (2026-01-02) Tj 1 0 0 1 160 704 Tm (Deposit) Tj 1 0 0 1 350 704 Tm ($100.00) Tj 1 0 0 1 460 704 Tm ($500.00) Tj '
            . '1 0 0 1 72 688 Tm (2026-01-03) Tj 1 0 0 1 160 688 Tm (Withdrawal) Tj 1 0 0 1 350 688 Tm (-$20.00) Tj 1 0 0 1 460 688 Tm ($480.00) Tj '
            . 'ET'
        );

        foreach (['invoice' => $invoice, 'bank statement' => $statement] as $kind => $pdf) {
            $document = (new PdfReader(['pdfGeometryTables' => true, 'pdfRepairProseText' => true]))->read($pdf);
            $blocks = PandocConverter::write($document, 'blocks');
            $meta = $document->attr('meta');

            $t->same(1, $meta['pdfDetectedTables'], "{$kind} should expose one table.");
            $t->contains('<!-- wp:table -->', $blocks);
        }
    },
    'pdf corpus gate warns and preserves low confidence geometry tables as text' => static function (TestRunner $t) use ($pdfWithContent, $plainText): void {
        $pdf = $pdfWithContent(
            'BT /F1 12 Tf '
            . '1 0 0 1 72 720 Tm (Background) Tj 1 0 0 1 300 720 Tm (Outcome) Tj '
            . '1 0 0 1 72 704 Tm (The intervention was reviewed) Tj 1 0 0 1 300 704 Tm (The final report stayed readable) Tj '
            . 'ET'
        );

        $document = (new PdfReader(['pdfGeometryTables' => true, 'pdfRepairProseText' => true]))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $text = $plainText(PandocConverter::write($document, 'html'));
        $meta = $document->attr('meta');

        $t->same(0, $meta['pdfDetectedTables']);
        $t->true(($meta['pdfGeometryTableLowConfidenceCandidates'] ?? 0) > 0);
        $t->contains('PDF table-like geometry was preserved as text', implode("\n", $meta['pdfWarnings'] ?? []));
        $t->true(!str_contains($blocks, '<!-- wp:table -->'));
        $t->contains('Background', $text);
        $t->contains('final report stayed readable', $text);
    },
    'pdf corpus gate keeps resume and slide like PDFs readable without false tables' => static function (TestRunner $t) use ($pdfWithContent, $plainText): void {
        $resume = $pdfWithContent(
            'BT /F1 18 Tf 72 740 Td (Ada Example) Tj T* '
            . '/F1 12 Tf (Engineering Lead) Tj T* '
            . '(Experience) Tj T* '
            . '(• Led platform migration for publishing tools.) Tj T* '
            . '(• Improved importer reliability and observability.) Tj ET'
        );
        $slide = $pdfWithContent(
            'BT /F1 28 Tf 72 700 Td (Import Any Document) Tj T* '
            . '/F1 16 Tf (• Upload files) Tj T* '
            . '(• Review conversion notes) Tj T* '
            . '(• Edit the result in WordPress) Tj ET'
        );

        foreach (['resume' => $resume, 'slide-like' => $slide] as $kind => $pdf) {
            $document = (new PdfReader(['pdfGeometryTables' => true, 'pdfRepairProseText' => true]))->read($pdf);
            $blocks = PandocConverter::write($document, 'blocks');
            $text = $plainText(PandocConverter::write($document, 'html'));
            $meta = $document->attr('meta');

            $t->same(0, $meta['pdfDetectedTables'], "{$kind} should not become a table.");
            $t->true(!str_contains($blocks, '<!-- wp:table -->'), "{$kind} should not emit a table block.");
            $t->true(strlen($text) > 40, "{$kind} should preserve readable text.");
        }
    },
    'pdf corpus gate flags scanned image only PDFs as needing OCR' => static function (TestRunner $t) use ($pdfPageWithNoText): void {
        $document = (new PdfReader())->read($pdfPageWithNoText());
        $meta = $document->attr('meta');

        $t->same(0, $meta['pdfTextLines']);
        $t->true(($meta['pdfEstimatedPages'] ?? 0) > 0 || ($meta['pdfPageCount'] ?? 0) > 0);
    },
    'pdf corpus gate covers shipped real world samples' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample): void {
        foreach ($pdfSamplePaths() as $kind => $path) {
            $t->true(is_file($path), "{$kind} PDF fixture should exist.");
            $result = $readPdfSample($path);
            $meta = $result['meta'];

            $t->true(count($result['document']->children) > 0, "{$kind} should produce document blocks.");
            $t->true(($meta['pdfTextLines'] ?? 0) > 0, "{$kind} should expose searchable text lines.");
            $t->true(($meta['pdfTextBytes'] ?? 0) >= 300, "{$kind} should preserve a useful text payload.");
            $t->true(array_key_exists('pdfTableReconstruction', $meta), "{$kind} should report table reconstruction mode.");
            $t->true(array_key_exists('pdfDetectedTables', $meta), "{$kind} should report detected table count.");
        }
    },
    'pdf corpus gate preserves real layout tables without using text fragments' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample): void {
        $cases = [
            'grand-canyon-map' => ['minTables' => 1, 'minLines' => 250],
            'muir-brochure' => ['minTables' => 2, 'minLines' => 400],
            'tracemonkey-paper' => ['minTables' => 6, 'minLines' => 1000],
        ];

        foreach ($cases as $kind => $expectation) {
            $result = $readPdfSample($pdfSamplePaths()[$kind]);
            $meta = $result['meta'];

            $t->true(($meta['pdfGeometryTables'] ?? 0) >= $expectation['minTables'], "{$kind} should retain geometry table candidates.");
            $t->true(($meta['pdfDetectedTables'] ?? 0) >= $expectation['minTables'], "{$kind} should expose layout tables as document tables.");
            $t->true($result['tables'] >= $expectation['minTables'], "{$kind} WordPress blocks should include table blocks.");
            $t->true(($meta['pdfTextLines'] ?? 0) >= $expectation['minLines'], "{$kind} should keep enough positioned text to avoid collapsed extraction.");
            $t->same('geometry', $meta['pdfTableReconstruction'], "{$kind} should use layout-aware table reconstruction.");
        }
    },
    'pdf corpus gate keeps text only retry prose oriented' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample): void {
        $cases = [
            'grand-canyon-map' => ['minParagraphs' => 40, 'minHeadings' => 40],
            'muir-brochure' => ['minParagraphs' => 20, 'minHeadings' => 10],
            'tracemonkey-paper' => ['minParagraphs' => 100, 'minHeadings' => 40],
        ];

        foreach ($cases as $kind => $expectation) {
            $result = $readPdfSample($pdfSamplePaths()[$kind], ['pdfGeometryTables' => false]);
            $meta = $result['meta'];

            $t->same(0, $meta['pdfGeometryTables'], "{$kind} text-only retry should skip geometry table extraction.");
            $t->same(0, $meta['pdfDetectedTables'], "{$kind} text-only retry should not synthesize layout tables.");
            $t->same('text', $meta['pdfTableReconstruction'], "{$kind} text-only retry should report text reconstruction.");
            $t->same(0, $result['tables'], "{$kind} text-only retry WordPress blocks should not include table blocks.");
            $t->true($result['paragraphs'] >= $expectation['minParagraphs'], "{$kind} text-only retry should keep prose split into readable paragraphs.");
            $t->true($result['headings'] >= $expectation['minHeadings'], "{$kind} text-only retry should retain heading-like line structure.");
        }
    },
    'pdf corpus gate preserves brochure lists and form baseline extraction' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample): void {
        $cdc = $readPdfSample($pdfSamplePaths()['cdc-brochure']);
        $cdcMeta = $cdc['meta'];

        $t->same(0, $cdcMeta['pdfDetectedTables'], 'CDC brochure should not become a false table.');
        $t->true($cdc['lists'] >= 6, 'CDC brochure should preserve visible bullet lists.');
        $t->true($cdc['headings'] >= 6, 'CDC brochure should retain prominent heading-like text.');
        $t->true($cdc['paragraphs'] >= 20, 'CDC brochure should not collapse into a tiny number of paragraphs.');

        $w4 = $readPdfSample($pdfSamplePaths()['irs-w4-form']);
        $w4Meta = $w4['meta'];

        $t->true(($w4Meta['pdfEstimatedPages'] ?? 0) >= 5, 'IRS W-4 form should report its page count.');
        $t->true(($w4Meta['pdfTextLines'] ?? 0) >= 15, 'IRS W-4 form should retain extracted form text lines.');
        $t->true(($w4Meta['pdfTextBytes'] ?? 0) >= 300, 'IRS W-4 form should retain a baseline amount of text.');
        $t->true($w4['headings'] >= 2, 'IRS W-4 form should retain heading-like form labels.');
    },
    'pdf corpus gate keeps scanned book bounded but readable' => static function (TestRunner $t) use ($pdfSamplePaths, $readPdfSample): void {
        $result = $readPdfSample($pdfSamplePaths()['archive-book']);
        $meta = $result['meta'];

        $t->true(($meta['pdfEstimatedPages'] ?? 0) >= 40, 'Archive book should report the multi-page source.');
        $t->true(($meta['pdfTextLines'] ?? 0) >= 20, 'Archive book should keep available OCR/search text lines.');
        $t->true(($meta['pdfTextBytes'] ?? 0) >= 2000, 'Archive book should keep available OCR/search text.');
        $t->same(0, $meta['pdfDetectedTables'], 'Archive book prose should not become a table.');
        $t->true($result['paragraphs'] >= 8, 'Archive book should produce readable prose blocks.');
    },
];
