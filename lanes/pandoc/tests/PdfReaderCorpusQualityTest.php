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
];
