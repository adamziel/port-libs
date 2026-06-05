<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationTrailerRootBoundaryCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale named destination body) Tj ET';
    $currentOneContent = 'BT /F1 12 Tf 72 720 Td (Current named destination body) Tj ET';
    $currentTwoContent = 'BT /F1 12 Tf 72 720 Td (Current appendix destination body) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /StaleLegacy [3 0 R /FitV 88] >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(5, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(8, 0, '<< /Names [(Stale Start) [3 0 R /FitH 111] (Stale Dict) 9 0 R] >>');
    $addObject(9, 0, '<< /D [3 0 R /XYZ 9 99 0] >>');

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0'])
        . $xrefRow($offsets['2:0'])
        . $xrefRow($offsets['3:0'])
        . $xrefRow($offsets['4:0'])
        . $xrefRow($offsets['5:0'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['8:0'])
        . $xrefRow($offsets['9:0'])
        . "trailer\n<< /Size 30 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(10, 0, '<< /Type /Catalog /Pages 11 0 R /Names << /Dests 18 0 R >> /Dests << /LegacyCurrent [13 0 R /FitV 90] >> >>');
    $addObject(11, 0, '<< /Type /Pages /Kids [12 0 R 13 0 R] /Count 2 >>');
    $addObject(12, 0, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 15 0 R >> >> /Contents 16 0 R >>');
    $addObject(13, 0, '<< /Type /Page /Parent 11 0 R /Resources << /Font << /F1 15 0 R >> >> /Contents 17 0 R >>');
    $addObject(15, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(16, 0, "<< /Length " . strlen($currentOneContent) . " >>\nstream\n{$currentOneContent}\nendstream");
    $addObject(17, 0, "<< /Length " . strlen($currentTwoContent) . " >>\nstream\n{$currentTwoContent}\nendstream");
    $addObject(18, 0, '<< /Names [(Current Start) [12 0 R /FitH 700] (Current Appendix) 19 0 R] >>');
    $addObject(19, 0, '<< /D [13 0 R /XYZ 72 640 0] >>');

    $currentXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 1\n"
        . $xrefRow(0, 65535, 'f')
        . "10 10\n"
        . $xrefRow($offsets['10:0'])
        . $xrefRow($offsets['11:0'])
        . $xrefRow($offsets['12:0'])
        . $xrefRow($offsets['13:0'])
        . $xrefRow(0, 0, 'f')
        . $xrefRow($offsets['15:0'])
        . $xrefRow($offsets['16:0'])
        . $xrefRow($offsets['17:0'])
        . $xrefRow($offsets['18:0'])
        . $xrefRow($offsets['19:0'])
        . "% trailer << /Root 1 0 R /CommentOnly /Stale#52oot >>\n"
        . "trailer\n<< /Size 30 /Ro#6ft 10 0 R /Pre#76 {$previousXrefOffset} >>\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

$namedDestinationXrefStreamRootBoundaryCurrentBasePdf = static function (): string {
    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R >>');
    $addObject(8, 0, '<< /Names [(Stale Xref Stream Root) [3 0 R /FitH 111]] >>');
    $addObject(10, 0, '<< /Type /Catalog /Pages 11 0 R /Names << /Dests 18 0 R >> >>');
    $addObject(11, 0, '<< /Type /Pages /Kids [12 0 R] /Count 1 >>');
    $addObject(12, 0, '<< /Type /Page /Parent 11 0 R >>');
    $addObject(18, 0, '<< /Names [(Current Xref Stream Root) [12 0 R /FitH 715]] >>');

    $xrefStreamOffset = strlen($pdf);
    $pdf .= "90 0 obj\n"
        . "<< /Type /XRef /Size 91 /Root 10 0 R /W [1 4 1] /Length 0 >>\n"
        . "stream\n\nendstream\nendobj\n"
        . "startxref\n{$xrefStreamOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses current trailer Root catalog before stale named-destination catalog bodies' => static function (
        TestRunner $t
    ) use ($namedDestinationTrailerRootBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationTrailerRootBoundaryCurrentBasePdf()
        );

        $t->same(['Current Start', 'Current Appendix', 'LegacyCurrent'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([12, 13, 13], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 90.0], $destinations[2]['coordinates']);
    },
    'keeps stale body catalog destinations out of WordPress review and visible text' => static function (
        TestRunner $t
    ) use ($namedDestinationTrailerRootBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationTrailerRootBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Current named destination body', $plainText);
        $t->contains('Current appendix destination body', $plainText);
        $t->true(!str_contains($plainText, 'Stale named destination body'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Start'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Dict'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'StaleLegacy'));
        $t->true(is_string($encoded) && !str_contains($encoded, '111'));
        $t->true(is_string($encoded) && !str_contains($encoded, '99'));
    },
    'uses xref-stream Root catalog before stale named-destination catalog bodies' => static function (
        TestRunner $t
    ) use ($namedDestinationXrefStreamRootBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationXrefStreamRootBoundaryCurrentBasePdf()
        );
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

        $t->same(['Current Xref Stream Root'], array_column($destinations, 'name'));
        $t->same([0], array_column($destinations, 'page'));
        $t->same([12], array_column($destinations, 'page_object_id'));
        $t->same(['FitH'], array_column($destinations, 'fit'));
        $t->same(['top' => 715.0], $destinations[0]['coordinates']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Xref Stream Root'));
        $t->true(is_string($encoded) && !str_contains($encoded, '111'));
    },
];
