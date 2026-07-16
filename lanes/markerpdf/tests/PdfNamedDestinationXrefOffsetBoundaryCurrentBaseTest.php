<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationXrefOffsetBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current xref destination page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Current xref appendix page) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset,
        $generation,
        $state
    );

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyCurrent [4 0 R /FitV 96] >> >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Contents 6 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
    $addObject(5, 0, "<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream");
    $addObject(6, 0, "<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream");
    $addObject(7, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(8, 0, '<< /Names [(Current Xref Start) [3 0 R /FitH 700] (Current Xref Appendix) 9 0 R] >>');
    $addObject(9, 0, '<< /D [4 0 R /XYZ 72 640 0] >>');
    $currentDestinationTreeOffset = $offsets['8:0'];
    $currentDestinationDictionaryOffset = $offsets['9:0'];

    $addObject(8, 0, '<< /Names [(Stale Scanned Duplicate) [4 0 R /FitH 111] (Stale Dict Duplicate) 9 0 R] >>');
    $addObject(9, 0, '<< /D [3 0 R /FitV 22] >>');

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefRow(0, 65535, 'f')
        . $xrefRow($offsets['1:0'])
        . $xrefRow($offsets['2:0'])
        . $xrefRow($offsets['3:0'])
        . $xrefRow($offsets['4:0'])
        . $xrefRow($offsets['5:0'])
        . $xrefRow($offsets['6:0'])
        . $xrefRow($offsets['7:0'])
        . $xrefRow($currentDestinationTreeOffset)
        . $xrefRow($currentDestinationDictionaryOffset)
        . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses classic xref object offsets before later duplicate named-destination bodies' => static function (
        TestRunner $t
    ) use ($namedDestinationXrefOffsetBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationXrefOffsetBoundaryCurrentBasePdf()
        );

        $t->same(['Current Xref Start', 'Current Xref Appendix', 'LegacyCurrent'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 96.0], $destinations[2]['coordinates']);
    },
    'keeps later duplicate named-destination bodies out of WordPress review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationXrefOffsetBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationXrefOffsetBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Current xref destination page', $plainText);
        $t->contains('Current xref appendix page', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Scanned Duplicate'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale Dict Duplicate'));
        $t->true(is_string($encoded) && !str_contains($encoded, '111'));
        $t->true(is_string($encoded) && !str_contains($encoded, '22'));
        $t->true(!str_contains($plainText, 'Stale Scanned Duplicate'));
        $t->true(!str_contains($plainText, 'Stale Dict Duplicate'));
    },
];
