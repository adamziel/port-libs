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
    string $description,
    string $date,
    string $rootPrefix = 'x',
    string $rootNamespace = 'adobe:ns:meta/'
): string {
    return '<' . $rootPrefix . ':xmpmeta xmlns:' . $rootPrefix . '="' . htmlspecialchars($rootNamespace, ENT_XML1) . '">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>Namespace Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-namespace-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Namespace Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Namespace Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T06:23:17Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</' . $rootPrefix . ':xmpmeta>';
};

$wrongNamespace = $xmpPacket(
    'Wrong Namespace Decoy XMP Title',
    'A non-Adobe xmpmeta local-name wrapper must not block the current packet.',
    '2026-06-05T06:59:59Z',
    'notxmp',
    'urn:not-adobe-xmp'
);
$currentXmp = $xmpPacket(
    'Current Namespace Boundary XMP Title',
    'Current Adobe XMP root follows a non-document xmpmeta wrapper',
    '2026-06-05T02:23:17-04:00'
);
$trailingXmp = $xmpPacket(
    'Trailing Namespace Decoy XMP Title',
    'Trailing namespace packet stays outside the current root.',
    '2026-06-05T07:00:00Z'
);
$metadataBytes = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . $wrongNamespace
    . $currentXmp
    . '<?xpacket end="w"?>'
    . "\0\0"
    . $trailingXmp;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP namespace boundary smoke fixture.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Namespace XMP Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Namespace Boundary Info Title) /Author (Info Namespace Author) /Producer (Info Namespace Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'Current Namespace Boundary XMP Title') {
    throw new RuntimeException('Expected current Adobe XMP title to win past a non-Adobe xmpmeta wrapper.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected XMP namespace boundary fallback to be recorded.');
}
if (!is_string($encoded) || str_contains($encoded, 'Wrong Namespace Decoy XMP Title') || str_contains($encoded, 'Trailing Namespace Decoy XMP Title')) {
    throw new RuntimeException('Expected non-document and trailing XMP titles to stay out of metadata JSON.');
}
if (str_contains($plainText, 'Current Namespace Boundary XMP Title') || str_contains($plainText, 'Wrong Namespace Decoy XMP Title')) {
    throw new RuntimeException('Expected XMP packet text to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-namespace-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-xmp-root-namespace-boundary',
    'native_boundary' => 'Catalog /Metadata XMP root scan skips non-Adobe xmpmeta wrappers before promoting document XMP',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'source' => $metadata['source'],
    'title_from_current_adobe_xmp' => ($metadata['title'] ?? null) === 'Current Namespace Boundary XMP Title',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'wrong_namespace_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Wrong Namespace Decoy XMP Title'),
    'trailing_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing Namespace Decoy XMP Title'),
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Namespace Boundary XMP Title')
        && !str_contains($plainText, 'Wrong Namespace Decoy XMP Title'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf:document-metadata ' . $htmlJson([
    'authors' => $metadata['authors'] ?? [],
    'description' => $metadata['description'] ?? null,
    'keywords' => $metadata['keywords'] ?? [],
    'creator_tool' => $metadata['creator_tool'] ?? null,
    'producer' => $metadata['producer'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
]) . " -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
