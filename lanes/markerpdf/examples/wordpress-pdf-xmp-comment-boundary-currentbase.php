<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$xmpPacket = static function (string $title, string $description, string $date): string {
    return '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
        . '<rdf:Description rdf:about=""'
        . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<pdf:Producer>WordPress Comment Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Comment Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T02:12:24Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$currentXmp = $xmpPacket(
    'Current Comment Boundary XMP Title',
    'Current XMP root follows packet comments and declarations',
    '2026-06-04T22:12:24-04:00'
);
$commentDecoyXmp = $xmpPacket(
    'Comment Decoy XMP Title',
    'Comment decoy must never become document metadata',
    '2026-06-05T02:59:59Z'
);
$trailingXmp = $xmpPacket(
    'Trailing Comment Boundary Decoy Title',
    'Trailing packet stays outside the current XMP root',
    '2026-06-05T03:00:00Z'
);
$metadataBytes = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<!-- ' . $commentDecoyXmp . ' -->'
    . '<!DOCTYPE xmpmeta [<!ENTITY decoy "<rdf:RDF><rdf:Description xmlns:dc=&quot;http://purl.org/dc/elements/1.1/&quot;><dc:title>DOCTYPE Decoy XMP Title</dc:title></rdf:Description></rdf:RDF>">]>'
    . '<?adobe-xap-filters esc="CRLF"?>'
    . $currentXmp
    . '<?xpacket end="w"?>'
    . "\0\0 \n"
    . $trailingXmp;

$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress XMP comment boundary smoke metadata.');
}

$content = 'BT /F1 12 Tf 72 720 Td (XMP Comment Boundary Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Comment Boundary Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';

if (($metadata['title'] ?? null) !== 'Current Comment Boundary XMP Title') {
    throw new RuntimeException('Expected current XMP root to remain the WordPress metadata title.');
}
if (str_contains($encoded, 'Comment Decoy XMP Title') || str_contains($encoded, 'DOCTYPE Decoy XMP Title')) {
    throw new RuntimeException('Expected comment and doctype decoy XMP values to stay out of metadata.');
}
if (str_contains($encoded, 'Trailing Comment Boundary Decoy Title')) {
    throw new RuntimeException('Expected trailing XMP packet to stay outside current metadata.');
}
if (str_contains($plainText, 'Current Comment Boundary XMP Title') || str_contains($plainText, 'Comment Decoy XMP Title')) {
    throw new RuntimeException('Expected XMP packet values to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-comment-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-comment-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-comment-boundary',
    'native_boundary' => 'XMP root fallback skips comment and DOCTYPE decoy roots before metadata promotion',
    'source' => $metadata['source'] ?? [],
    'title' => $metadata['title'] ?? null,
    'comment_decoy_excluded' => !str_contains($encoded, 'Comment Decoy XMP Title'),
    'doctype_decoy_excluded' => !str_contains($encoded, 'DOCTYPE Decoy XMP Title'),
    'trailing_decoy_excluded' => !str_contains($encoded, 'Trailing Comment Boundary Decoy Title'),
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? null,
    'visible_text_excludes_xmp' => !str_contains($plainText, 'Current Comment Boundary XMP Title')
        && !str_contains($plainText, 'Comment Decoy XMP Title'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($metadata['title'] ?? 'Untitled PDF', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
