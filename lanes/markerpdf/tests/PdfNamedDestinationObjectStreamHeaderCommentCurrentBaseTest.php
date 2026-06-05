<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationObjectStreamHeaderCommentCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Comment header destination page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Comment header appendix page) Tj ET';

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(2, '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Contents 5 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
    $addObject(4, '<< /Type /Page /Parent 2 0 R /Contents 6 0 R /Resources << /Font << /F1 7 0 R >> >> >>');
    $addObject(5, "<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream");
    $addObject(6, "<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream");
    $addObject(7, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $compressedMembers = [
        1 => '<< /Type /Catalog /Pages 2 0 R /Names 8 0 R /Dests << /LegacyCommented [4 0 R /FitV 96] >> >>',
        8 => '<< /Dests 9 0 R >>',
        9 => '<< /Names [(Commented Start) [3 0 R /FitH 700] (Commented Appendix) 10 0 R] >>',
        10 => '<< /D [4 0 R /XYZ 72 640 0] >>',
    ];
    $objectStreamData = '';
    $objectStreamHeader = '';
    $memberIndexes = [];
    $memberIndex = 0;
    foreach ($compressedMembers as $objectNumber => $body) {
        $objectStreamHeader .= $objectNumber . ' ' . strlen($objectStreamData);
        if ($objectNumber === 1) {
            $objectStreamHeader .= " % 99 123 fake numeric header row\n";
        } else {
            $objectStreamHeader .= ' ';
        }
        $memberIndexes[$objectNumber] = $memberIndex++;
        $objectStreamData .= $body . "\n";
    }
    $objectStreamBytes = $objectStreamHeader . $objectStreamData;
    $compressedObjectStream = gzcompress($objectStreamBytes);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress named-destination commented object stream fixture.');
    }
    $addObject(20, '<< /Type /ObjStm /N ' . count($compressedMembers) . ' /First ' . strlen($objectStreamHeader) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyStale [3 0 R /FitH 111] >> >>');
    $addObject(8, '<< /Dests 9 0 R >>');
    $addObject(9, '<< /Names [(Stale Direct Start) [4 0 R /FitH 222] (Stale Direct Appendix) 10 0 R] >>');
    $addObject(10, '<< /D [3 0 R /FitV 33] >>');

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber < 22; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }
        if (isset($memberIndexes[$objectNumber])) {
            $rows .= $xrefRow(2, 20, $memberIndexes[$objectNumber]);
            continue;
        }
        if ($objectNumber === 21) {
            $rows .= $xrefRow(1, $xrefOffset);
            continue;
        }
        if (isset($offsets[$objectNumber])) {
            $rows .= $xrefRow(1, $offsets[$objectNumber]);
            continue;
        }

        $rows .= $xrefRow(0, 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress named-destination commented xref stream fixture.');
    }

    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps named-destination object-stream indexes aligned across commented header rows' => static function (
        TestRunner $t
    ) use ($namedDestinationObjectStreamHeaderCommentCurrentBasePdf): void {
        $pdf = $namedDestinationObjectStreamHeaderCommentCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

        $t->same(['Commented Start', 'Commented Appendix', 'LegacyCommented'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 96.0], $destinations[2]['coordinates']);
        $t->contains('Comment header destination page', $plainText);
        $t->contains('Comment header appendix page', $plainText);
        foreach (['Stale Direct Start', 'Stale Direct Appendix', 'LegacyStale', '222', '33'] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
        $t->true(!str_contains($plainText, 'Commented Start'));
        $t->true(!str_contains($plainText, 'LegacyCommented'));
        $t->true(!str_contains($plainText, "\0"));
    },
];
