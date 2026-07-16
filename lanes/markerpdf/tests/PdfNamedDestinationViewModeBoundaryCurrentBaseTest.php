<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationViewModeBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Current destination target page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Review destination target page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] /LegacyBad [4 0 R /Launch 88] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Names [(Current Fit) [3 0 R /FitH 700] (Current Unknown View) [3 0 R /Launch 77] (Indirect Unknown View) [4 0 R 9 0 R 88] (Action Unknown View) << /S /GoTo /D [4 0 R /Movie 99] >> (Valid Bounding Box Fit) [4 0 R /FitB 111 222]] >>\nendobj\n"
        . "9 0 obj\n/RichMedia\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects unknown destination view names before WordPress named-destination review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationViewModeBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationViewModeBoundaryCurrentBasePdf()
        );

        $t->same(['Current Fit', 'Valid Bounding Box Fit', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'FitB', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same([], $destinations[1]['coordinates']);
        $t->same(['left' => 120.0], $destinations[2]['coordinates']);
    },
    'keeps unknown destination view operands out of visible WordPress text and review rows' => static function (
        TestRunner $t
    ) use ($namedDestinationViewModeBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationViewModeBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Current destination target page', $plainText);
        $t->contains('Review destination target page', $plainText);
        foreach ([
            'Current Unknown View',
            'Indirect Unknown View',
            'Action Unknown View',
            'LegacyBad',
            'Launch',
            'RichMedia',
            'Movie',
            '77',
            '88',
            '99',
        ] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
