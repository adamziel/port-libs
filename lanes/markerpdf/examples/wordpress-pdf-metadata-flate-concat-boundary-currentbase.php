<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = static function (string $title, string $description, string $keyword): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>WordPress Metadata Boundary Author</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>' . htmlspecialchars($keyword, ENT_XML1) . '</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>WordPress Flate Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Flate Boundary Smoke</xmp:CreatorTool>'
        . '<xmp:MetadataDate>2026-06-08T14:49:52Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$pdf = static function (string $metadataPayload, string $bodyText, string $infoTitle): string {
    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataPayload) . " >>\nstream\n{$metadataPayload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "7 0 obj\n<< /Title (" . $infoTitle . ") /Author (WordPress Info Fallback Author) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 7 0 R >>\n%%EOF";
};

$trustedXmp = $xmp(
    'Trusted WordPress Flate XMP Title',
    'Clean Flate metadata can safely populate document metadata',
    'trusted-flate-metadata'
);
$decoyXmp = $xmp(
    'Concatenated Flate Decoy XMP Title',
    'A second compressed member is not a bounded metadata stream',
    'concatenated-flate-decoy'
);
$trustedCompressed = gzcompress($trustedXmp);
$decoyCompressed = gzcompress($decoyXmp);
if (!is_string($trustedCompressed) || !is_string($decoyCompressed)) {
    throw new RuntimeException('Unable to compress focused metadata smoke fixtures.');
}

$concatPdf = $pdf(
    $trustedCompressed . $decoyCompressed,
    'Visible WordPress metadata fallback body',
    'WordPress Info Fallback Flate Title'
);
$cleanPdf = $pdf(
    $trustedCompressed . " \t",
    'Visible WordPress clean metadata body',
    'WordPress Info Clean Flate Title'
);

$metadataExtractor = new PdfMetadataExtractor();
$textExtractor = new PdfTextExtractor();

$concatMetadata = $metadataExtractor->extractDocumentMetadata($concatPdf);
$cleanMetadata = $metadataExtractor->extractDocumentMetadata($cleanPdf);
$concatReview = $concatMetadata['catalog']['metadata_stream_review'] ?? [];
$concatText = $textExtractor->extractPlainText($concatPdf);
$cleanText = $textExtractor->extractPlainText($cleanPdf);

$result = [
    'scenario' => 'wordpress_pdf_metadata_flate_concatenated_member_boundary_currentbase',
    'concat_metadata_promoted' => in_array('xmp', $concatMetadata['source'] ?? [], true),
    'concat_uses_info_fallback' => ($concatMetadata['title'] ?? null) === 'WordPress Info Fallback Flate Title',
    'concat_review_status' => $concatReview['status'] ?? null,
    'concat_payload_included' => $concatReview['payload_included'] ?? null,
    'clean_metadata_promoted' => ($cleanMetadata['title'] ?? null) === 'Trusted WordPress Flate XMP Title',
    'clean_text_visible' => $cleanText === 'Visible WordPress clean metadata body',
    'concat_text_visible' => $concatText === 'Visible WordPress metadata fallback body',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

$result['self_test_passed'] =
    $result['concat_metadata_promoted'] === false
    && $result['concat_uses_info_fallback'] === true
    && $result['concat_review_status'] === 'unreadable_metadata_stream'
    && $result['concat_payload_included'] === false
    && $result['clean_metadata_promoted'] === true
    && $result['clean_text_visible'] === true
    && $result['concat_text_visible'] === true;

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['self_test_passed'] ? 0 : 1);
