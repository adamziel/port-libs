<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationMalformedUtf16BoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Malformed UTF16 destination source page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Current review destination target page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /LegacyOk [4 0 R /FitV 120] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(Current Review) (LegacyOk)] /Names [<FEFF004100> [4 0 R /FitH 111] (Current Review) [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'fails closed on malformed UTF-16 destination name-tree keys without PHP notices' => static function (
        TestRunner $t
    ) use ($namedDestinationMalformedUtf16BoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationMalformedUtf16BoundaryCurrentBasePdf();
        $errors = [];

        set_error_handler(static function (int $severity, string $message) use (&$errors): bool {
            $errors[] = $severity . ':' . $message;

            return true;
        });
        try {
            $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        } finally {
            restore_error_handler();
        }

        $t->same([], $errors);
        $t->same(['Current Review', 'LegacyOk'], array_column($destinations, 'name'));
        $t->same([1, 1], array_column($destinations, 'page'));
        $t->same([4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['XYZ', 'FitV'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[0]['coordinates']);
        $t->same(['left' => 120.0], $destinations[1]['coordinates']);
    },
    'keeps malformed UTF-16 destination key rows out of WordPress text and metadata' => static function (
        TestRunner $t
    ) use ($namedDestinationMalformedUtf16BoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationMalformedUtf16BoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode([$destinations, $metadata['document_destinations'] ?? []], JSON_UNESCAPED_SLASHES) ?: '';

        $t->same(['Current Review', 'LegacyOk'], $metadata['document_destinations']['names'] ?? []);
        $t->same(2, $metadata['document_destinations']['count'] ?? null);
        $t->contains('Malformed UTF16 destination source page', $plainText);
        $t->contains('Current review destination target page', $plainText);
        foreach (["\xfe\xff\x00A\x00", 'FitH 111', 'Current Review', 'LegacyOk'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
        $t->same(false, str_contains($encoded, '111'));
    },
];
