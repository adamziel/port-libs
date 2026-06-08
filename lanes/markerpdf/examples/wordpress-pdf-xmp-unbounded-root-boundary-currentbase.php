<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$xmpRoot = static function (string $title, string $description, string $date): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>WordPress Unbounded Root Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-unbounded-root-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>WordPress Unbounded Root Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Unbounded Root Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T10:08:21Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$malformedFirstRoot = preg_replace(
    '/<\/x:xmpmeta>$/',
    '',
    $xmpRoot(
        'WordPress Unbounded First XMP Title',
        'An unclosed first XMP root must fail closed before import.',
        '2026-06-08T06:08:21-04:00'
    )
) ?? '';
$trailingDecoyRoot = $xmpRoot(
    'WordPress Trailing XMP Decoy Title',
    'A trailing valid-looking root must stay outside document metadata.',
    '2026-06-08T10:59:59Z'
);
$metadataBytes = $malformedFirstRoot . "\0\0\n" . $trailingDecoyRoot;
$content = 'BT /F1 12 Tf 72 720 Td (XMP Unbounded Root WordPress Body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Length " . strlen($metadataBytes) . " >>\nstream\n{$metadataBytes}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Info Fallback Title) /Author (Info Unbounded Root Author) /Producer (Info Unbounded Root Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$review = $metadata['catalog']['metadata_stream_review'] ?? [];
$summary = $review['xmp_summary'] ?? [];

$flags = [
    'scenario' => 'wordpress-pdf-xmp-unbounded-root-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-root-boundary',
    'native_boundary' => 'Unbounded unpacketed Adobe xmpmeta roots fail closed before inner RDF or trailing root fallback',
    'source' => $metadata['source'] ?? [],
    'info_fallback_title' => $metadata['title'] ?? null,
    'review_status' => $review['status'] ?? null,
    'summary_status' => $summary['status'] ?? null,
    'malformed_packet_reason' => $summary['malformed_packet_reason'] ?? null,
    'malformed_document_xmp_rejected' => ($review['status'] ?? null) === 'rejected_malformed_document_xmp_packet',
    'inner_rdf_title_not_promoted' => !str_contains($encoded, 'WordPress Unbounded First XMP Title'),
    'trailing_xmp_decoy_excluded' => !str_contains($encoded, 'WordPress Trailing XMP Decoy Title'),
    'visible_text_excludes_xmp_metadata' => !str_contains($plainText, 'WordPress Unbounded First XMP Title')
        && !str_contains($plainText, 'WordPress Trailing XMP Decoy Title'),
    'payload_included' => $review['payload_included'] ?? null,
    'accepted_as_document_xmp' => $review['accepted_as_document_xmp'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    ($metadata['title'] ?? null) !== 'WordPress Info Fallback Title'
    || ($review['status'] ?? null) !== 'rejected_malformed_document_xmp_packet'
    || ($summary['status'] ?? null) !== 'rejected_malformed_first_xmp_packet'
    || ($summary['malformed_packet_reason'] ?? null) !== 'unbounded_adobe_xmpmeta_root'
    || str_contains($encoded, 'WordPress Unbounded First XMP Title')
    || str_contains($encoded, 'WordPress Trailing XMP Decoy Title')
    || str_contains($plainText, 'WordPress Unbounded First XMP Title')
    || str_contains($plainText, 'WordPress Trailing XMP Decoy Title')
) {
    throw new RuntimeException('Expected unbounded XMP root boundary smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

if (in_array('--self-test', $argv, true)) {
    echo "OK markerpdf-xmp-unbounded-root-boundary-currentbase\n";
}

echo '<!-- markerpdf-pdf-xmp-unbounded-root-boundary-currentbase ' . $htmlJson($flags) . " -->\n";
echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:catalog-metadata-review ' . $htmlJson([
    'status' => $review['status'] ?? null,
    'object_number' => $review['object_number'] ?? null,
    'type' => $review['type'] ?? null,
    'subtype' => $review['subtype'] ?? null,
    'bytes' => $review['bytes'] ?? null,
    'sha256' => $review['sha256'] ?? null,
    'xmp_summary' => $summary,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
