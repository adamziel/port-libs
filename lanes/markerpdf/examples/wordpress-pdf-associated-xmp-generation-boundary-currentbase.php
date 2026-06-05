<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$xmpPacket = static function (string $title, string $description, string $date): string {
    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$rootXmp = $xmpPacket('Current Generation Root XMP Title', 'Root XMP remains the document metadata source', '2026-06-05T00:08:56Z');
$staleAttachmentXmp = $xmpPacket('Stale Generation Attachment XMP Title', 'Stale same-object generation must not be summarized', '2026-06-05T00:09:56Z');
$currentAttachmentXmp = $xmpPacket('Current Generation Attachment XMP Title', 'Only exact FileSpec metadata generation is summarized', '2026-06-05T00:10:56Z');

$rootXmpStream = gzcompress($rootXmp);
$staleAttachmentXmpStream = gzcompress($staleAttachmentXmp);
$currentAttachmentXmpStream = gzcompress($currentAttachmentXmp);
if (!is_string($rootXmpStream) || !is_string($staleAttachmentXmpStream) || !is_string($currentAttachmentXmpStream)) {
    throw new RuntimeException('Unable to compress associated XMP generation smoke streams.');
}

$mismatchedPayload = '<wp-export><post id="mismatched-generation"/></wp-export>';
$exactPayload = '<wp-export><post id="exact-generation"/></wp-export>';
$content = 'BT /F1 12 Tf 72 720 Td (Current XMP Generation Boundary Body) Tj ET';

$pdf = "%PDF-2.0\n";
$offsets = [];
$generations = [];
$addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets, &$generations): void {
    $offsets[$objectNumber] = strlen($pdf);
    $generations[$objectNumber] = $generation;
    $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";
};

$addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R /AF [10 0 R 12 0 R] >>');
$addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
$addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>');
$addObject(4, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
$addObject(5, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($rootXmpStream) . " >>\nstream\n{$rootXmpStream}\nendstream");
$addObject(6, 0, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($staleAttachmentXmpStream) . " >>\nstream\n{$staleAttachmentXmpStream}\nendstream");
$addObject(6, 1, '<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length ' . strlen($currentAttachmentXmpStream) . " >>\nstream\n{$currentAttachmentXmpStream}\nendstream");
$addObject(10, 0, '<< /Type /Filespec /F (mismatched-generation.xml) /Desc (Mismatched generation attachment) /AFRelationship /Source /Metadata 6 0 R /EF << /F 11 0 R >> >>');
$addObject(11, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($mismatchedPayload) . " >>\nstream\n{$mismatchedPayload}\nendstream");
$addObject(12, 0, '<< /Type /Filespec /F (exact-generation.xml) /Desc (Exact generation attachment) /AFRelationship /Schema /Metadata 6 1 R /EF << /F 13 0 R >> >>');
$addObject(13, 0, '<< /Type /EmbeddedFile /Subtype /text#2Fxml /Length ' . strlen($exactPayload) . " >>\nstream\n{$exactPayload}\nendstream");

$xrefOffset = strlen($pdf);
$rows = '';
for ($objectNumber = 0; $objectNumber < 91; $objectNumber++) {
    if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 90)) {
        $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
        continue;
    }

    $rows .= pack('CNn', 1, $objectNumber === 90 ? $xrefOffset : $offsets[$objectNumber], $generations[$objectNumber] ?? 0);
}

$compressedXref = gzcompress($rows);
if (!is_string($compressedXref)) {
    throw new RuntimeException('Unable to compress associated XMP generation xref stream.');
}

$pdf .= "90 0 obj\n"
    . '<< /Type /XRef /Size 91 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
    . "stream\n{$compressedXref}\nendstream\nendobj\n"
    . "startxref\n{$xrefOffset}\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$files = $metadata['associated_files'] ?? [];
$mismatchedProvenance = is_array($files[0]['provenance_review'] ?? null) ? $files[0]['provenance_review'] : [];
$exactProvenance = is_array($files[1]['provenance_review'] ?? null) ? $files[1]['provenance_review'] : [];
$exactXmp = is_array($exactProvenance['xmp_metadata'] ?? null) ? $exactProvenance['xmp_metadata'] : [];

if (($metadata['title'] ?? null) !== 'Current Generation Root XMP Title') {
    throw new RuntimeException('Expected current root XMP to remain the document metadata source.');
}
if (array_key_exists('xmp_metadata', $mismatchedProvenance)) {
    throw new RuntimeException('Expected mismatched FileSpec XMP generation to be excluded from provenance.');
}
if (($exactXmp['object_generation'] ?? null) !== 1) {
    throw new RuntimeException('Expected exact FileSpec XMP generation to be summarized.');
}
if (str_contains($encoded, 'Stale Generation Attachment XMP Title') || str_contains($encoded, $mismatchedPayload)) {
    throw new RuntimeException('Expected stale associated XMP and payload bytes to stay out of metadata.');
}
if (str_contains($plainText, 'Current Generation Attachment XMP Title') || str_contains($plainText, '<wp-export>')) {
    throw new RuntimeException('Expected attachment XMP and payloads to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-associated-xmp-generation-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-associated-xmp-generation-boundary-currentbase',
    'support_component' => 'native-pdf-associated-xmp-generation-boundary',
    'native_boundary' => 'FileSpec /Metadata XMP provenance resolves exact object generation before review summaries',
    'source' => $metadata['source'],
    'associated_file_count' => count($files),
    'mismatched_generation_excluded' => !array_key_exists('xmp_metadata', $mismatchedProvenance),
    'exact_generation_summarized' => ($exactXmp['object_generation'] ?? null) === 1,
    'attachment_xmp_payload_included' => $exactXmp['payload_included'] ?? null,
    'stale_attachment_xmp_excluded' => !str_contains($encoded, 'Stale Generation Attachment XMP Title'),
    'visible_text_excludes_xmp_and_payloads' => !str_contains($plainText, 'Current Generation Attachment XMP Title')
        && !str_contains($plainText, '<wp-export>'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:associated-file-review ' . $htmlJson([
    'filenames' => array_values(array_filter(array_map(
        static fn (mixed $file): ?string => is_array($file) && is_string($file['filename'] ?? null) ? $file['filename'] : null,
        $files
    ))),
    'mismatched_generation_has_xmp_summary' => array_key_exists('xmp_metadata', $mismatchedProvenance),
    'exact_generation_xmp_field_names' => $exactXmp['xmp_summary']['field_names'] ?? [],
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
