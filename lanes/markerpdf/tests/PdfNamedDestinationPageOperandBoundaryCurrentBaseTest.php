<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationPageOperandBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current page destination body) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Index destination body) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk [1 /FitV 90] /LegacyBad [-1 /FitH 111] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Names [(Valid Page Ref) [3 0 R /FitH 700] (Valid Page Index) [1 /XYZ 72 640 0] (Null Page Operand) [null /Fit] (Negative Page Index) [-1 /FitH 111] (Out Of Range Page Index) [5 /FitV 222] (String Page Operand) [(not a page) /FitR 1 2 3 4] (Name Page Operand) [/PageAlias /FitBH 500] (Dictionary Page Operand) [<< /Type /Page >> /FitH 333]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects invalid page operands before WordPress named-destination review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationPageOperandBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationPageOperandBoundaryCurrentBasePdf()
        );

        $t->same(['Valid Page Ref', 'Valid Page Index', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, null, null], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 90.0], $destinations[2]['coordinates']);
    },
    'keeps invalid page operands out of visible WordPress text and review rows' => static function (
        TestRunner $t
    ) use ($namedDestinationPageOperandBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationPageOperandBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Current page destination body', $plainText);
        $t->contains('Index destination body', $plainText);
        foreach ([
            'Null Page Operand',
            'Negative Page Index',
            'Out Of Range Page Index',
            'String Page Operand',
            'Name Page Operand',
            'Dictionary Page Operand',
            'LegacyBad',
            '111',
            '222',
            '333',
            '500',
        ] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
