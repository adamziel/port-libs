<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationAliasBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Legacy alias source page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Alias target destination page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyTarget [3 0 R /FitH 700] /LegacyAlias /LegacyTarget /LegacyCycleA /LegacyCycleB /LegacyCycleB /LegacyCycleA >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Names [(Actual Target) [4 0 R /XYZ 72 640 0] (String Alias) (Actual Target) (Name Alias) /Actual#20Target (Action Alias) << /S /GoTo /D (Actual Target) >> (Names To Legacy) /LegacyTarget (Missing Alias) (Not Present) (Cycle A) (Cycle B) (Cycle B) (Cycle A)] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'resolves named-destination aliases before WordPress review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationAliasBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationAliasBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $expectedNames = [
            'Actual Target',
            'String Alias',
            'Name Alias',
            'Action Alias',
            'Names To Legacy',
            'LegacyTarget',
            'LegacyAlias',
        ];

        $t->same($expectedNames, array_column($destinations, 'name'));
        $t->same([1, 1, 1, 1, 0, 0, 0], array_column($destinations, 'page'));
        $t->same([4, 4, 4, 4, 3, 3, 3], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'XYZ', 'XYZ', 'XYZ', 'FitH', 'FitH', 'FitH'], array_column($destinations, 'fit'));
        $t->same(
            ['names-tree', 'names-tree', 'names-tree', 'names-tree', 'names-tree', 'legacy-dests', 'legacy-dests'],
            array_column($destinations, 'source')
        );
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[2]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[3]['coordinates']);
        $t->same(['top' => 700.0], $destinations[4]['coordinates']);
        $t->same(['top' => 700.0], $destinations[5]['coordinates']);
        $t->same(['top' => 700.0], $destinations[6]['coordinates']);

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same($expectedNames, $documentDestinations['names'] ?? null);
        $t->same(7, $documentDestinations['count'] ?? null);
        $t->same([1, 1, 1, 1, 0, 0, 0], array_column($documentDestinations['destinations'] ?? [], 'page'));
    },
    'keeps missing and cyclic named-destination aliases out of WordPress text and review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationAliasBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationAliasBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES) ?: '';

        $t->contains('Legacy alias source page', $plainText);
        $t->contains('Alias target destination page', $plainText);
        foreach (['Missing Alias', 'Not Present', 'Cycle A', 'Cycle B', 'LegacyCycleA', 'LegacyCycleB'] as $hidden) {
            $t->same(false, str_contains($encoded, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }
        foreach (['Actual Target', 'String Alias', 'Name Alias', 'Action Alias', 'Names To Legacy', 'LegacyTarget', 'LegacyAlias'] as $reviewOnly) {
            $t->same(false, str_contains($plainText, $reviewOnly));
        }
    },
];
