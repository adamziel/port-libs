<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Malformed DecodeParms XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">This XMP is behind a malformed indirect DecodeParms helper.</rdf:li></rdf:Alt></dc:description>'
    . '<pdf:Producer>DecodeParms Operand Producer</pdf:Producer>'
    . '<xmp:CreatorTool>DecodeParms Operand Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-06T12:31:24-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-06T16:31:24Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress DecodeParms XMP smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP DecodeParms Operand Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /DecodeParms 7 0 R /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (DecodeParms Operand Info Title) /Author (Info DecodeParms Author) /Producer (Info DecodeParms Producer) >>\nendobj\n"
    . "7 0 obj\n<< /Predictor 1 /Columns 1 >> /Crypt 8 0 R\nendobj\n"
    . "8 0 obj\n<< /S /JavaScript /JS (app.alert\\('indirect metadata decodeparms operand tail'\\)) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$review = $metadata['catalog']['metadata_stream_review'] ?? [];
$decodeParmsOperand = $review['decodeparms_operands'][0] ?? [];

if (($metadata['title'] ?? null) !== 'DecodeParms Operand Info Title') {
    throw new RuntimeException('Expected Info title fallback when XMP DecodeParms helper is malformed.');
}
if (($review['status'] ?? null) !== 'rejected_malformed_metadata_stream_decodeparms_operand') {
    throw new RuntimeException('Expected malformed catalog metadata DecodeParms operand review.');
}
if (($review['decodeparms_operand_policy'] ?? null) !== 'reject_malformed_decodeparms_operands') {
    throw new RuntimeException('Expected malformed metadata DecodeParms operand policy.');
}
if (($decodeParmsOperand['extra_decodeparms_name'] ?? null) !== 'Crypt') {
    throw new RuntimeException('Expected trailing /Crypt operand to be reported.');
}
if (!is_string($encoded) || str_contains($encoded, 'Malformed DecodeParms XMP Title')) {
    throw new RuntimeException('Malformed-DecodeParms XMP title leaked into metadata output.');
}
if (str_contains($plainText, 'Malformed DecodeParms XMP Title')) {
    throw new RuntimeException('Malformed-DecodeParms XMP title leaked into visible WordPress text.');
}
if (str_contains($plainText, 'indirect metadata decodeparms operand tail')) {
    throw new RuntimeException('Malformed DecodeParms helper action tail leaked into visible WordPress text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-decodeparms-operand-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-metadata-stream-decodeparms-operand-review',
    'native_boundary' => 'Catalog XMP stream indirect /DecodeParms helpers must resolve to one dictionary or null operand before WordPress metadata import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'info_fallback_title_selected' => $metadata['title'] ?? null,
    'xmp_promoted' => ($metadata['xmp'] ?? []) !== [],
    'review_status' => $review['status'] ?? null,
    'decodeparms_operand_policy' => $review['decodeparms_operand_policy'] ?? null,
    'invalid_decodeparms_operand_count' => $review['invalid_decodeparms_operand_count'] ?? null,
    'malformed_decodeparms_operand_count' => $review['malformed_decodeparms_operand_count'] ?? null,
    'extra_decodeparms_name' => $decodeParmsOperand['extra_decodeparms_name'] ?? null,
    'xmp_title_excluded' => !str_contains($encoded, 'Malformed DecodeParms XMP Title'),
    'helper_tail_not_visible' => !str_contains($plainText, 'indirect metadata decodeparms operand tail'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
