<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationIntermediateLimitsCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Intermediate limit start page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Intermediate limit summary page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 120] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(A) (Zzz)] /Kids [9 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Limits [(Zzz) (A)] /Kids [10 0 R 11 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Names [(Current Start) [3 0 R /FitH 700] (Review Summary) 13 0 R (zzzz stale) [4 0 R /Fit]] >>\nendobj\n"
        . "11 0 obj\n<< /Limits [(Summary) (Summaryzz)] /Names [(Summary Appendix) [4 0 R /FitBH 600] (0 stale) [3 0 R /Fit]] >>\nendobj\n"
        . "13 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'falls back to inherited name-tree limits through malformed intermediate Kids nodes' => static function (
        TestRunner $t
    ) use ($namedDestinationIntermediateLimitsCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationIntermediateLimitsCurrentBasePdf()
        );

        $t->same(
            ['Current Start', 'Review Summary', 'Summary Appendix', 'LegacyOnly'],
            array_column($destinations, 'name')
        );
        $t->same([0, 1, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitBH', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['top' => 600.0], $destinations[2]['coordinates']);
        $t->same(['left' => 120.0], $destinations[3]['coordinates']);
    },
    'keeps out-of-range intermediate name-tree labels out of WordPress text and review metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationIntermediateLimitsCurrentBasePdf): void {
        $pdf = $namedDestinationIntermediateLimitsCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('Intermediate limit start page', $plainText);
        $t->contains('Intermediate limit summary page', $plainText);
        foreach (['zzzz stale', '0 stale', 'Current Start', 'Review Summary', 'Summary Appendix'] as $hidden) {
            $t->true(!str_contains($plainText, $hidden));
        }
        foreach (['zzzz stale', '0 stale'] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
        }
    },
];
