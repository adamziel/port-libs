<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationKidLimitsOrderBoundaryCurrentBasePdf = static function (): string {
    $alphaContent = 'BT /F1 12 Tf 72 720 Td (Alpha destination target page) Tj ET';
    $sameLowerContent = 'BT /F1 12 Tf 72 720 Td (Same lower destination page) Tj ET';
    $reviewContent = 'BT /F1 12 Tf 72 720 Td (Review and appendix destination page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyTail [5 0 R /FitV 144] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "5 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 32 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(Alpha Start) (Zulu Appendix)] /Kids [14 0 R 10 0 R 9 0 R 11 0 R 12 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Limits [(Alpha Start) (Deck Body)] /Names [(Alpha Start) [3 0 R /FitH 710] (Deck Body) 13 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(Review Summary) (Review Summary)] /Names [(Review Summary) [5 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "11 0 obj\n<< /Limits [(Same Lower Current) (Same Lower Wide)] /Names [(Same Lower Current) [4 0 R /FitBH 620]] >>\nendobj\n"
        . "12 0 obj\n<< /Limits [(Same Lower Current) (Same Lower Narrow)] /Names [(Same Lower Narrow) [5 0 R /FitH 555]] >>\nendobj\n"
        . "13 0 obj\n<< /D [4 0 R /XYZ 72 650 0] >>\nendobj\n"
        . "14 0 obj\n<< /Limits [(Zulu Appendix) (Zulu Appendix)] /Names [(Zulu Appendix) [5 0 R /Fit]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($alphaContent) . " >>\nstream\n{$alphaContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($sameLowerContent) . " >>\nstream\n{$sameLowerContent}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Length " . strlen($reviewContent) . " >>\nstream\n{$reviewContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'orders destination name-tree kids by Limits before WordPress destination review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationKidLimitsOrderBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationKidLimitsOrderBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $documentDestinations = $metadata['document_destinations'] ?? [];

        $t->same(
            ['Alpha Start', 'Deck Body', 'Review Summary', 'Same Lower Current', 'Same Lower Narrow', 'Zulu Appendix', 'LegacyTail'],
            array_column($destinations, 'name')
        );
        $t->same([0, 1, 2, 1, 2, 2, 2], array_column($destinations, 'page'));
        $t->same(['FitH', 'XYZ', 'XYZ', 'FitBH', 'FitH', 'Fit', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'names-tree', 'names-tree', 'names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 710.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 650.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[2]['coordinates']);
        $t->same(['top' => 620.0], $destinations[3]['coordinates']);
        $t->same(['top' => 555.0], $destinations[4]['coordinates']);
        $t->same([], $destinations[5]['coordinates']);
        $t->same(['left' => 144.0], $destinations[6]['coordinates']);

        $t->same(array_column($destinations, 'name'), $documentDestinations['names'] ?? null);
        $t->same(7, $documentDestinations['count'] ?? null);
        $t->same(['FitH', 'XYZ', 'XYZ', 'FitBH', 'FitH', 'Fit', 'FitV'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));
        $t->same(['top' => 710.0], $documentDestinations['destinations'][0]['view_parameters'] ?? null);
    },
    'keeps name-tree destination labels out of visible WordPress text after kid reordering' => static function (
        TestRunner $t
    ) use ($namedDestinationKidLimitsOrderBoundaryCurrentBasePdf): void {
        $plainText = (new PdfTextExtractor())->extractPlainText($namedDestinationKidLimitsOrderBoundaryCurrentBasePdf());

        $t->contains('Alpha destination target page', $plainText);
        $t->contains('Same lower destination page', $plainText);
        $t->contains('Review and appendix destination page', $plainText);
        foreach ([
            'Alpha Start',
            'Deck Body',
            'Review Summary',
            'Same Lower Current',
            'Same Lower Narrow',
            'Zulu Appendix',
            'LegacyTail',
        ] as $hidden) {
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
