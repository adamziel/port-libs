<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationPartialKidLimitsOrderBoundaryCurrentBasePdf = static function (): string {
    $alphaContent = 'BT /F1 12 Tf 72 720 Td (Alpha partial-limits destination page) Tj ET';
    $reviewContent = 'BT /F1 12 Tf 72 720 Td (Review partial-limits destination page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyTail [4 0 R /FitV 144] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(Alpha Partial) (Review Partial)] /Kids [14 0 R 10 0 R 9 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Limits [(Alpha Partial) (Alpha Partial)] /Names [(Alpha Partial) [3 0 R /FitH 710]] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(Review Partial) (Review Partial)] /Names [(Review Partial) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "14 0 obj\n<< /Names [(Zulu Stale Partial) [4 0 R /Fit] (zz-partial-decoy) [3 0 R /Fit]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($alphaContent) . " >>\nstream\n{$alphaContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($reviewContent) . " >>\nstream\n{$reviewContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'orders bounded name-tree destination kids even when a malformed sibling has no local limits' => static function (
        TestRunner $t
    ) use ($namedDestinationPartialKidLimitsOrderBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationPartialKidLimitsOrderBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $documentDestinations = $metadata['document_destinations'] ?? [];

        $t->same(['Alpha Partial', 'Review Partial', 'LegacyTail'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 710.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 144.0], $destinations[2]['coordinates']);

        $t->same(array_column($destinations, 'name'), $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));

        $names = array_column($destinations, 'name');
        $t->true(!in_array('Zulu Stale Partial', $names, true));
        $t->true(!in_array('zz-partial-decoy', $names, true));
    },
    'keeps malformed partial-limit destination labels out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationPartialKidLimitsOrderBoundaryCurrentBasePdf): void {
        $plainText = (new PdfTextExtractor())->extractPlainText($namedDestinationPartialKidLimitsOrderBoundaryCurrentBasePdf());

        $t->contains('Alpha partial-limits destination page', $plainText);
        $t->contains('Review partial-limits destination page', $plainText);
        foreach ([
            'Alpha Partial',
            'Review Partial',
            'LegacyTail',
            'Zulu Stale Partial',
            'zz-partial-decoy',
        ] as $hidden) {
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
