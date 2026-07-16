<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationPageOnlyBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Page only destination intro) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Page only destination appendix) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyPageRef 4 0 R /LegacyPageIndex 0 /LegacyLaunch << /S /Launch /F (legacy-hidden.bin) /D 3 0 R >> >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(Action Page) (Page Ref)] /Names [(Action Page) << /S /GoTo /D 3 0 R >> (Dictionary Page Ref) << /D 12 0 R >> (Page Index) 1 (Page Ref) 3 0 R (Invalid Page Ref) 41 0 R (Launch Page Ref) 42 0 R (Out Of Range Page Index) 5] >>\nendobj\n"
        . "12 0 obj\n4 0 R\nendobj\n"
        . "41 0 obj\n99 0 R\nendobj\n"
        . "42 0 obj\n<< /S /Launch /F (hidden-page-only-destination.exe) /D 4 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'normalizes page-only named destinations to Fit review rows before WordPress import' => static function (
        TestRunner $t
    ) use ($namedDestinationPageOnlyBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationPageOnlyBoundaryCurrentBasePdf()
        );

        $t->same(
            ['Action Page', 'Dictionary Page Ref', 'Page Index', 'Page Ref', 'LegacyPageRef', 'LegacyPageIndex'],
            array_column($destinations, 'name')
        );
        $t->same([0, 1, 1, 0, 1, 0], array_column($destinations, 'page'));
        $t->same([3, 4, null, 3, 4, null], array_column($destinations, 'page_object_id'));
        $t->same(['Fit', 'Fit', 'Fit', 'Fit', 'Fit', 'Fit'], array_column($destinations, 'fit'));
        $t->same(
            ['names-tree', 'names-tree', 'names-tree', 'names-tree', 'legacy-dests', 'legacy-dests'],
            array_column($destinations, 'source')
        );
        foreach ($destinations as $destination) {
            $t->same([], $destination['coordinates']);
        }
    },
    'keeps invalid page-only destinations out of visible WordPress text and review rows' => static function (
        TestRunner $t
    ) use ($namedDestinationPageOnlyBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationPageOnlyBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Page only destination intro', $plainText);
        $t->contains('Page only destination appendix', $plainText);
        foreach ([
            'Invalid Page Ref',
            'Launch Page Ref',
            'Out Of Range Page Index',
            'LegacyLaunch',
            'legacy-hidden.bin',
            'hidden-page-only-destination.exe',
        ] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
