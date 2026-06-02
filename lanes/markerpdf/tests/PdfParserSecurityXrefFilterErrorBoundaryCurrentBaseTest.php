<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserSecurityXrefFilterErrorBoundaryCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale xref filter fallback leak) Tj T* (Stale table selected text) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current malformed xref stream page) Tj ET';

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );

    $staleCatalog = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $stalePages = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $stalePage = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $staleFont = $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $staleStream = $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n0 6\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($staleCatalog)
        . $xrefRow($stalePages)
        . $xrefRow($stalePage)
        . $xrefRow($staleFont)
        . $xrefRow($staleStream)
        . "trailer\n<< /Size 6 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentCatalog = $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Version /Current >>');
    $currentPages = $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $currentPage = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $currentFont = $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $currentStream = $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefRows = ''
        . chr(1) . pack('N', $currentCatalog) . chr(0)
        . chr(1) . pack('N', $currentPages) . chr(0)
        . chr(1) . pack('N', $currentPage) . chr(0)
        . chr(1) . pack('N', $currentFont) . chr(0)
        . chr(1) . pack('N', $currentStream) . chr(0);
    $malformedXrefPayload = substr($xrefRows, 0, 7) . "not-deflate-xref-rows";

    $xrefStreamOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [1 5] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($malformedXrefPayload) . " >>\n"
        . "stream\n{$malformedXrefPayload}\nendstream\nendobj\n"
        . "startxref\n{$xrefStreamOffset}\n%%EOF";

    return $pdf;
};

return [
    'fails closed when current startxref xref-stream filter decoding errors before stale table fallback' => static function (TestRunner $t) use ($parserSecurityXrefFilterErrorBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserSecurityXrefFilterErrorBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $text);
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same([], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale xref filter fallback leak'));
        $t->true(!str_contains($text, 'Stale table selected text'));
        $t->true(!str_contains($text, 'Current malformed xref stream page'));
        $t->true(!str_contains($text, 'not-deflate-xref-rows'));
        $t->true(!str_contains($text, "\0"));
    },
];
