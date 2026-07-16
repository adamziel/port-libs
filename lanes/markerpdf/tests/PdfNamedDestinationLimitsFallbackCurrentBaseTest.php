<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationLimitsFallbackCurrentBasePdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOnly [4 0 R /FitV 120] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(Current Start) (Review Summary)] /Kids [9 0 R 10 0 R] >>\nendobj\n"
        . "9 0 obj\n<< /Limits [(zz-stale) (zz-stale)] /Names [(Current Start) [3 0 R /FitH 700] (Review Summary) 11 0 R (zz-stale) [4 0 R /Fit]] >>\nendobj\n"
        . "10 0 obj\n<< /Limits [(zzz-decoy) (zzz-decoy)] /Names [(A Stale Deck) [4 0 R /Fit] (ZZZ Appendix) [4 0 R /Fit]] >>\nendobj\n"
        . "11 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
        . "30 0 obj\n<< /Length 59 >>\nstream\nBT /F1 12 Tf 72 720 Td (Current named destination page) Tj ET\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length 58 >>\nstream\nBT /F1 12 Tf 72 720 Td (Review summary destination page) Tj ET\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'falls back to inherited name-tree limits when a malformed destination leaf matches none of its keys' => static function (TestRunner $t) use ($namedDestinationLimitsFallbackCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($namedDestinationLimitsFallbackCurrentBasePdf());

        $t->same(['Current Start', 'Review Summary', 'LegacyOnly'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 120.0], $destinations[2]['coordinates']);

        $names = array_column($destinations, 'name');
        $t->true(!in_array('zz-stale', $names, true));
        $t->true(!in_array('A Stale Deck', $names, true));
        $t->true(!in_array('ZZZ Appendix', $names, true));
    },
    'keeps malformed name-tree limit operands out of visible WordPress text' => static function (TestRunner $t) use ($namedDestinationLimitsFallbackCurrentBasePdf): void {
        $plainText = (new PdfTextExtractor())->extractPlainText($namedDestinationLimitsFallbackCurrentBasePdf());

        $t->contains('Current named destination page', $plainText);
        $t->contains('Review summary destination page', $plainText);
        $t->true(!str_contains($plainText, 'zz-stale'));
        $t->true(!str_contains($plainText, 'A Stale Deck'));
        $t->true(!str_contains($plainText, 'ZZZ Appendix'));
    },
];
