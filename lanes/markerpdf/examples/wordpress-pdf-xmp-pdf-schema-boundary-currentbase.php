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
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress PDF Schema Review</rdf:li></rdf:Alt></dc:title>'
    . '<pdf:Producer>WordPress Native PDF Producer</pdf:Producer>'
    . '<pdf:Keywords>wordpress, xmp-pdf-schema; review</pdf:Keywords>'
    . '<pdf:PDFVersion>1.7</pdf:PDFVersion>'
    . '<pdf:Trapped>False</pdf:Trapped>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$compressedXmp = gzcompress($xmp);
if (!is_string($compressedXmp)) {
    throw new RuntimeException('Unable to compress XMP PDF schema smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Visible PDF Schema Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\nstream\n{$compressedXmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Stale Info PDF Schema Title) /Producer (Stale Info Producer) /Keywords (stale, info) /Trapped /True >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$pdfSchema = $metadata['xmp_pdf'] ?? [];

if (($metadata['title'] ?? null) !== 'WordPress PDF Schema Review') {
    throw new RuntimeException('Expected Catalog XMP title before stale Info title.');
}
if (($metadata['producer'] ?? null) !== 'WordPress Native PDF Producer') {
    throw new RuntimeException('Expected PDF namespace producer before stale Info producer.');
}
if (($metadata['keywords'] ?? []) !== ['wordpress', 'xmp-pdf-schema', 'review']) {
    throw new RuntimeException('Expected PDF namespace keywords to split for import review.');
}
if (($pdfSchema['pdf_version'] ?? null) !== '1.7' || ($pdfSchema['trapped_normalized'] ?? null) !== 'False') {
    throw new RuntimeException('Expected xmp_pdf review metadata to expose PDFVersion and normalized Trapped.');
}
if (str_contains($plainText, 'WordPress PDF Schema Review') || str_contains($plainText, 'WordPress Native PDF Producer')) {
    throw new RuntimeException('XMP PDF schema values leaked into visible text output.');
}

echo '<!-- markerpdf-xmp-pdf-schema-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'Catalog /Metadata XMP PDF namespace scalars remain review metadata before WordPress import',
    'xmp_preferred_over_info' => ($metadata['title'] ?? null) === 'WordPress PDF Schema Review',
    'pdf_schema_review_present' => ($pdfSchema['source'] ?? null) === 'xmp_pdf',
    'pdf_schema_payload_included' => $pdfSchema['payload_included'] ?? null,
    'pdf_version' => $pdfSchema['pdf_version'] ?? null,
    'trapped_normalized' => $pdfSchema['trapped_normalized'] ?? null,
    'metadata_stream_excluded_from_text' => !str_contains($plainText, 'WordPress PDF Schema Review'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . htmlspecialchars(json_encode([
    'keywords' => $metadata['keywords'] ?? [],
    'producer' => $metadata['producer'] ?? null,
    'xmp_pdf' => [
        'review_only' => $pdfSchema['review_only'] ?? null,
        'payload_included' => $pdfSchema['payload_included'] ?? null,
        'pdf_version' => $pdfSchema['pdf_version'] ?? null,
        'trapped_normalized' => $pdfSchema['trapped_normalized'] ?? null,
    ],
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
