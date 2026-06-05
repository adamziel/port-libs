<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;

$namedDestinationGenerationBodyCurrentBasePdf = static function (): string {
    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyCurrent 30 1 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 1 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "4 1 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "8 0 obj\n<< /Names [(Current Review) 20 1 R (Direct Current Page) [4 1 R /FitR 10 20 300 740] (Stale Page Generation) [4 0 R /FitH 120] (Bad Destination Generation) 21 1 R] >>\nendobj\n"
        . "20 1 obj\n<< /D [4 1 R /XYZ 72 640 0] >>\nendobj\n"
        . "20 0 obj\n<< /D [3 0 R /FitH 111] >>\nendobj\n"
        . "21 0 obj\n<< /D [3 0 R /FitV 80] >>\nendobj\n"
        . "30 1 obj\n<< /D [3 0 R /FitBH 610] >>\nendobj\n"
        . "30 0 obj\n<< /D [4 0 R /FitBV 90] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'uses exact-generation destination dictionaries before stale same-number bodies' => static function (
        TestRunner $t
    ) use ($namedDestinationGenerationBodyCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationGenerationBodyCurrentBasePdf()
        );

        $t->same(['Current Review', 'Direct Current Page', 'LegacyCurrent'], array_column($destinations, 'name'));
        $t->same([1, 1, 0], array_column($destinations, 'page'));
        $t->same([4, 4, 3], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'FitR', 'FitBH'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);
        $t->same(['left' => 10.0, 'bottom' => 20.0, 'right' => 300.0, 'top' => 740.0], $destinations[1]['coordinates']);
        $t->same(['top' => 610.0], $destinations[2]['coordinates']);
    },
    'filters stale page generations from WordPress named-destination review rows' => static function (
        TestRunner $t
    ) use ($namedDestinationGenerationBodyCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationGenerationBodyCurrentBasePdf()
        );
        $names = array_column($destinations, 'name');
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);

        $t->true(!in_array('Stale Page Generation', $names, true));
        $t->true(!in_array('Bad Destination Generation', $names, true));
        $t->true(is_string($encoded));
        $t->true(is_string($encoded) && !str_contains($encoded, '111'));
        $t->true(is_string($encoded) && !str_contains($encoded, '120'));
        $t->true(is_string($encoded) && !str_contains($encoded, '80'));
        $t->true(is_string($encoded) && !str_contains($encoded, '90'));
    },
];
