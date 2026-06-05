<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationActionDictionaryBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (GoTo destination page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Plain dictionary destination page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk << /D [4 0 R /FitV 130] >> /LegacyLaunch << /S /Launch /F (legacy-payload.sh) /D [3 0 R /FitH 88] >> >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Names [(GoTo Action Dest) << /S /GoTo /D [3 0 R /FitH 700] >> (Plain Dest Dict) << /D [4 0 R /XYZ 72 640 0] >> (URI Masquerade) << /S /URI /URI (https://example.com/hidden-destination-action) /D [4 0 R /FitH 111] >> (Launch Masquerade) 11 0 R (JavaScript Masquerade) << /S /JavaScript /JS (app.alert\\(hidden named destination\\)) /D [3 0 R /FitV 222] >> (Malformed Action Type) << /S (GoTo) /D [4 0 R /FitBH 333] >>] >>\nendobj\n"
        . "11 0 obj\n<< /S /Launch /F (launch-payload.exe) /D [4 0 R /FitR 1 2 3 4] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects non-GoTo action dictionaries before WordPress named-destination metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationActionDictionaryBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationActionDictionaryBoundaryCurrentBasePdf()
        );

        $t->same(['GoTo Action Dest', 'Plain Dest Dict', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 130.0], $destinations[2]['coordinates']);
    },
    'keeps action dictionary payloads out of visible WordPress text and destination review rows' => static function (
        TestRunner $t
    ) use ($namedDestinationActionDictionaryBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationActionDictionaryBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('GoTo destination page', $plainText);
        $t->contains('Plain dictionary destination page', $plainText);
        foreach ([
            'URI Masquerade',
            'Launch Masquerade',
            'JavaScript Masquerade',
            'Malformed Action Type',
            'LegacyLaunch',
            'hidden-destination-action',
            'hidden named destination',
            'launch-payload.exe',
            'legacy-payload.sh',
            '111',
            '222',
            '333',
        ] as $hidden) {
            $t->true(is_string($encoded) && !str_contains($encoded, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
