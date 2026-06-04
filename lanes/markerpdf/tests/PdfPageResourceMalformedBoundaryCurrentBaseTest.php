<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageResourceMalformedBoundaryCMap = static function (array $entries): string {
    $body = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<00> <FF>\n"
        . "endcodespacerange\n"
        . count($entries) . " beginbfchar\n";

    foreach ($entries as $sourceHex => $text) {
        $encoded = iconv('UTF-8', 'UTF-16BE//IGNORE', (string) $text);
        if ($encoded === false) {
            throw new RuntimeException('Unable to encode focused resource CMap text.');
        }

        $body .= '<' . strtoupper((string) $sourceHex) . '> <' . strtoupper(bin2hex($encoded)) . ">\n";
    }

    return $body
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /MalformedPageResourceBoundaryCMap defineresource pop\n"
        . "end\n"
        . "end\n";
};

$pageResourceMalformedBoundaryPdf = static function () use ($pageResourceMalformedBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj T* '
        . '/P /ParentActual BDC <42> Tj EMC ET q /ParentForm Do Q';
    $parentForm = 'BT /F1 12 Tf 12 24 Td (Parent form resource leak) Tj ET';
    $cmap = $pageResourceMalformedBoundaryCMap([
        '41' => 'Parent font resource leak',
        '42' => 'Parent actual resource glyph',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> /XObject << /ParentForm 5 0 R >> /Properties << /ParentActual << /ActualText (Parent actual resource leak) >> >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /PieceInfo << /WPReview << /Private << /XObject << /ParentForm 5 0 R >> >> /ReviewOnly true >> >> /Resources 99 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 220 80] /Length " . strlen($parentForm) . " >>\nstream\n{$parentForm}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentResourceFont /Encoding /Identity-H /ToUnicode 7 0 R >>\nendobj\n"
        . "7 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "%%EOF";
};

$pageResourceMalformedArrayBoundaryPdf = static function () use ($pageResourceMalformedBoundaryCMap): string {
    $content = 'BT /F1 12 Tf 72 720 Td <41> Tj ET';
    $cmap = $pageResourceMalformedBoundaryCMap([
        '41' => 'Parent array resource leak',
    ]);

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources [99 0 R] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /ParentArrayResourceFont /Encoding /Identity-H /ToUnicode 6 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
        . "%%EOF";
};

return [
    'fails closed on unresolved page Resources references instead of inheriting parent resources' => static function (TestRunner $t) use ($pageResourceMalformedBoundaryPdf): void {
        $pdf = $pageResourceMalformedBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];

        $t->same(['A', 'B'], $extractor->extractTextLines($pdf));
        $t->same(['A', 'B'], $extractor->extractTextRuns($pdf));
        $t->same("A\nB", $plainText);
        $t->same("A\nB\n", $extractor->naiveGetText($pdf));
        $t->same('unresolved_or_malformed', $resourceMetadata['status'] ?? null);
        $t->same(false, $resourceMetadata['resolved'] ?? null);
        $t->same(3, $resourceMetadata['resource_owner_object'] ?? null);
        $t->same(99, $resourceMetadata['resource_object'] ?? null);
        $t->same(false, $resourceMetadata['inherited'] ?? null);
        $t->same([], $resourceMetadata['categories'] ?? null);
        $t->same(false, str_contains($plainText, 'Parent font resource leak'));
        $t->same(false, str_contains($plainText, 'Parent actual resource leak'));
        $t->same(false, str_contains($plainText, 'Parent form resource leak'));
    },
    'fails closed on malformed non-dictionary page Resources operands before parent font lookup' => static function (TestRunner $t) use ($pageResourceMalformedArrayBoundaryPdf): void {
        $pdf = $pageResourceMalformedArrayBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $plainText = $extractor->extractPlainText($pdf);
        $boundary = (new PdfPagePropertyExtractor())->extractPageBoundaryMetadata($pdf);
        $resourceMetadata = $boundary[0]['resources'] ?? [];

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same(['A'], $extractor->extractTextRuns($pdf));
        $t->same('A', $plainText);
        $t->same('unresolved_or_malformed', $resourceMetadata['status'] ?? null);
        $t->same(false, $resourceMetadata['resolved'] ?? null);
        $t->same(null, $resourceMetadata['resource_object'] ?? null);
        $t->same(false, $resourceMetadata['inherited'] ?? null);
        $t->same([], $resourceMetadata['categories'] ?? null);
        $t->same(false, str_contains($plainText, 'Parent array resource leak'));
    },
];
