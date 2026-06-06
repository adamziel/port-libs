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

$xmpPacket = static function (
    string $title,
    array $languages,
    string $rights
): string {
    $languageItems = '';
    foreach ($languages as $language) {
        $languageItems .= '<rdf:li>' . htmlspecialchars($language, ENT_XML1) . '</rdf:li>';
    }

    return '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
        . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about="#private-wordpress-dublin-core"'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' dc:language="zz-ZZ"'
        . ' dc:rights="Private WordPress rights decoy"/>'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>WordPress Dublin Core Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Dublin Core properties remain metadata during import.</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-dublin-core-review</rdf:li></rdf:Bag></dc:subject>'
        . '<dc:format>application/pdf</dc:format>'
        . '<dc:language><rdf:Bag>' . $languageItems . '</rdf:Bag></dc:language>'
        . '<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($rights, ENT_XML1) . '</rdf:li></rdf:Alt></dc:rights>'
        . '<pdf:Producer>WordPress Dublin Core Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Dublin Core Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>2026-06-06T03:19:12-04:00</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-06T07:19:12Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$currentXmp = $xmpPacket(
    'WordPress Dublin Core Review XMP Title',
    ['en-US', 'fr-CA'],
    'Copyright 2026 WordPress Import Review'
);
$trailingXmp = $xmpPacket(
    'Trailing WordPress Dublin Core Decoy Title',
    ['es-MX'],
    'Trailing WordPress rights decoy'
);
$metadataBytes = $currentXmp . "\0\0\n" . $trailingXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress WordPress XMP Dublin Core smoke metadata.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Dublin Core Review WordPress Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Dublin Core Info Title) /Author (Info Dublin Core Author) /Producer (Info Dublin Core Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';
$dublinCore = is_array($metadata['xmp_dublin_core'] ?? null) ? $metadata['xmp_dublin_core'] : [];

if (($metadata['format'] ?? null) !== 'application/pdf') {
    throw new RuntimeException('Expected XMP dc:format to be promoted as document metadata.');
}
if (($metadata['language'] ?? null) !== 'en-US' || ($metadata['languages'] ?? []) !== ['en-US', 'fr-CA']) {
    throw new RuntimeException('Expected XMP dc:language values to be preserved for WordPress review.');
}
if (($metadata['rights'] ?? null) !== 'Copyright 2026 WordPress Import Review') {
    throw new RuntimeException('Expected XMP dc:rights to be preserved as review metadata.');
}
if (($dublinCore['payload_included'] ?? null) !== false || ($dublinCore['review_only'] ?? null) !== true) {
    throw new RuntimeException('Expected XMP Dublin Core payload to remain review-only metadata.');
}
if (
    str_contains($encoded, 'Private WordPress rights decoy')
    || str_contains($encoded, 'Trailing WordPress rights decoy')
    || str_contains($encoded, 'es-MX')
) {
    throw new RuntimeException('Expected private and trailing XMP Dublin Core decoys to stay excluded.');
}
if (
    str_contains($plainText, 'WordPress Dublin Core Review XMP Title')
    || str_contains($plainText, 'Copyright 2026 WordPress Import Review')
    || str_contains($plainText, 'en-US')
) {
    throw new RuntimeException('Expected XMP Dublin Core metadata to stay out of visible WordPress paragraph text.');
}

echo '<!-- markerpdf-pdf-xmp-dublin-core-review-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-dublin-core-review-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-dublin-core-review-boundary',
    'native_boundary' => 'XMP dc:format, dc:language, and dc:rights are document metadata/review metadata bounded before private resources and trailing packets',
    'source' => $metadata['source'] ?? [],
    'format_preserved' => ($metadata['format'] ?? null) === 'application/pdf',
    'language_preserved' => ($metadata['language'] ?? null) === 'en-US',
    'languages_preserved' => ($metadata['languages'] ?? []) === ['en-US', 'fr-CA'],
    'rights_preserved' => ($metadata['rights'] ?? null) === 'Copyright 2026 WordPress Import Review',
    'review_only' => ($dublinCore['review_only'] ?? null) === true,
    'payload_included' => $dublinCore['payload_included'] ?? null,
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'private_resource_decoy_excluded' => !str_contains($encoded, 'Private WordPress rights decoy'),
    'trailing_packet_decoy_excluded' => !str_contains($encoded, 'Trailing WordPress rights decoy')
        && !str_contains($encoded, 'es-MX'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'WordPress Dublin Core Review XMP Title')
        && !str_contains($plainText, 'Copyright 2026 WordPress Import Review')
        && !str_contains($plainText, 'en-US'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:xmp-dublin-core-review ' . $htmlJson([
    'format' => $metadata['format'] ?? null,
    'language' => $metadata['language'] ?? null,
    'languages' => $metadata['languages'] ?? [],
    'rights' => $metadata['rights'] ?? null,
    'review_only' => $dublinCore['review_only'] ?? null,
    'payload_included' => $dublinCore['payload_included'] ?? null,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
