<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xmpMetadataBoundaryPdf = static function (
    string $catalogMetadataValue,
    string $bodyText,
    string $extraObjects = ''
): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata {$catalogMetadataValue} >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . $extraObjects
        . "6 0 obj\n<< /Title (Metadata Boundary Info Title) /Author (Metadata Boundary Author) /Producer (Metadata Boundary Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

return [
    'treats catalog Metadata null as absent before WordPress import' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf): void {
        $pdf = $xmpMetadataBoundaryPdf(
            'null',
            'Null Metadata Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['info'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same(['Metadata Boundary Author'], $metadata['authors']);
        $t->same('Null Metadata Boundary Body', $plainText);
        $t->same(false, isset($metadata['catalog']['metadata_stream_review']));
        $t->true(is_string($encoded) && !str_contains($encoded, 'catalog_metadata_stream_boundary'));
    },
    'keeps direct catalog Metadata dictionaries review-only before WordPress import' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf): void {
        $pdf = $xmpMetadataBoundaryPdf(
            '<< /Type /Metadata /Subtype /XML /HiddenTitle (Direct Catalog Metadata Leak) >>',
            'Direct Metadata Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same(['Metadata Boundary Author'], $metadata['authors']);
        $t->same('Direct Metadata Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('rejected_non_indirect_metadata_reference', $review['status'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Direct Catalog Metadata Leak'));
        $t->true(!str_contains($plainText, 'Direct Catalog Metadata Leak'));
    },
    'keeps unresolved catalog Metadata references as fail-closed review metadata' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf): void {
        $pdf = $xmpMetadataBoundaryPdf(
            '99 0 R',
            'Unresolved Metadata Boundary Body'
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same('Unresolved Metadata Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('unresolved_metadata_reference', $review['status'] ?? null);
        $t->same(99, $review['object_number'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
    },
    'records unreadable XMP metadata stream filters without promoting payload text' => static function (
        TestRunner $t
    ) use ($xmpMetadataBoundaryPdf): void {
        $badCompressedXmp = 'not-a-valid-flate-xmp-stream with Unreadable Metadata XMP Leak Title';
        $metadataObject = "5 0 obj\n"
            . '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($badCompressedXmp) . " >>\n"
            . "stream\n{$badCompressedXmp}\nendstream\nendobj\n";
        $pdf = $xmpMetadataBoundaryPdf(
            '5 0 R',
            'Unreadable Metadata Boundary Body',
            $metadataObject
        );

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);
        $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        $review = $metadata['catalog']['metadata_stream_review'] ?? [];

        $t->same(['info', 'catalog'], $metadata['source']);
        $t->same([], $metadata['xmp']);
        $t->same('Metadata Boundary Info Title', $metadata['title']);
        $t->same('Unreadable Metadata Boundary Body', $plainText);
        $t->same('catalog_metadata_stream_boundary', $review['source'] ?? null);
        $t->same('unreadable_metadata_stream', $review['status'] ?? null);
        $t->same(5, $review['object_number'] ?? null);
        $t->same('Metadata', $review['type'] ?? null);
        $t->same('XML', $review['subtype'] ?? null);
        $t->same(['FlateDecode'], $review['filters'] ?? null);
        $t->same(strlen($badCompressedXmp), $review['declared_length'] ?? null);
        $t->same(false, $review['accepted_as_document_xmp'] ?? null);
        $t->same(false, $review['payload_included'] ?? null);
        $t->true(is_string($encoded) && !str_contains($encoded, 'Unreadable Metadata XMP Leak Title'));
        $t->true(!str_contains($plainText, 'Unreadable Metadata XMP Leak Title'));
    },
];
