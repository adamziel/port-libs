<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$xrefClassicRebuildEarlyEndstreamFreeMapPdf = static function (): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous early-endstream free-map page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current early-endstream free-map page) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber] = $offset;
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );

    $previousCatalogOffset = $addObject(1, '<< /Type /Catalog /Pages 2 0 R >>');
    $previousPagesOffset = $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $previousPageOffset = $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $previousContentOffset = $addObject(4, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $previousFontOffset = $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleAnnotationOffset = $addObject(7, '<< /Type /Annot /Subtype /Link /Rect [72 700 280 718] /Contents (Stale early-endstream free annotation) /A << /S /URI /URI (https://stale.example.com/early-endstream-free-map) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($previousCatalogOffset)
        . $xrefRow($previousPagesOffset)
        . $xrefRow($previousPageOffset)
        . $xrefRow($previousContentOffset)
        . $xrefRow($previousFontOffset)
        . $xrefRow(0, 0, 'f')
        . $xrefRow($staleAnnotationOffset)
        . "trailer\n<< /Size 80 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 80 /Root 1 0 R /Prev {$previousXrefOffset} >>\n";

    $streamOwnerOffset = strlen($pdf);
    $fakePayload = "WordPress import note before an early marker\n"
        . "endstream\n"
        . "endobj\n"
        . "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($previousCatalogOffset)
        . $xrefRow($previousPagesOffset)
        . $xrefRow($previousPageOffset)
        . $xrefRow($previousContentOffset)
        . $xrefRow($previousFontOffset)
        . $xrefRow(0, 0, 'f')
        . $xrefRow($staleAnnotationOffset)
        . "trailer\n<< /Size 80 /Root 1 0 R >>\n"
        . "payload still belongs to object 60 after fake endstream\n";
    $pdf .= "60 0 obj\n"
        . "<< /Length " . strlen($fakePayload) . " >>\n"
        . "stream\n"
        . $fakePayload
        . "endstream\n"
        . "endobj\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $previousXrefOffset, $currentXrefOffset, $streamOwnerOffset];
};

return [
    'keeps declared-length stream early endstream decoys out of rebuilt free-object maps' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildEarlyEndstreamFreeMapPdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset, $streamOwnerOffset] = $xrefClassicRebuildEarlyEndstreamFreeMapPdf();

        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
        $linkedPages = (new PdfLinkAnnotationExtractor())->extractPageLinks($pdf);
        $text = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedReview = json_encode([$freeObjects, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true($streamOwnerOffset > $currentXrefOffset);
        $t->true(str_contains($pdf, "endstream\nendobj\nxref\n0 8\n"));
        $t->same(true, $freeObjects[7] ?? null, 'The current classic xref table keeps stale annotation object 7 free.');
        $t->same([], $linkedPages, 'A fake xref after an early stream marker must not resurrect a freed link annotation.');
        $t->same('Current early-endstream free-map page', $text);
        $t->true(!str_contains($encodedReview, 'stale.example.com/early-endstream-free-map'));
        $t->true(!str_contains($text, 'Previous early-endstream free-map page'));
        $t->true(!str_contains($text, "\0"));
    },
];
