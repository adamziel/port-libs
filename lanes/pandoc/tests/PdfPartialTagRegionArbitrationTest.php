<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PdfReader;

$streamObject = static function (int $objectNumber, string $content): string {
    return $objectNumber . " 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n"
        . $content . "\nendstream\nendobj\n";
};

$pageOne = 'BT /F1 22 Tf '
    . '1 0 0 1 72 760 Tm /Span << /MCID 0 >> BDC (Regional Evidence) Tj EMC '
    . '/F1 11 Tf '
    . '1 0 0 1 72 720 Tm (MARA: Left one.) Tj 1 0 0 1 340 720 Tm (ELI: Right one.) Tj '
    . '1 0 0 1 72 704 Tm (ELI: Left two.) Tj 1 0 0 1 340 704 Tm (MARA: Right two.) Tj '
    . '1 0 0 1 72 688 Tm (MARA: Left three.) Tj 1 0 0 1 340 688 Tm (ELI: Right three.) Tj '
    . '1 0 0 1 72 672 Tm (ELI: Left four.) Tj 1 0 0 1 340 672 Tm (MARA: Right four.) Tj '
    . 'ET';
$pageTwo = 'BT /F1 11 Tf '
    . '1 0 0 1 72 720 Tm (Product) Tj 1 0 0 1 250 720 Tm (Qty) Tj 1 0 0 1 340 720 Tm (Total) Tj '
    . '1 0 0 1 72 704 Tm (Widget) Tj 1 0 0 1 250 704 Tm (2) Tj 1 0 0 1 340 704 Tm ($6.00) Tj '
    . '1 0 0 1 72 688 Tm (Gadget) Tj 1 0 0 1 250 688 Tm (3) Tj 1 0 0 1 340 688 Tm ($9.00) Tj '
    . 'ET';
$pageThree = 'BT /F1 12 Tf '
    . '1 0 0 1 72 720 Tm (1. Inspect the source.) Tj '
    . '1 0 0 1 72 700 Tm (2. Preserve uncovered regions.) Tj '
    . '1 0 0 1 72 680 Tm (3. Publish the result.) Tj '
    . 'ET';
$pageFour = 'BT /F2 10 Tf '
    . '1 0 0 1 72 720 Tm (function total\(items\) \{) Tj '
    . '1 0 0 1 84 704 Tm (let sum = items.length >= 1;) Tj '
    . '1 0 0 1 84 688 Tm (const rate = sum === 0 ? 1 : 2;) Tj '
    . '1 0 0 1 84 672 Tm (for \(const item of items\) \{) Tj '
    . '1 0 0 1 96 656 Tm (const adjusted = item >= rate;) Tj '
    . '1 0 0 1 96 640 Tm (if \(adjusted !== false\) \{) Tj '
    . '1 0 0 1 108 624 Tm (sum++;) Tj '
    . '1 0 0 1 96 608 Tm (\} // end if) Tj '
    . '1 0 0 1 84 592 Tm (\} // end loop) Tj '
    . '1 0 0 1 84 576 Tm (return sum >= 0;) Tj '
    . '1 0 0 1 72 560 Tm (\} // end total) Tj '
    . 'ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 13 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R 6 0 R] /Count 4 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R /F2 8 0 R >> >> /Contents 9 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R /F2 8 0 R >> >> /Contents 10 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R /F2 8 0 R >> >> /Contents 11 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R /F2 8 0 R >> >> /Contents 12 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>\nendobj\n"
    . $streamObject(9, $pageOne)
    . $streamObject(10, $pageTwo)
    . $streamObject(11, $pageThree)
    . $streamObject(12, $pageFour)
    . "13 0 obj\n<< /Type /StructTreeRoot /ParentTree 14 0 R /K 15 0 R >>\nendobj\n"
    . "14 0 obj\n<< /Nums [0 [15 0 R]] >>\nendobj\n"
    . "15 0 obj\n<< /Type /StructElem /S /H1 /ActualText (Regional Evidence) /Pg 3 0 R /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$duplicateContent = 'BT /F1 12 Tf '
    . '1 0 0 1 72 720 Tm (Repeated Heading) Tj '
    . '1 0 0 1 72 700 Tm /Span << /MCID 0 >> BDC (Repeated Heading) Tj EMC '
    . '1 0 0 1 72 680 Tm (Coda.) Tj ET';
$duplicatePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 13 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 0 /MediaBox [0 0 612 792] /Resources << /Font << /F1 7 0 R >> >> /Contents 9 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . $streamObject(9, $duplicateContent)
    . "13 0 obj\n<< /Type /StructTreeRoot /ParentTree 14 0 R /K 15 0 R >>\nendobj\n"
    . "14 0 obj\n<< /Nums [0 [15 0 R]] >>\nendobj\n"
    . "15 0 obj\n<< /Type /StructElem /S /H1 /ActualText (Repeated Heading) /Pg 3 0 R /K << /Type /MCR /Pg 3 0 R /MCID 0 >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

return [
    'arbitrates one tagged heading without suppressing uncovered geometry list and code regions' => static function (TestRunner $t) use ($pdf): void {
        $document = (new PdfReader([
            'pdfGeometryTables' => true,
            'pdfRepairProseText' => true,
        ]))->read($pdf);
        $blocks = PandocConverter::write($document, 'blocks');
        $plain = PandocConverter::write($document, 'plain');
        $meta = $document->attr('meta');
        $types = array_map(static fn (AstNode $node): string => $node->type, $document->children);

        $t->same('heading', $document->children[0]->type);
        $t->same('Regional Evidence', $document->children[0]->attr('text'));
        $t->same(1, substr_count($plain, 'Regional Evidence'));
        $t->true(in_array('table', $types, true));
        $t->true(in_array('ordered_list', $types, true));
        $t->true(
            in_array('code_block', $types, true),
            'Expected uncovered code block; types=' . json_encode($types)
                . ' detected=' . json_encode($meta['pdfDetectedCodeBlocks'] ?? null)
                . ' textLines=' . json_encode($meta['pdfTextLines'] ?? null)
                . ' issues=' . json_encode($meta['pdfPageExtractionIssues'] ?? null)
                . ' unsupported=' . json_encode($meta['pdfUnsupportedFilters'] ?? null)
                . ' suppressed=' . json_encode($meta['pdfSuppressedGlyphRuns'] ?? null)
                . ' missingFonts=' . json_encode($meta['pdfMissingUnicodeFonts'] ?? null)
                . ' plain=' . $plain
        );
        $t->true(($meta['pdfIndependentColumnRegions'] ?? 0) > 0);
        $t->same(1, $meta['pdfGeometryTables'] ?? null);
        $t->same(1, $meta['pdfDetectedTables'] ?? null);
        $t->same(1, $meta['pdfDetectedCodeBlocks'] ?? null);
        $t->contains('<th>Product</th><th>Qty</th><th>Total</th>', $blocks);
        $t->contains('<li>Inspect the source.</li>', $blocks);
        $t->contains('function total(items) {', $plain);

        $leftLast = strpos($plain, 'Left four.');
        $rightFirst = strpos($plain, 'Right one.');
        $t->true($leftLast !== false && $rightFirst !== false && $leftLast < $rightFirst);

        $arbitration = $meta['pdfTaggedRegionArbitration'] ?? [];
        $t->same(1, count($arbitration));
        $t->same('tagged-structure', $arbitration[0]['selectedEvidence'] ?? null);
        $t->same('applied', $arbitration[0]['status'] ?? null);
        $t->same(true, $meta['pdfTaggedRegionArbitrationComplete'] ?? null);
        $t->same(0, $meta['pdfSourceDisposition']['unresolvedOccurrenceCount'] ?? null);
        $t->same(true, $meta['pdfSemanticTextComplete'] ?? null);
    },
    'keeps ambiguous duplicate tag occurrences on fallback with an explicit diagnostic' => static function (TestRunner $t) use ($duplicatePdf): void {
        $document = (new PdfReader([
            'pdfGeometryTables' => true,
            'pdfRepairProseText' => true,
        ]))->read($duplicatePdf);
        $plain = PandocConverter::write($document, 'plain');
        $blocks = PandocConverter::write($document, 'blocks');
        $meta = $document->attr('meta');
        $diagnostic = $meta['pdfTaggedRegionArbitration'][0] ?? [];

        $t->same(2, substr_count($plain, 'Repeated Heading'));
        $t->true(!str_contains($blocks, 'data-pdf-role="H1"'));
        $t->same('tagged-mapping-fallback', $meta['pdfTaggedArbitrationMode'] ?? null);
        $t->same(false, $meta['pdfTaggedRegionArbitrationComplete'] ?? null);
        $t->same('fallback-unresolved-tag-mapping', $diagnostic['status'] ?? null);
        $t->same('ambiguous-tagged-source-occurrence', $diagnostic['reason'] ?? null);
        $t->same(2, $diagnostic['candidateCount'] ?? null);
        $t->same(true, $meta['pdfSemanticTextComplete'] ?? null);
    },
];
