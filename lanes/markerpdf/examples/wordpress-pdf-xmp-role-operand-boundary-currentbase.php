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
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Hidden Role Operand XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>XMP Role Operand Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Tailed role operands must not promote document XMP metadata.</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-role-operand-boundary</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>Role Operand Boundary Producer</pdf:Producer>'
    . '<xmp:CreatorTool>Role Operand Boundary Tool</xmp:CreatorTool>'
    . '<xmp:CreateDate>2026-06-08T05:03:51-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-08T09:03:51Z</xmp:MetadataDate>'
    . '</rdf:Description>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$content = 'BT /F1 12 Tf 72 720 Td (XMP Role Operand Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype 7 0 R /Length " . strlen($xmp) . " >>\nstream\n{$xmp}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Role Operand Info Title) /Author (Info Role Operand Author) /Producer (Info Role Operand Producer) >>\nendobj\n"
    . "7 0 obj\n/XML /EmbeddedFile\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$review = $metadata['catalog']['metadata_stream_review'] ?? [];

if (($review['status'] ?? null) !== 'rejected_tailed_metadata_stream_role_operand') {
    throw new RuntimeException('Expected tailed metadata stream role operand to stay review-only.');
}
if (($metadata['title'] ?? null) !== 'Role Operand Info Title') {
    throw new RuntimeException('Expected trailer Info title fallback to win after rejecting tailed XMP role operand.');
}
if (!is_string($encoded) || str_contains($encoded, 'Hidden Role Operand XMP Title')) {
    throw new RuntimeException('Expected rejected XMP text values to stay redacted from document metadata.');
}
if (str_contains($plainText, 'Hidden Role Operand XMP Title')) {
    throw new RuntimeException('Expected rejected XMP stream payload to stay out of visible WordPress paragraphs.');
}

$htmlJson = static fn (array $data): string => htmlspecialchars(
    json_encode($data, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

echo '<!-- markerpdf-pdf-xmp-role-operand-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-role-operand-boundary',
    'native_boundary' => 'Catalog /Metadata stream /Type and /Subtype role names must be single PDF name operands before document XMP promotion',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'metadata_source' => $metadata['source'],
    'review_status' => $review['status'] ?? null,
    'tailed_role_keys' => $review['tailed_role_keys'] ?? [],
    'info_fallback_title_selected' => ($metadata['title'] ?? null) === 'Role Operand Info Title',
    'xmp_payload_redacted' => is_string($encoded) && !str_contains($encoded, 'Hidden Role Operand XMP Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Hidden Role Operand XMP Title'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'producer' => $metadata['producer'] ?? null,
    'metadata_stream_review' => [
        'status' => $review['status'] ?? null,
        'tailed_role_keys' => $review['tailed_role_keys'] ?? [],
        'payload_included' => $review['payload_included'] ?? null,
        'accepted_as_document_xmp' => $review['accepted_as_document_xmp'] ?? null,
    ],
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
