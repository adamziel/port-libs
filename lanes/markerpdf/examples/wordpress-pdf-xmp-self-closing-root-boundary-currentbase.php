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
        . '<dc:creator><rdf:Seq><rdf:li>Self Closing Boundary Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-self-closing-root</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>Self Closing Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>Self Closing Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T05:07:53Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>';
};

$currentXmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="empty-boundary-decoy"/>'
    . $xmpPacket(
        'WordPress Self Closing Root XMP Title',
        'Current XMP metadata follows an empty self-closing wrapper',
        '2026-06-04T21:07:53-04:00'
    )
    . '<?xpacket end="w"?>';
$trailingDecoy = $xmpPacket(
    'Trailing WordPress Self Closing Decoy Title',
    'Trailing self-closing root decoy must stay hidden',
    '2026-06-05T05:59:59Z'
);

$metadataBytes = $currentXmp . "\0\0 \n" . $trailingDecoy;
$compressedMetadata = gzcompress($metadataBytes);
if (!is_string($compressedMetadata)) {
    throw new RuntimeException('Unable to compress self-closing XMP root smoke metadata.');
}

$content = 'BT /F1 12 Tf 72 720 Td (Self Closing XMP WordPress Body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Self Closing Info Title) /Author (Info Fallback Author) /Producer (Info Fallback Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '';

if (($metadata['title'] ?? null) !== 'WordPress Self Closing Root XMP Title') {
    throw new RuntimeException('Expected current XMP root after empty self-closing wrapper to become document metadata.');
}
if (($metadata['xmp']['packet_boundary_applied'] ?? null) !== true) {
    throw new RuntimeException('Expected self-closing XMP root boundary fallback to be recorded.');
}
if (str_contains($encoded, 'Trailing WordPress Self Closing Decoy Title')) {
    throw new RuntimeException('Expected trailing XMP packet to stay outside document metadata.');
}
if (str_contains($plainText, 'WordPress Self Closing Root XMP Title')
    || str_contains($plainText, 'Trailing WordPress Self Closing Decoy Title')
) {
    throw new RuntimeException('Expected XMP metadata values to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-pdf-xmp-self-closing-root-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-self-closing-root-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-root-boundary',
    'native_boundary' => 'empty self-closing xmpmeta wrappers are skipped before selecting the current metadata RDF root',
    'source' => $metadata['source'] ?? [],
    'title' => $metadata['title'] ?? null,
    'description' => $metadata['description'] ?? null,
    'authors' => $metadata['authors'] ?? [],
    'keywords' => $metadata['keywords'] ?? [],
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
    'self_closing_wrapper_skipped' => ($metadata['title'] ?? null) === 'WordPress Self Closing Root XMP Title',
    'packet_boundary_applied' => $metadata['xmp']['packet_boundary_applied'] ?? false,
    'trailing_decoy_excluded' => !str_contains($encoded, 'Trailing WordPress Self Closing Decoy Title'),
    'visible_text_excludes_xmp_metadata' => !str_contains($plainText, 'WordPress Self Closing Root XMP Title')
        && !str_contains($plainText, 'Trailing WordPress Self Closing Decoy Title'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
