<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationIndirectPageIndexBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect destination intro page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Indirect destination appendix page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyIndirect [20 0 R /FitV 90] /LegacyInvalid [21 0 R /FitH 111] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Names [(Indirect Page Index) [20 0 R /XYZ 72 640 0] (Indirect Page Ref) [23 0 R /FitH 700] (Indirect Negative Index) [21 0 R /FitH 111] (Indirect String Page) [22 0 R /FitH 222] (Indirect Out Of Range) [24 0 R /FitV 333] (Indirect Null Page) [25 0 R /FitR 1 2 3 4]] >>\nendobj\n"
        . "20 0 obj\n1\nendobj\n"
        . "21 0 obj\n-1\nendobj\n"
        . "22 0 obj\n(Not a page)\nendobj\n"
        . "23 0 obj\n3 0 R\nendobj\n"
        . "24 0 obj\n9\nendobj\n"
        . "25 0 obj\nnull\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'resolves indirect page-number operands in explicit named destinations before WordPress review' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectPageIndexBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationIndirectPageIndexBoundaryCurrentBasePdf()
        );

        $t->same(['Indirect Page Index', 'Indirect Page Ref', 'LegacyIndirect'], array_column($destinations, 'name'));
        $t->same([1, 0, 1], array_column($destinations, 'page'));
        $t->same([null, 3, null], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'FitH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);
        $t->same(['top' => 700.0], $destinations[1]['coordinates']);
        $t->same(['left' => 90.0], $destinations[2]['coordinates']);
    },
    'keeps malformed indirect page operands out of visible WordPress text and review rows' => static function (
        TestRunner $t
    ) use ($namedDestinationIndirectPageIndexBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationIndirectPageIndexBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Indirect destination intro page', $plainText);
        $t->contains('Indirect destination appendix page', $plainText);
        foreach ([
            'Indirect Negative Index',
            'Indirect String Page',
            'Indirect Out Of Range',
            'Indirect Null Page',
            'LegacyInvalid',
            '111',
            '222',
            '333',
            'Not a page',
        ] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
