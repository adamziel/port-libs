<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$xmp = '<?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    . '<x:xmpmeta xmlns:x="adobe:ns:meta/">'
    . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    . '<xmp:Document rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/"'
    . ' xmlns:xmp="http://ns.adobe.com/xap/1.0/"'
    . ' xmp:CreatorTool="WordPress Typed Node Tool">'
    . '<xmp:PrivateReview><rdf:RDF><rdf:Description rdf:about=""'
    . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
    . ' xmlns:pdf="http://ns.adobe.com/pdf/1.3/">'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Nested WordPress Decoy XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<pdf:Producer>Nested WordPress Decoy Producer</pdf:Producer>'
    . '</rdf:Description></rdf:RDF></xmp:PrivateReview>'
    . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">WordPress Typed Node XMP Title</rdf:li></rdf:Alt></dc:title>'
    . '<dc:creator><rdf:Seq><rdf:li>Typed Node Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
    . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">Typed RDF node metadata is imported without nested RDF decoys</rdf:li></rdf:Alt></dc:description>'
    . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-typed-node</rdf:li></rdf:Bag></dc:subject>'
    . '<pdf:Producer>WordPress Typed Node Producer</pdf:Producer>'
    . '<xmp:CreateDate>2026-06-05T00:32:33-04:00</xmp:CreateDate>'
    . '<xmp:MetadataDate>2026-06-05T04:32:33Z</xmp:MetadataDate>'
    . '</xmp:Document>'
    . '</rdf:RDF>'
    . '</x:xmpmeta>'
    . '<?xpacket end="w"?>';

$trailingDecoy = str_replace(
    ['WordPress Typed Node XMP Title', 'Typed RDF node metadata is imported without nested RDF decoys'],
    ['Trailing WordPress Typed Node Decoy Title', 'Trailing typed-node packet stays hidden'],
    $xmp
);

$metadataBytes = $xmp . "\0\0 \n" . $trailingDecoy;
$metadataStream = gzcompress($metadataBytes);
if (!is_string($metadataStream)) {
    throw new RuntimeException('Unable to compress WordPress typed-node XMP smoke stream.');
}

$content = 'BT /F1 12 Tf 72 720 Td (WordPress typed-node XMP import body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Title (Typed Node Info Fallback Title) /Author (Typed Node Info Author) /Producer (Typed Node Info Producer) >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($metadata['title'] ?? null) !== 'WordPress Typed Node XMP Title') {
    throw new RuntimeException('Expected top-level RDF typed node title to be imported.');
}
if (($metadata['creator_tool'] ?? null) !== 'WordPress Typed Node Tool') {
    throw new RuntimeException('Expected typed-node XMP attribute shorthand to be imported.');
}
if (!is_string($encoded)
    || str_contains($encoded, 'Nested WordPress Decoy XMP Title')
    || str_contains($encoded, 'Nested WordPress Decoy Producer')
    || str_contains($encoded, 'Trailing WordPress Typed Node Decoy Title')
) {
    throw new RuntimeException('Expected nested and trailing typed-node XMP decoys to stay out of document metadata.');
}
if (str_contains($plainText, 'WordPress Typed Node XMP Title')
    || str_contains($plainText, 'Nested WordPress Decoy XMP Title')
    || str_contains($plainText, 'Trailing WordPress Typed Node Decoy Title')
) {
    throw new RuntimeException('Expected XMP metadata values to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-xmp-typed-node-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-xmp-typed-node-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-metadata-parser',
    'native_boundary' => 'document-level rdf:RDF typed node elements are metadata resources, while nested RDF and trailing packets remain review-only boundaries',
    'title' => $metadata['title'] ?? null,
    'description' => $metadata['description'] ?? null,
    'authors' => $metadata['authors'] ?? [],
    'keywords' => $metadata['keywords'] ?? [],
    'creator_tool' => $metadata['creator_tool'] ?? null,
    'producer' => $metadata['producer'] ?? null,
    'created_at_utc' => $metadata['created_at_utc'] ?? null,
    'metadata_date_utc' => $metadata['metadata_date_utc'] ?? null,
    'typed_node_metadata_imported' => ($metadata['title'] ?? null) === 'WordPress Typed Node XMP Title',
    'attribute_shorthand_imported' => ($metadata['creator_tool'] ?? null) === 'WordPress Typed Node Tool',
    'nested_rdf_decoy_excluded' => is_string($encoded) && !str_contains($encoded, 'Nested WordPress Decoy XMP Title'),
    'trailing_packet_excluded' => is_string($encoded) && !str_contains($encoded, 'Trailing WordPress Typed Node Decoy Title'),
    'visible_text_excludes_xmp_metadata' => !str_contains($plainText, 'WordPress Typed Node XMP Title')
        && !str_contains($plainText, 'Nested WordPress Decoy XMP Title')
        && !str_contains($plainText, 'Trailing WordPress Typed Node Decoy Title'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
