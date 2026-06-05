<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationIndirectPageKidsBoundaryCurrentBasePdf = static function (): string {
    $firstLogicalPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect Kids first logical page) Tj ET';
    $secondLogicalPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect Kids second logical page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacySecond [3 0 R /FitV 90] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids 12 0 R /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(First Logical) [4 0 R /FitH 700] (Second Logical) [3 0 R /XYZ 72 640 0] (Detached Decoy) [5 0 R /FitH 111]] >>\nendobj\n"
        . "12 0 obj\n[4 0 R 3 0 R]\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstLogicalPageContent) . " >>\nstream\n{$firstLogicalPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondLogicalPageContent) . " >>\nstream\n{$secondLogicalPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'uses indirect page-tree Kids arrays for named-destination page order before fallback scanning' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectPageKidsBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationIndirectPageKidsBoundaryCurrentBasePdf()
        );

        $t->same(['First Logical', 'Second Logical', 'LegacySecond'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([4, 3, 3], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 90.0], $destinations[2]['coordinates']);
    },
    'keeps detached page objects out of named-destination metadata and visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectPageKidsBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationIndirectPageKidsBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $encoded = json_encode([$destinations, $documentDestinations], JSON_UNESCAPED_SLASHES);

        $t->same(['First Logical', 'Second Logical', 'LegacySecond'], $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(2, $documentDestinations['page_count'] ?? null);
        $t->contains('Indirect Kids first logical page', $plainText);
        $t->contains('Indirect Kids second logical page', $plainText);
        foreach (['Detached Decoy', 'FitH 111'] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
