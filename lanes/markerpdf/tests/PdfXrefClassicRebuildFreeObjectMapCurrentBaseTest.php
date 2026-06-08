<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAnnotationExtractor;
use PortLibs\MarkerPDF\PdfLinkAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfXrefFreeObjectMap;

$xrefClassicRebuildFreeObjectMapCurrentBasePdf = static function (): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous damaged-startxref free-map page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current damaged-startxref free-map page) Tj ET';

    $pdf = "%PDF-1.7\n";
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 250 718] /Contents (Stale damaged-startxref free annotation) /A << /S /URI /URI (https://stale.example.com/damaged-startxref-free-map) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $previousXrefOffset, $currentXrefOffset];
};

$xrefClassicRebuildFreeObjectMapLiteralDecoyPdf = static function (): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous literal-decoy free-map page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current literal-decoy free-map page) Tj ET';

    $pdf = "%PDF-1.7\n";
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 260 718] /Contents (Stale literal-decoy free annotation) /A << /S /URI /URI (https://stale.example.com/literal-decoy-free-map) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n";

    $literalDecoyOffset = strlen($pdf);
    $pdf .= "(\n"
        . "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . ")\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $previousXrefOffset, $currentXrefOffset, $literalDecoyOffset];
};

$xrefClassicRebuildFreeObjectMapNameDelimitedDecoyPdf = static function (): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous name-delimited free-map page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current name-delimited free-map page) Tj ET';

    $pdf = "%PDF-1.7\n";
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 280 718] /Contents (Stale name-delimited free annotation) /A << /S /URI /URI (https://stale.example.com/name-delimited-free-map) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n";

    $nameDelimitedDecoyOffset = strlen($pdf);
    $pdf .= "xref/Decoy\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $previousXrefOffset, $currentXrefOffset, $nameDelimitedDecoyOffset];
};

$xrefClassicRebuildFreeObjectMapMissingFinalStartxrefPdf = static function (): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous missing-final-startxref free-map page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current missing-final-startxref free-map page) Tj ET';

    $pdf = "%PDF-1.7\n";
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 360 718] /Contents (Stale missing-final-startxref free annotation) /A << /S /URI /URI (https://stale.example.com/missing-final-startxref-free-map) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "%%EOF\n";
    $currentEofOffset = (int) strrpos($pdf, '%%EOF');

    $postEofXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n";

    return [$pdf, $previousXrefOffset, $currentXrefOffset, $currentEofOffset, $postEofXrefOffset];
};

$xrefClassicRebuildFreeObjectMapMalformedFinalStartxrefPdf = static function (): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous malformed-final-startxref free-map page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current malformed-final-startxref free-map page) Tj ET';

    $pdf = "%PDF-1.7\n";
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 385 718] /Contents (Stale malformed-final-startxref free annotation) /A << /S /URI /URI (https://stale.example.com/malformed-final-startxref-free-map) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\nnot-a-byte-offset\n%%EOF\n";
    $currentEofOffset = (int) strrpos($pdf, '%%EOF');

    $postEofXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "%%EOF";

    return [$pdf, $previousXrefOffset, $currentXrefOffset, $currentEofOffset, $postEofXrefOffset];
};

$xrefClassicRebuildFreeObjectMapCommentTrailerPdf = static function (): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous comment-trailer free-map page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current comment-trailer free-map page) Tj ET';

    $pdf = "%PDF-1.7\n";
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

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 365 718] /Contents (Stale comment-trailer free annotation) /A << /S /URI /URI (https://stale.example.com/comment-trailer-free-map) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "% trailer << /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "7 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $previousXrefOffset, $currentXrefOffset];
};

