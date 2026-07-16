<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationNameKeyBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (String key destination page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Legacy destination page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyNameKey [4 0 R /FitV 130] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(Current String Key) (Review Summary)] /Names [(Current String Key) [3 0 R /FitH 700] /NameObjectStale [4 0 R /FitH 111] 12 0 R [4 0 R /FitBH 222] (Review Summary) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "12 0 obj\n/IndirectNameObjectStale\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'rejects PDF-name keys in destination name trees while preserving legacy Dests name keys' => static function (
        TestRunner $t
    ) use ($namedDestinationNameKeyBoundaryCurrentBasePdf): void {
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations(
            $namedDestinationNameKeyBoundaryCurrentBasePdf()
        );

        $t->same(['Current String Key', 'Review Summary', 'LegacyNameKey'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['left' => 130.0], $destinations[2]['coordinates']);
    },
    'keeps malformed name-tree name-object rows out of WordPress visible text and metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationNameKeyBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationNameKeyBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $encoded = json_encode($destinations, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $t->contains('String key destination page', $plainText);
        $t->contains('Legacy destination page', $plainText);
        $t->true(is_string($encoded) && !str_contains($encoded, 'NameObjectStale'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'IndirectNameObjectStale'));
        $t->true(is_string($encoded) && !str_contains($encoded, '111'));
        $t->true(is_string($encoded) && !str_contains($encoded, '222'));
        $t->true(!str_contains($plainText, 'NameObjectStale'));
        $t->true(!str_contains($plainText, 'IndirectNameObjectStale'));
    },
];
