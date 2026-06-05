<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfWithCatalogMetadata = static function (
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

$badCompressedXmp = 'not-a-valid-flate-xmp-stream with Unreadable Metadata XMP Leak Title';
$unreadableMetadataObject = "5 0 obj\n"
    . '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($badCompressedXmp) . " >>\n"
    . "stream\n{$badCompressedXmp}\nendstream\nendobj\n";

$cases = [
    'direct_metadata_dictionary' => $pdfWithCatalogMetadata(
        '<< /Type /Metadata /Subtype /XML /HiddenTitle (Direct Catalog Metadata Leak) >>',
        'Direct Metadata Boundary Body'
    ),
    'unresolved_metadata_reference' => $pdfWithCatalogMetadata(
        '99 0 R',
        'Unresolved Metadata Boundary Body'
    ),
    'unreadable_metadata_stream' => $pdfWithCatalogMetadata(
        '5 0 R',
        'Unreadable Metadata Boundary Body',
        $unreadableMetadataObject
    ),
];

$extractor = new PdfMetadataExtractor();
$textExtractor = new PdfTextExtractor();
$reviews = [];
foreach ($cases as $name => $pdf) {
    $metadata = $extractor->extractDocumentMetadata($pdf);
    $plainText = $textExtractor->extractPlainText($pdf);
    $encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
    $review = $metadata['catalog']['metadata_stream_review'] ?? [];

    if (($metadata['title'] ?? null) !== 'Metadata Boundary Info Title') {
        throw new RuntimeException('Expected Info metadata fallback for ' . $name . '.');
    }
    if (($metadata['xmp'] ?? []) !== []) {
        throw new RuntimeException('Expected no promoted XMP metadata for ' . $name . '.');
    }
    if (($review['accepted_as_document_xmp'] ?? null) !== false) {
        throw new RuntimeException('Expected catalog Metadata boundary rejection for ' . $name . '.');
    }
    if (
        !is_string($encoded)
        || str_contains($encoded, 'Direct Catalog Metadata Leak')
        || str_contains($encoded, 'Unreadable Metadata XMP Leak Title')
        || str_contains($plainText, 'Direct Catalog Metadata Leak')
        || str_contains($plainText, 'Unreadable Metadata XMP Leak Title')
    ) {
        throw new RuntimeException('Rejected catalog Metadata payload leaked into WordPress output for ' . $name . '.');
    }

    $reviews[$name] = [
        'status' => $review['status'] ?? null,
        'object_number' => $review['object_number'] ?? null,
        'type' => $review['type'] ?? null,
        'subtype' => $review['subtype'] ?? null,
        'filters' => $review['filters'] ?? [],
        'declared_length' => $review['declared_length'] ?? null,
        'visible_text' => $plainText,
    ];
}

if (($reviews['direct_metadata_dictionary']['status'] ?? null) !== 'rejected_non_indirect_metadata_reference') {
    throw new RuntimeException('Expected direct catalog Metadata dictionaries to be rejected.');
}
if (($reviews['unresolved_metadata_reference']['status'] ?? null) !== 'unresolved_metadata_reference') {
    throw new RuntimeException('Expected unresolved catalog Metadata references to be rejected.');
}
if (($reviews['unreadable_metadata_stream']['status'] ?? null) !== 'unreadable_metadata_stream') {
    throw new RuntimeException('Expected unreadable catalog Metadata streams to be rejected.');
}
if (($reviews['unreadable_metadata_stream']['filters'] ?? []) !== ['FlateDecode']) {
    throw new RuntimeException('Expected unreadable catalog Metadata stream filters to be preserved for review.');
}
if (($reviews['unreadable_metadata_stream']['declared_length'] ?? null) !== strlen($badCompressedXmp)) {
    throw new RuntimeException('Expected unreadable catalog Metadata declared length to be preserved for review.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-metadata-reference-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-catalog-metadata-reference-boundary',
    'native_boundary' => 'Catalog /Metadata must be an indirect readable /Type /Metadata /Subtype /XML stream before document XMP promotion',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'direct_metadata_dictionary_rejected' => ($reviews['direct_metadata_dictionary']['status'] ?? null) === 'rejected_non_indirect_metadata_reference',
    'unresolved_reference_rejected' => ($reviews['unresolved_metadata_reference']['status'] ?? null) === 'unresolved_metadata_reference',
    'unreadable_stream_rejected' => ($reviews['unreadable_metadata_stream']['status'] ?? null) === 'unreadable_metadata_stream',
    'unreadable_filters_preserved' => ($reviews['unreadable_metadata_stream']['filters'] ?? []) === ['FlateDecode'],
    'unreadable_declared_length_preserved' => ($reviews['unreadable_metadata_stream']['declared_length'] ?? null) === strlen($badCompressedXmp),
    'payload_values_excluded_from_metadata' => true,
    'payload_values_excluded_from_visible_text' => true,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>Metadata Boundary Info Title</p>' . "\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:catalog-metadata-boundary-review ' . $htmlJson($reviews) . " -->\n";
