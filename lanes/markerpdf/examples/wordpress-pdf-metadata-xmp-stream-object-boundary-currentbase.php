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
        . '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($title, ENT_XML1) . '</rdf:li></rdf:Alt></dc:title>'
        . '<dc:creator><rdf:Seq><rdf:li>WordPress XMP Stream Object Editor</rdf:li><rdf:li>Import Review Team</rdf:li></rdf:Seq></dc:creator>'
        . '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">' . htmlspecialchars($description, ENT_XML1) . '</rdf:li></rdf:Alt></dc:description>'
        . '<dc:subject><rdf:Bag><rdf:li>wordpress</rdf:li><rdf:li>xmp-stream-object-boundary</rdf:li></rdf:Bag></dc:subject>'
        . '<pdf:Producer>WordPress Stream Boundary Producer</pdf:Producer>'
        . '<xmp:CreatorTool>WordPress Stream Boundary Tool</xmp:CreatorTool>'
        . '<xmp:CreateDate>' . htmlspecialchars($date, ENT_XML1) . '</xmp:CreateDate>'
        . '<xmp:MetadataDate>2026-06-05T10:37:10Z</xmp:MetadataDate>'
        . '</rdf:Description>'
        . '</rdf:RDF>'
        . '</x:xmpmeta>'
        . '<?xpacket end="w"?>';
};

$pdfWithMetadataTail = static function (string $xmp, string $metadataTail, string $bodyText): string {
    $compressedXmp = gzcompress($xmp);
    if (!is_string($compressedXmp)) {
        throw new RuntimeException('Unable to compress WordPress XMP stream-object boundary fixture.');
    }

    $content = 'BT /F1 12 Tf 72 720 Td (' . $bodyText . ') Tj ET';

    return "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Metadata 5 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($compressedXmp) . " >>\n"
        . "stream\n{$compressedXmp}\nendstream{$metadataTail}\nendobj\n"
        . "6 0 obj\n<< /Title (Fallback WordPress XMP Stream Title) /Author (Fallback Stream Author) /Producer (Fallback Stream Producer) >>\nendobj\n"
        . "8 0 obj\n<< /S /JavaScript /JS (app.alert\\('wordpress metadata stream object action tail'\\)) >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R /Info 6 0 R >>\n%%EOF";
};

$malformedXmp = $xmpPacket(
    'WordPress Malformed Stream Object XMP Title',
    'A malformed metadata stream object tail must not define WordPress metadata',
    '2026-06-05T06:37:10-04:00'
);
$validXmp = $xmpPacket(
    'WordPress Comment Tail Stream Object XMP Title',
    'A comment-only metadata stream object tail remains valid XMP',
    '2026-06-05T07:37:10-03:00'
);

$malformedPdf = $pdfWithMetadataTail(
    $malformedXmp,
    "\n/A 8 0 R /Next 99 0 R",
    'WordPress malformed XMP stream object body'
);
$validPdf = $pdfWithMetadataTail(
    $validXmp,
    "\n% /A 8 0 R is only a PDF comment after endstream",
    'WordPress valid XMP stream object body'
);

$metadataExtractor = new PdfMetadataExtractor();
$textExtractor = new PdfTextExtractor();

$malformedMetadata = $metadataExtractor->extractDocumentMetadata($malformedPdf);
$validMetadata = $metadataExtractor->extractDocumentMetadata($validPdf);
$malformedText = $textExtractor->extractPlainText($malformedPdf);
$validText = $textExtractor->extractPlainText($validPdf);
$malformedEncoded = json_encode($malformedMetadata, JSON_UNESCAPED_SLASHES);
$validEncoded = json_encode($validMetadata, JSON_UNESCAPED_SLASHES);
$review = $malformedMetadata['catalog']['metadata_stream_review'] ?? [];

if (($malformedMetadata['xmp'] ?? []) !== [] || ($malformedMetadata['title'] ?? null) !== 'Fallback WordPress XMP Stream Title') {
    throw new RuntimeException('Expected malformed metadata stream object to fall back to trailer Info metadata.');
}
if (($review['status'] ?? null) !== 'rejected_malformed_metadata_stream_object') {
    throw new RuntimeException('Expected malformed metadata stream object review status.');
}
if (($validMetadata['title'] ?? null) !== 'WordPress Comment Tail Stream Object XMP Title') {
    throw new RuntimeException('Expected comment-only metadata stream tail to preserve document XMP.');
}
if (!is_string($malformedEncoded)
    || str_contains($malformedEncoded, 'WordPress Malformed Stream Object XMP Title')
    || str_contains($malformedEncoded, 'wordpress metadata stream object action tail')
) {
    throw new RuntimeException('Expected malformed XMP text and action tail to remain out of encoded metadata.');
}
if (str_contains($malformedText, 'WordPress Malformed Stream Object XMP Title')
    || str_contains($validText, 'WordPress Comment Tail Stream Object XMP Title')
) {
    throw new RuntimeException('Expected XMP packet text to stay out of visible WordPress paragraphs.');
}

echo '<!-- markerpdf-metadata-xmp-stream-object-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-metadata-xmp-stream-object-boundary-currentbase',
    'support_component' => 'native-pdf-xmp-stream-object-boundary-review',
    'native_boundary' => 'catalog Metadata stream objects must end after one stream token; comment-only tails remain valid whitespace',
    'malformed_status' => $review['status'] ?? null,
    'malformed_xmp_rejected' => ($malformedMetadata['xmp'] ?? []) === [],
    'malformed_info_fallback_title' => $malformedMetadata['title'] ?? null,
    'malformed_action_tail_excluded' => is_string($malformedEncoded)
        && !str_contains($malformedEncoded, 'wordpress metadata stream object action tail'),
    'malformed_xmp_values_redacted' => is_string($malformedEncoded)
        && !str_contains($malformedEncoded, 'WordPress Malformed Stream Object XMP Title'),
    'comment_tail_xmp_title' => $validMetadata['title'] ?? null,
    'comment_tail_xmp_accepted' => ($validMetadata['xmp']['title'] ?? null) === 'WordPress Comment Tail Stream Object XMP Title',
    'visible_text_excludes_xmp_metadata' => !str_contains($malformedText, 'WordPress Malformed Stream Object XMP Title')
        && !str_contains($validText, 'WordPress Comment Tail Stream Object XMP Title'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($malformedText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:paragraph -->\n<p>"
    . htmlspecialchars($validText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</p>\n<!-- /wp:paragraph -->\n";
