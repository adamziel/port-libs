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
        . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
        . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/">'
        . '<dc:title><rdf:Alt rdf:_2="WordPress Secondary Alt Attribute Title" rdf:_1="' . htmlspecialchars($title, ENT_XML1) . '"/></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>WordPress Alt Attribute Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt rdf:_2="WordPress Secondary Alt Attribute Description" rdf:_1="' . htmlspecialchars($description, ENT_XML1) . '"/></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-alt-attribute-membership</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>WordPress Alt Attribute Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Alt Attribute Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-08T06:05:43Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$pdfWithAltAttributeMetadata = static function (string $metadataBytes, string $bodyText): string {
    $compressedMetadata = gzcompress($metadataBytes);
    if (!is_string($compressedMetadata)) {
        throw new RuntimeException('Unable to compress WordPress XMP Alt attribute membership fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedMetadata) . " >>\nstream\n{$compressedMetadata}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Title (Fallback WordPress Alt Attribute Title) /Author (Fallback Alt Attribute Author) /Producer (Fallback Alt Attribute Producer) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$currentXmp = $xmpPacket(
    'WordPress Alt Attribute XMP Title',
    'RDF Alt membership attributes become WordPress document metadata.',
    '2026-06-08T02:05:43-04:00'
);
$trailingXmp = $xmpPacket(
    'WordPress Trailing Alt Attribute Decoy Title',
    'Trailing Alt attribute packet stays outside WordPress metadata.',
    '2026-06-08T06:59:59Z'
);

$pdf = $pdfWithAltAttributeMetadata(
    $currentXmp . "\0\0 \n" . $trailingXmp,
    'WordPress XMP Alt Attribute Boundary Body'
);

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'WordPress Alt Attribute XMP Title') {
    throw new RuntimeException('Expected RDF Alt membership attribute title to promote from document XMP.');
}
if (($metadata['description'] ?? null) !== 'RDF Alt membership attributes become WordPress document metadata.') {
    throw new RuntimeException('Expected RDF Alt membership attribute description to promote from document XMP.');
}
if (($metadata['info']['Title'] ?? null) !== 'Fallback WordPress Alt Attribute Title') {
    throw new RuntimeException('Expected trailer Info title to remain available as fallback metadata.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'WordPress Secondary Alt Attribute Title')
    || str_contains($encoded, 'WordPress Trailing Alt Attribute Decoy Title')
) {
    throw new RuntimeException('Expected secondary and trailing Alt attribute values to stay out of encoded metadata.');
}
if (str_contains($plainText, 'WordPress Alt Attribute XMP Title')
    || str_contains($plainText, 'WordPress Alt Attribute Editor')
    || str_contains($plainText, 'WordPress Trailing Alt Attribute Decoy Title')
) {
    throw new RuntimeException('Expected XMP metadata to stay out of visible WordPress paragraph text.');
}

echo '<!-- markerpdf-metadata-xmp-alt-attribute-membership-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-metadata-xmp-alt-attribute-membership-currentbase',
    'support_component' => 'native-pdf-xmp-alt-attribute-membership',
    'native_boundary' => 'RDF Alt membership attributes promote ordered document metadata while later packet values remain excluded from WordPress visible text',
    'title_promoted' => ($metadata['title'] ?? null) === 'WordPress Alt Attribute XMP Title',
    'description_promoted' => ($metadata['description'] ?? null) === 'RDF Alt membership attributes become WordPress document metadata.',
    'secondary_alt_title_excluded' => is_string($encoded)
        && !str_contains($encoded, 'WordPress Secondary Alt Attribute Title'),
    'trailing_packet_excluded' => is_string($encoded)
        && !str_contains($encoded, 'WordPress Trailing Alt Attribute Decoy Title'),
    'visible_text_excludes_xmp_metadata' => !str_contains($plainText, 'WordPress Alt Attribute XMP Title')
        && !str_contains($plainText, 'WordPress Alt Attribute Editor'),
    'packet_boundary_applied' => ($metadata['xmp']['packet_boundary_applied'] ?? null) === true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
