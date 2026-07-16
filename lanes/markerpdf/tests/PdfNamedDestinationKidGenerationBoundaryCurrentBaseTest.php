<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationKidGenerationBoundaryCurrentBasePdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyFallback [4 0 R /FitV 130] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Kids [9 0 R 10 0 R] /Limits [(Current Review) (Summary Review)] >>\nendobj\n"
        . "9 0 obj\n<< /Limits [(Current Review) (Current Review)] /Kids [9 1 R] >>\nendobj\n"
        . "9 1 obj\n<< /Limits [(Current Review) (Current Review)] /Names [(Current Review) [3 0 R /XYZ 72 700 1]] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(Summary Review) (Summary Review)] /Names [(Summary Review) 11 0 R] >>\nendobj\n"
        . "11 0 obj\n<< /D [4 0 R /FitBH 640] >>\nendobj\n"
        . "30 0 obj\n<< /Length 55 >>\nstream\nBT /F1 12 Tf 72 720 Td (Current destination page) Tj ET\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length 55 >>\nstream\nBT /F1 12 Tf 72 720 Td (Summary destination page) Tj ET\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'keeps nested same-object name-tree Kids distinct by generation before WordPress named destination review' => static function (
        TestRunner $t
    ) use ($namedDestinationKidGenerationBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationKidGenerationBoundaryCurrentBasePdf()
        );

        $t->same(['Current Review', 'Summary Review', 'LegacyFallback'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'FitBH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 72.0, 'top' => 700.0, 'zoom' => 1.0], $destinations[0]['coordinates']);
        $t->same(['top' => 640.0], $destinations[1]['coordinates']);
        $t->same(['left' => 130.0], $destinations[2]['coordinates']);
    },
    'keeps same-number name-tree generation labels out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationKidGenerationBoundaryCurrentBasePdf): void {
        $plainText = (new PdfTextExtractor())->extractPlainText($namedDestinationKidGenerationBoundaryCurrentBasePdf());

        $t->contains('Current destination page', $plainText);
        $t->contains('Summary destination page', $plainText);
        $t->true(!str_contains($plainText, 'Current Review'));
        $t->true(!str_contains($plainText, 'Summary Review'));
        $t->true(!str_contains($plainText, 'LegacyFallback'));
    },
];
