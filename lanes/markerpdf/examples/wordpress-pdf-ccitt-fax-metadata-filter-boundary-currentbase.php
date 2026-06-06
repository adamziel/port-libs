<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$hiddenXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">CCITT Filtered XMP Payload Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Fax image filter bytes must not become document metadata</rdf:li></rdf:Alt></dc:description>'
    . '<xmp:CreateDate>2026-06-06T21:23:09Z</xmp:CreateDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';
$pageText = 'CCITT filtered metadata boundary body';
$pageContent = "BT /F1 12 Tf 72 720 Td ({$pageText}) Tj ET";
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /CCF /DecodeParms << /K 0 /Columns 8 /Rows 1 /BlackIs1 true >> /Length " . strlen($hiddenXmp) . " >>\nstream\n{$hiddenXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Info Fallback Title) /Producer (Info Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$review = $metadata['catalog']['metadata_stream_review'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';

$ccittMetadataRejected = ($review['status'] ?? null) === 'rejected_ccitt_fax_metadata_stream_filter'
    && ($review['filter_operand_policy'] ?? null) === 'reject_ccitt_fax_metadata_stream_filter'
    && ($review['filters'] ?? []) === ['CCF']
    && ($review['preview_only_filters'] ?? []) === ['CCF']
    && ($review['decoded_with_current_filters'] ?? true) === false
    && ($review['native_metadata_decode'] ?? true) === false;
$infoFallbackUsed = ($metadata['source'] ?? []) === ['info', 'catalog']
    && ($metadata['xmp'] ?? null) === []
    && ($metadata['title'] ?? null) === 'Info Fallback Title'
    && ($metadata['producer'] ?? null) === 'Info Producer';
$payloadExcluded = !str_contains($encoded, 'CCITT Filtered XMP Payload Title')
    && !str_contains($encoded, 'Fax image filter bytes must not become document metadata')
    && !str_contains($plainText, 'CCITT Filtered XMP Payload Title')
    && $plainText === $pageText;

if (!$ccittMetadataRejected || !$infoFallbackUsed || !$payloadExcluded) {
    throw new RuntimeException('CCITT Fax metadata stream filter boundary smoke failed.');
}

$summary = [
    'source' => 'native-pdf-ccitt-fax-metadata-filter-boundary-currentbase',
    'upstream_boundary' => 'Catalog Metadata streams are XML metadata; CCITT Fax filters are image-raster filters and remain review-only in the no-GPU PHP port',
    'ccitt_metadata_stream_rejected' => $ccittMetadataRejected,
    'filter_operand_policy' => $review['filter_operand_policy'] ?? null,
    'filters' => $review['filters'] ?? [],
    'preview_only_filters' => $review['preview_only_filters'] ?? [],
    'decoded_with_current_filters' => $review['decoded_with_current_filters'] ?? null,
    'native_metadata_decode' => $review['native_metadata_decode'] ?? null,
    'info_fallback_used' => $infoFallbackUsed,
    'payload_excluded_from_metadata' => $payloadExcluded,
    'paragraphs' => [$plainText],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-ccitt-fax-metadata-filter-boundary-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
