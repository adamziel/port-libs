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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Current Stream Filter Dictionary XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">The metadata dictionary string contains a fake Filter token.</rdf:li></rdf:Alt></dc:description>'
    . '<pdf:Producer>Stream Filter Dictionary Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Stream Filter Dictionary Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-05T09:33:52-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T13:33:52Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$content = 'BT /F1 12 Tf 72 720 Td (XMP Stream Filter Dictionary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Desc (Decoy /Filter /FlateDecode inside metadata string) /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Stream Filter Dictionary Info Title) /Author (Info Stream Filter Author) /Producer (Info Stream Filter Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current Stream Filter Dictionary XMP Title') {
    throw new RuntimeException('Expected valid unfiltered XMP to promote despite fake /Filter text inside a dictionary string.');
}
if (isset($metadata['catalog']['metadata_stream_review'])) {
    throw new RuntimeException('Did not expect catalog metadata stream review for valid promoted XMP.');
}
if (!is_string($encoded) || str_contains($encoded, 'Decoy /Filter /FlateDecode')) {
    throw new RuntimeException('Fake filter dictionary string leaked into metadata output.');
}
if (str_contains($plainText, 'Current Stream Filter Dictionary XMP Title')) {
    throw new RuntimeException('XMP values leaked into visible WordPress text.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-stream-filter-dictionary-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-stream-filter-dictionary-tokenizer',
    'native_boundary' => 'Only top-level stream dictionary /Filter values control metadata stream decoding',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'xmp_title_selected' => $metadata['title'] ?? null,
    'info_fallback_title_preserved' => $metadata['info']['Title'] ?? null,
    'fake_filter_string_ignored' => true,
    'catalog_review_absent' => !isset($metadata['catalog']['metadata_stream_review']),
    'fake_filter_not_leaked' => !str_contains($encoded, 'Decoy /Filter /FlateDecode'),
    'xmp_not_visible_text' => !str_contains($plainText, 'Current Stream Filter Dictionary XMP Title'),
],) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
