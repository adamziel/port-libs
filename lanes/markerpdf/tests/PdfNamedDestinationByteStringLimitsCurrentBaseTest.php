<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationByteStringLimitsCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Byte range destination start page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Byte range destination appendix page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits [<18> <41>] /Names [<18> [3 0 R /FitH 700] <41> [4 0 R /XYZ 72 640 0] <80> [4 0 R /FitH 111]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'compares destination name-tree Limits by source bytes before decoded labels' => static function (
        TestRunner $t
    ) use ($namedDestinationByteStringLimitsCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationByteStringLimitsCurrentBasePdf()
        );

        $breve = "\u{02d8}";
        $bullet = "\u{2022}";
        $names = array_column($destinations, 'name');

        $t->same([$breve, 'A', 'LegacyOk'], $names);
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 120.0], $destinations[2]['coordinates']);
        $t->true(!in_array($bullet, $names, true));
    },
    'keeps decoded out-of-byte-range destination keys out of WordPress text and metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationByteStringLimitsCurrentBasePdf): void {
        $pdf = $namedDestinationByteStringLimitsCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $metadataEncoded = json_encode($metadata['document_destinations'] ?? [], JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Byte range destination start page', $plainText);
        $t->contains('Byte range destination appendix page', $plainText);
        $t->same(["\u{02d8}", 'A', 'LegacyOk'], $metadata['document_destinations']['names'] ?? []);
        $t->same(3, $metadata['document_destinations']['count'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, "\u{2022}"));
        $t->true(is_string($encoded) && !str_contains($encoded, '111'));
        $t->true(is_string($metadataEncoded) && !str_contains($metadataEncoded, "\u{2022}"));
        $t->true(is_string($metadataEncoded) && !str_contains($metadataEncoded, '111'));
        $t->true(!str_contains($plainText, "\u{2022}"));
        $t->true(!str_contains($plainText, 'LegacyOk'));
    },
];
