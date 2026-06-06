<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationActionAliasCycleBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Legacy action alias source page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Action alias target destination page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyTarget [3 0 R /FitH 710] /LegacyActionCycle 17 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Names [(Actual Target) [4 0 R /XYZ 72 640 0] (Chained Action Alias) 10 0 R (Direct Action Alias) << /S /GoTo /D (Actual Target) >> (Names To Legacy Chain) 13 0 R (Self Action Cycle) 11 0 R (Mutual Action Cycle) 12 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /S /GoTo /D 14 0 R >>\nendobj\n"
        . "11 0 obj\n<< /S /GoTo /D 11 0 R /Title (hidden self action cycle) >>\nendobj\n"
        . "12 0 obj\n<< /S /GoTo /D 16 0 R >>\nendobj\n"
        . "13 0 obj\n<< /S /GoTo /D /LegacyTarget >>\nendobj\n"
        . "14 0 obj\n<< /S /GoTo /D (Actual Target) >>\nendobj\n"
        . "16 0 obj\n<< /S /GoTo /D 12 0 R /Title (hidden mutual action cycle) >>\nendobj\n"
        . "17 0 obj\n<< /S /GoTo /D 17 0 R /F (legacy-action-cycle.bin) >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'resolves chained GoTo aliases and rejects action-dictionary cycles before WordPress named-destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationActionAliasCycleBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationActionAliasCycleBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $expectedNames = [
            'Actual Target',
            'Chained Action Alias',
            'Direct Action Alias',
            'Names To Legacy Chain',
            'LegacyTarget',
        ];

        $t->same($expectedNames, array_column($destinations, 'name'));
        $t->same([1, 1, 1, 0, 0], array_column($destinations, 'page'));
        $t->same([4, 4, 4, 3, 3], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'XYZ', 'XYZ', 'FitH', 'FitH'], array_column($destinations, 'fit'));
        $t->same(
            ['names-tree', 'names-tree', 'names-tree', 'names-tree', 'legacy-dests'],
            array_column($destinations, 'source')
        );
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['top' => 710.0], $destinations[3]['coordinates']);
        $t->same($expectedNames, $metadata['document_destinations']['names'] ?? null);
        $t->same(5, $metadata['document_destinations']['count'] ?? null);
    },
    'keeps cyclic GoTo action alias operands out of visible WordPress text and review rows' => static function (
        TestRunner $t
    ) use ($namedDestinationActionAliasCycleBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationActionAliasCycleBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? []], JSON_UNESCAPED_SLASHES) ?: '';

        $t->contains('Legacy action alias source page', $plainText);
        $t->contains('Action alias target destination page', $plainText);
        foreach ([
            'Self Action Cycle',
            'Mutual Action Cycle',
            'LegacyActionCycle',
            'hidden self action cycle',
            'hidden mutual action cycle',
            'legacy-action-cycle.bin',
        ] as $hidden) {
            $t->same(false, str_contains($encodedReview, $hidden));
            $t->same(false, str_contains($plainText, $hidden));
        }

        foreach ([
            'Actual Target',
            'Chained Action Alias',
            'Direct Action Alias',
            'Names To Legacy Chain',
            'LegacyTarget',
        ] as $reviewOnly) {
            $t->same(false, str_contains($plainText, $reviewOnly));
        }
    },
];
