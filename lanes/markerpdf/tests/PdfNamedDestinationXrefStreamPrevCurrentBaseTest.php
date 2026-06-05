<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationXrefStreamPrevCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Xref stream previous destination page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Xref stream previous appendix page) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber . ':' . $generation] = strlen($pdf);
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Contents 6 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
    $addObject(5, 0, "<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream");
    $addObject(6, 0, "<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream");
    $addObject(7, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(8, 0, '<< /Names [(Prev Start) [3 0 R /FitH 700] (Prev Appendix) 9 0 R] >>');
    $addObject(9, 0, '<< /D [4 0 R /XYZ 72 640 0] >>');

    $previousRows = '';
    for ($objectNumber = 0; $objectNumber <= 9; $objectNumber++) {
        if ($objectNumber === 0) {
            $previousRows .= $xrefRow(0, 0, 255);
            continue;
        }

        $key = $objectNumber . ':0';
        $previousRows .= isset($offsets[$key])
            ? $xrefRow(1, $offsets[$key])
            : $xrefRow(0, 0);
    }

    $previousXref = gzcompress($previousRows);
    if (!is_string($previousXref)) {
        throw new RuntimeException('Unable to compress previous named-destination xref stream fixture.');
    }

    $previousXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 10 /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousXref) . " >>\n"
        . "stream\n{$previousXref}\nendstream\nendobj\n";

    $addObject(8, 0, '<< /Names [(Stale Fallback Start) [4 0 R /FitH 111] (Stale Fallback Dict) 9 0 R] >>');
    $addObject(9, 0, '<< /D [3 0 R /FitV 22] >>');
    $addObject(10, 0, '<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyPrev [4 0 R /FitV 96] >> >>');

    $currentRows = $xrefRow(0, 0, 255) . $xrefRow(1, $offsets['10:0']);
    $currentXref = gzcompress($currentRows);
    if (!is_string($currentXref)) {
        throw new RuntimeException('Unable to compress current named-destination xref stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Index [0 1 10 1] /Root 10 0 R /Prev ' . $previousXrefOffset
        . ' /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentXref) . " >>\n"
        . "stream\n{$currentXref}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'walks xref-stream Prev chains before named-destination fallback object scanning' => static function (
        TestRunner $t
    ) use ($namedDestinationXrefStreamPrevCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationXrefStreamPrevCurrentBasePdf()
        );

        $t->same(['Prev Start', 'Prev Appendix', 'LegacyPrev'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 96.0], $destinations[2]['coordinates']);
    },
    'keeps unselected stale xref-stream fallback destinations out of WordPress text and review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationXrefStreamPrevCurrentBasePdf): void {
        $pdf = $namedDestinationXrefStreamPrevCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

        $t->contains('Xref stream previous destination page', $plainText);
        $t->contains('Xref stream previous appendix page', $plainText);
        foreach (['Stale Fallback Start', 'Stale Fallback Dict', '111', '22'] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
        $t->true(!str_contains($plainText, 'Prev Start'));
        $t->true(!str_contains($plainText, 'Prev Appendix'));
        $t->true(!str_contains($plainText, 'LegacyPrev'));
    },
];