$xrefClassicRebuildFreeObjectMapPunctuationRowPdf = static function (): array {
    $previousContent = 'BT /F1 12 Tf 72 720 Td (Previous punctuation-row free-map page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current punctuation-row free-map page) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation . ':' . count($offsets)] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (
        int $offset,
        int $generation = 0,
        string $state = 'n',
        string $suffix = ' '
    ): string => sprintf("%010d %05d %s%s\n", $offset, $generation, $state, $suffix);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($previousContent) . " >>\nstream\n{$previousContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(7, 0, '<< /Type /Annot /Subtype /Link /Rect [72 700 390 718] /Contents (Stale punctuation-row free annotation) /A << /S /URI /URI (https://stale.example.com/punctuation-row-free-map) >> >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0:0'])
        . $xrefRow($offsets['2:0:1'])
        . $xrefRow($offsets['3:0:2'])
        . $xrefRow($offsets['4:0:3'])
        . $xrefRow($offsets['5:0:4'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['7:0:5'])
        . "trailer\n<< /Size 8 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $currentPageOffset = $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Annots [7 0 R] /Contents 4 0 R >>');
    $currentContentOffset = $addObject(4, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "3 2\n"
        . $xrefRow($currentPageOffset)
        . $xrefRow($currentContentOffset)
        . "7 1\n"
        . $xrefRow(0, 1, 'f')
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n999999\n%%EOF\n";

    $punctuationXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 8\n"
        . $xrefRow(0, 65535, 'f', '!')
        . $xrefRow($offsets['1:0:0'], 0, 'n', '!')
        . $xrefRow($offsets['2:0:1'], 0, 'n', '!')
        . $xrefRow($currentPageOffset, 0, 'n', '!')
        . $xrefRow($currentContentOffset, 0, 'n', '!')
        . $xrefRow($offsets['5:0:4'], 0, 'n', '!')
        . $xrefRow(0, 0, 'f', '!')
        . $xrefRow($offsets['7:0:5'], 0, 'n', '!')
        . "trailer\n<< /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>\n"
        . "startxref\n999999\n%%EOF";

    return [$pdf, $previousXrefOffset, $currentXrefOffset, $punctuationXrefOffset];
};

return [
    'rebuilds damaged classic startxref for the free-object map before annotation review' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildFreeObjectMapCurrentBasePdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset] = $xrefClassicRebuildFreeObjectMapCurrentBasePdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true(isset($freeObjects[7]), 'Damaged classic startxref rebuild must preserve current free rows.');
        $t->same(true, $freeObjects[7] ?? null);
        $t->same([], $linkExtractor->extractPageLinks($pdf), 'The stale freed annotation must not be promoted to a WordPress link.');
        $t->same([], $annotationExtractor->extractPageAnnotations($pdf), 'The stale freed annotation must not become review metadata.');

        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 250.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 250.0, 718.0],
                    'spans' => [[
                        'text' => 'Current damaged-startxref free-map page',
                        'bbox' => [72.0, 700.0, 250.0, 718.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $encodedReview = json_encode([$freeObjects, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true(!isset($span['link_uri']));
        $t->true(!isset($span['link_annotation_object']));
        $t->true(!str_contains($encodedReview, 'stale.example.com'));
        $t->true(!str_contains($encodedReview, 'Stale damaged-startxref free annotation'));
        $t->true(str_contains($pdf, "startxref\n999999"));
    },
    'ignores literal-string xref decoy while rebuilding the free-object map before annotation review' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildFreeObjectMapLiteralDecoyPdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset, $literalDecoyOffset] = $xrefClassicRebuildFreeObjectMapLiteralDecoyPdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true($literalDecoyOffset > $currentXrefOffset);
        $t->true(isset($freeObjects[7]), 'Literal-string xref decoys must not replace the current free-row map.');
        $t->same(true, $freeObjects[7] ?? null);
        $t->same([], $linkExtractor->extractPageLinks($pdf), 'The stale literal-decoy annotation URI must remain suppressed.');
        $t->same([], $annotationExtractor->extractPageAnnotations($pdf), 'The stale literal-decoy annotation review metadata must remain suppressed.');

        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 260.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 260.0, 718.0],
                    'spans' => [[
                        'text' => 'Current literal-decoy free-map page',
                        'bbox' => [72.0, 700.0, 260.0, 718.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $encodedReview = json_encode([$freeObjects, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true(!isset($span['link_uri']));
        $t->true(!isset($span['link_annotation_object']));
        $t->true(str_contains($pdf, "(\nxref\n"));
        $t->true(str_contains($pdf, "startxref\n999999"));
        $t->true(!str_contains($encodedReview, 'stale.example.com/literal-decoy-free-map'));
        $t->true(!str_contains($encodedReview, 'Stale literal-decoy free annotation'));
    },
    'ignores name-delimited xref pseudo-table while rebuilding the free-object map before annotation review' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildFreeObjectMapNameDelimitedDecoyPdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset, $nameDelimitedDecoyOffset] = $xrefClassicRebuildFreeObjectMapNameDelimitedDecoyPdf();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true($nameDelimitedDecoyOffset > $currentXrefOffset);
        $t->true(isset($freeObjects[7]), 'Name-delimited xref pseudo-tables must not replace the current free-row map.');
        $t->same(true, $freeObjects[7] ?? null);
        $t->same([], $linkExtractor->extractPageLinks($pdf), 'The stale name-delimited pseudo-table annotation URI must remain suppressed.');
        $t->same([], $annotationExtractor->extractPageAnnotations($pdf), 'The stale name-delimited pseudo-table annotation review metadata must remain suppressed.');

        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 280.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 280.0, 718.0],
                    'spans' => [[
                        'text' => 'Current name-delimited free-map page',
                        'bbox' => [72.0, 700.0, 280.0, 718.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $encodedReview = json_encode([$freeObjects, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true(!isset($span['link_uri']));
        $t->true(!isset($span['link_annotation_object']));
        $t->true(str_contains($pdf, "xref/Decoy\n"));
        $t->true(str_contains($pdf, "startxref\n999999"));
        $t->true(!str_contains($encodedReview, 'stale.example.com/name-delimited-free-map'));
        $t->true(!str_contains($encodedReview, 'Stale name-delimited free annotation'));
    },
    'uses EOF-bounded current classic xref for the free-object map when final startxref is missing' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildFreeObjectMapMissingFinalStartxrefPdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset, $currentEofOffset, $postEofXrefOffset] = $xrefClassicRebuildFreeObjectMapMissingFinalStartxrefPdf();
        $textExtractor = new PdfTextExtractor();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
        $links = $linkExtractor->extractPageLinks($pdf);
        $annotations = $annotationExtractor->extractPageAnnotations($pdf);

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true($currentEofOffset > $currentXrefOffset);
        $t->true($postEofXrefOffset > $currentEofOffset);
        $t->same(['Current missing-final-startxref free-map page'], $textExtractor->extractTextLines($pdf));
        $t->true(isset($freeObjects[7]), 'Missing final startxref repair must preserve current EOF-bounded free rows.');
        $t->same(true, $freeObjects[7] ?? null);
        $t->same([], $links, 'The stale missing-final-startxref annotation URI must remain suppressed.');
        $t->same([], $annotations, 'The stale missing-final-startxref annotation review metadata must remain suppressed.');

        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 360.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 360.0, 718.0],
                    'spans' => [[
                        'text' => 'Current missing-final-startxref free-map page',
                        'bbox' => [72.0, 700.0, 360.0, 718.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $encodedReview = json_encode([$freeObjects, $links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true(!isset($span['link_uri']));
        $t->true(!isset($span['link_annotation_object']));
        $t->true(str_contains($pdf, "startxref\n{$previousXrefOffset}"));
        $t->true(!str_contains(substr($pdf, $currentXrefOffset), "\nstartxref\n"));
        $t->true(!str_contains($encodedReview, 'stale.example.com/missing-final-startxref-free-map'));
        $t->true(!str_contains($encodedReview, 'Stale missing-final-startxref free annotation'));
    },
    'bounds malformed final startxref free-object rebuild before post-EOF annotation decoys' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildFreeObjectMapMalformedFinalStartxrefPdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset, $currentEofOffset, $postEofXrefOffset] = $xrefClassicRebuildFreeObjectMapMalformedFinalStartxrefPdf();
        $textExtractor = new PdfTextExtractor();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
        $links = $linkExtractor->extractPageLinks($pdf);
        $annotations = $annotationExtractor->extractPageAnnotations($pdf);

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true($currentEofOffset > $currentXrefOffset);
        $t->true($postEofXrefOffset > $currentEofOffset);
        $t->same(['Current malformed-final-startxref free-map page'], $textExtractor->extractTextLines($pdf));
        $t->true(isset($freeObjects[7]), 'Malformed final startxref repair must preserve current EOF-bounded free rows.');
        $t->same(true, $freeObjects[7] ?? null);
        $t->same([], $links, 'The stale malformed-final-startxref annotation URI must remain suppressed.');
        $t->same([], $annotations, 'The stale malformed-final-startxref annotation review metadata must remain suppressed.');

        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 385.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 385.0, 718.0],
                    'spans' => [[
                        'text' => 'Current malformed-final-startxref free-map page',
                        'bbox' => [72.0, 700.0, 385.0, 718.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $encodedReview = json_encode([$freeObjects, $links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true(!isset($span['link_uri']));
        $t->true(!isset($span['link_annotation_object']));
        $t->true(str_contains($pdf, "startxref\nnot-a-byte-offset\n%%EOF"));
        $t->true(!str_contains($encodedReview, 'stale.example.com/malformed-final-startxref-free-map'));
        $t->true(!str_contains($encodedReview, 'Stale malformed-final-startxref free annotation'));
    },
    'skips commented trailer tokens while rebuilding the free-object map before annotation review' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildFreeObjectMapCommentTrailerPdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset] = $xrefClassicRebuildFreeObjectMapCommentTrailerPdf();
        $textExtractor = new PdfTextExtractor();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
        $links = $linkExtractor->extractPageLinks($pdf);
        $annotations = $annotationExtractor->extractPageAnnotations($pdf);

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->same(['Current comment-trailer free-map page'], $textExtractor->extractTextLines($pdf));
        $t->true(isset($freeObjects[7]), 'Commented trailer tokens in a rebuilt xref table must not hide later free rows.');
        $t->same(true, $freeObjects[7] ?? null);
        $t->same([], $links, 'The stale comment-trailer annotation URI must remain suppressed.');
        $t->same([], $annotations, 'The stale comment-trailer annotation review metadata must remain suppressed.');

        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 365.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 365.0, 718.0],
                    'spans' => [[
                        'text' => 'Current comment-trailer free-map page',
                        'bbox' => [72.0, 700.0, 365.0, 718.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $encodedReview = json_encode([$freeObjects, $links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true(!isset($span['link_uri']));
        $t->true(!isset($span['link_annotation_object']));
        $t->true(str_contains($pdf, "% trailer << /Size 8 /Root 1 0 R /Prev {$previousXrefOffset} >>"));
        $t->true(!str_contains($encodedReview, 'stale.example.com/comment-trailer-free-map'));
        $t->true(!str_contains($encodedReview, 'Stale comment-trailer free annotation'));
    },
    'rejects punctuation-suffixed free-object xref rows before WordPress annotation review' => static function (
        TestRunner $t
    ) use ($xrefClassicRebuildFreeObjectMapPunctuationRowPdf): void {
        [$pdf, $previousXrefOffset, $currentXrefOffset, $punctuationXrefOffset] = $xrefClassicRebuildFreeObjectMapPunctuationRowPdf();
        $textExtractor = new PdfTextExtractor();
        $linkExtractor = new PdfLinkAnnotationExtractor();
        $annotationExtractor = new PdfAnnotationExtractor();
        $freeObjects = PdfXrefFreeObjectMap::freeObjectNumbers($pdf);
        $links = $linkExtractor->extractPageLinks($pdf);
        $annotations = $annotationExtractor->extractPageAnnotations($pdf);

        $t->true($previousXrefOffset > 0);
        $t->true($currentXrefOffset > $previousXrefOffset);
        $t->true($punctuationXrefOffset > $currentXrefOffset);
        $t->same(['Current punctuation-row free-map page'], $textExtractor->extractTextLines($pdf));
        $t->true(isset($freeObjects[7]), 'Malformed punctuation rows must not replace the current free-row map.');
        $t->same(true, $freeObjects[7] ?? null);
        $t->same([], $links, 'The stale punctuation-row annotation URI must remain suppressed.');
        $t->same([], $annotations, 'The stale punctuation-row annotation review metadata must remain suppressed.');

        $pages = [[
            'pnum' => 0,
            'blocks' => [[
                'type' => 'Text',
                'bbox' => [72.0, 700.0, 390.0, 718.0],
                'lines' => [[
                    'bbox' => [72.0, 700.0, 390.0, 718.0],
                    'spans' => [[
                        'text' => 'Current punctuation-row free-map page',
                        'bbox' => [72.0, 700.0, 390.0, 718.0],
                        'font' => 'Helvetica',
                    ]],
                ]],
            ]],
        ]];
        $linkedPages = $linkExtractor->applyLinksToPages($pages, $pdf);
        $span = $linkedPages[0]['blocks'][0]['lines'][0]['spans'][0];
        $encodedReview = json_encode([$freeObjects, $links, $annotations, $linkedPages], JSON_UNESCAPED_SLASHES) ?: '';

        $t->true(!isset($span['link_uri']));
        $t->true(!isset($span['link_annotation_object']));
        $t->true(str_contains($pdf, "000000"));
        $t->true(str_contains($pdf, " n!\n"));
        $t->true(!str_contains($encodedReview, 'stale.example.com/punctuation-row-free-map'));
        $t->true(!str_contains($encodedReview, 'Stale punctuation-row free annotation'));
    },
];
