<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationIndirectArraysCurrentBasePdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyReview [4 0 R /FitV 120] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits 50 0 R /Kids 51 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Limits 52 0 R /Names 53 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Limits 54 0 R /Names 55 0 R >>\nendobj\n"
        . "11 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
        . "50 0 obj\n[(Alpha Review) (Summary Review)]\nendobj\n"
        . "51 0 obj\n[9 0 R 10 0 R]\nendobj\n"
        . "52 0 obj\n[(Alpha Review) (Current Start)]\nendobj\n"
        . "53 0 obj\n[(Alpha Review) [3 0 R /FitH 700] (Current Start) 11 0 R (zz stale alpha) [4 0 R /Fit]]\nendobj\n"
        . "54 0 obj\n[(Summary Review) (Summary Review)]\nendobj\n"
        . "55 0 obj\n[(A stale summary) [4 0 R /Fit] (Summary Review) [4 0 R /FitBH 600]]\nendobj\n"
        . "30 0 obj\n<< /Length 48 >>\nstream\nBT /F1 12 Tf 72 720 Td (Alpha destination page) Tj ET\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length 50 >>\nstream\nBT /F1 12 Tf 72 720 Td (Summary destination page) Tj ET\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'resolves indirect name-tree Kids Names and Limits arrays before WordPress named destination review' => static function (TestRunner $t) use ($namedDestinationIndirectArraysCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($namedDestinationIndirectArraysCurrentBasePdf());

        $t->same(['Alpha Review', 'Current Start', 'Summary Review', 'LegacyReview'], array_column($destinations, 'name'));
        $t->same([0, 1, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitBH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['top' => 600.0], $destinations[2]['coordinates']);
        $t->same(['left' => 120.0], $destinations[3]['coordinates']);

        $names = array_column($destinations, 'name');
        $t->true(!in_array('zz stale alpha', $names, true));
        $t->true(!in_array('A stale summary', $names, true));
    },
    'keeps indirect name-tree destination labels out of visible WordPress text' => static function (TestRunner $t) use ($namedDestinationIndirectArraysCurrentBasePdf): void {
        $plainText = (new PdfTextExtractor())->extractPlainText($namedDestinationIndirectArraysCurrentBasePdf());

        $t->contains('Alpha destination page', $plainText);
        $t->contains('Summary destination page', $plainText);
        $t->true(!str_contains($plainText, 'Alpha Review'));
        $t->true(!str_contains($plainText, 'Current Start'));
        $t->true(!str_contains($plainText, 'Summary Review'));
        $t->true(!str_contains($plainText, 'zz stale alpha'));
        $t->true(!str_contains($plainText, 'A stale summary'));
        $t->true(!str_contains($plainText, 'LegacyReview'));
    },
];
