<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationInternalNodeBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Internal node destination page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Internal child summary page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 120] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(A) (Zzz)] /Kids [9 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Limits [(Current Child Start) (Review Child Summary)] /Names [(Z Parent Decoy) [4 0 R /FitH 111]] /Kids [10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Names [(Current Child Start) [3 0 R /FitH 700] (Review Child Summary) 11 0 R (Z Child Decoy) [4 0 R /FitBH 222]] >>\nendobj\n"
        . "11 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps mixed internal name-tree nodes from widening child destination limits' => static function (
        TestRunner $t
    ) use ($namedDestinationInternalNodeBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationInternalNodeBoundaryCurrentBasePdf()
        );

        $t->same(['Current Child Start', 'Review Child Summary', 'LegacyOnly'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 120.0], $destinations[2]['coordinates']);

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata(
            $namedDestinationInternalNodeBoundaryCurrentBasePdf()
        );
        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Current Child Start', 'Review Child Summary', 'LegacyOnly'], $documentDestinations['names']);
        $t->same(['names_dests', 'legacy_dests'], $documentDestinations['source']);
        $t->same(3, $documentDestinations['count']);
        $t->same(2, $documentDestinations['page_count']);
    },
    'keeps stale mixed-node destination labels out of WordPress text and review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationInternalNodeBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationInternalNodeBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Internal node destination page', $plainText);
        $t->contains('Internal child summary page', $plainText);
        foreach (['Z Parent Decoy', 'Z Child Decoy', '111', '222'] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
