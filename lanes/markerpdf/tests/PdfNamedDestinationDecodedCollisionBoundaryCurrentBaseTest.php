<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfNamedDestinationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$namedDestinationDecodedCollisionBoundaryCurrentBasePdf = static function (): string {
    $firstPageContent = 'BT /F1 12 Tf 72 720 Td (Decoded collision first target page) Tj ET';
    $secondPageContent = 'BT /F1 12 Tf 72 720 Td (Decoded collision UTF16 target page) Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /Dests 8 0 R >> /Dests << /Collision [3 0 R /FitV 88] /LegacyTail [4 0 R /FitBH 500] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R /Resources << /Font << /F1 40 0 R >> >> >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(Collision) <FEFF0043006F006C006C006900730069006F006E>] /Names [(Collision) [3 0 R /FitH 700] <FEFF0043006F006C006C006900730069006F006E> [4 0 R /XYZ 72 640 0]] >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF\n";
};

return [
    'preserves distinct raw name-tree byte-string destinations with the same decoded WordPress label' => static function (
        TestRunner $t
    ) use ($namedDestinationDecodedCollisionBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationDecodedCollisionBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);

        $t->same(['Collision', 'Collision', 'LegacyTail'], array_column($destinations, 'name'));
        $t->same([0, 1, 1], array_column($destinations, 'page'));
        $t->same([3, 4, 4], array_column($destinations, 'page_object_id'));
        $t->same(['FitH', 'XYZ', 'FitBH'], array_column($destinations, 'fit'));
        $t->same(['names-tree', 'names-tree', 'legacy-dests'], array_column($destinations, 'source'));
        $t->same(['top' => 700.0], $destinations[0]['coordinates']);
        $t->same(['left' => 72.0, 'top' => 640.0, 'zoom' => 0.0], $destinations[1]['coordinates']);
        $t->same(['top' => 500.0], $destinations[2]['coordinates']);
        $t->same('436f6c6c6973696f6e', $destinations[0]['name_bytes_hex'] ?? null);
        $t->same('feff0043006f006c006c006900730069006f006e', $destinations[1]['name_bytes_hex'] ?? null);
        $t->same(false, isset($destinations[2]['name_bytes_hex']));

        $documentDestinations = $metadata['document_destinations'] ?? [];
        $t->same(['Collision', 'Collision', 'LegacyTail'], $documentDestinations['names'] ?? null);
        $t->same(3, $documentDestinations['count'] ?? null);
        $t->same([0, 1, 1], array_column($documentDestinations['destinations'] ?? [], 'page'));
        $t->same(['FitH', 'XYZ', 'FitBH'], array_column($documentDestinations['destinations'] ?? [], 'view_mode'));
        $t->same('436f6c6c6973696f6e', $documentDestinations['destinations'][0]['name_bytes_hex'] ?? null);
        $t->same('feff0043006f006c006c006900730069006f006e', $documentDestinations['destinations'][1]['name_bytes_hex'] ?? null);
        $t->same(false, isset($documentDestinations['destinations'][2]['name_bytes_hex']));
    },
    'keeps decoded-collision destination operands out of visible WordPress text' => static function (
        TestRunner $t
    ) use ($namedDestinationDecodedCollisionBoundaryCurrentBasePdf): void {
        $pdf = $namedDestinationDecodedCollisionBoundaryCurrentBasePdf();
        $destinations = (new PdfNamedDestinationExtractor())->extractNamedDestinations($pdf);
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encodedReview = json_encode([$destinations, $metadata['document_destinations'] ?? []], JSON_UNESCAPED_SLASHES) ?: '';

        $t->contains('Decoded collision first target page', $plainText);
        $t->contains('Decoded collision UTF16 target page', $plainText);
        $t->same(4, substr_count($encodedReview, '"name":"Collision"'));
        $t->same(true, str_contains($encodedReview, '436f6c6c6973696f6e'));
        $t->same(true, str_contains($encodedReview, 'feff0043006f006c006c006900730069006f006e'));
        foreach (['Collision', 'LegacyTail', 'FitV 88'] as $hidden) {
            $t->same(false, str_contains($plainText, $hidden));
        }
    },
];
