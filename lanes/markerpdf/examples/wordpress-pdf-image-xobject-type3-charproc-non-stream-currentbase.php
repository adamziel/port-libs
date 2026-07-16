<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "<< /Fake /Dictionary /Length 99 >>\n"
    . "q 12 0 0 8 1 2 cm /Glyph#20Image Do Q\n"
    . "BT /Fghost 9 Tf (non-stream Type3 review leak) Tj ET\n";
$pageContent = 'BT /Ft3 24 Tf 72 720 Td (A) Tj ET';
$glyphPayload = 'BT /Fghost 9 Tf 0 0 Td (Non-stream Type3 image payload noise) Tj ET';
$glyphCompressed = gzcompress($glyphPayload);
if (!is_string($glyphCompressed)) {
    throw new RuntimeException('Unable to compress non-stream Type3 image review fixture payload.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 10 0 R >>\nendobj\n"
    . "10 0 obj\n<< /Type /Pages /Kids [11 0 R] /Count 1 >>\nendobj\n"
    . "11 0 obj\n<< /Type /Page /Parent 10 0 R /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3NonStreamImageBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding << /Type /Encoding /Differences [65 /A] >> "
    . "/CharProcs << /A 3 0 R >> "
    . "/Resources << /XObject << /Glyph#20Image 5 0 R >> /Font << /Fghost 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n{$charProc}\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray "
    . "/BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($glyphCompressed) . " >>\n"
    . "stream\n{$glyphCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

$metadata = [
    'source' => 'native-pdf-image-xobject-type3-charproc-non-stream-boundary',
    'visible_text_preserved' => $plainText === 'A',
    'non_stream_charproc_image_review_rejected' => ($review['image_xobject_count'] ?? null) === 0
        && ($review['invoked_image_xobject_count'] ?? null) === 0
        && ($review['entries'] ?? []) === [],
    'non_stream_charproc_payload_excluded' => !str_contains($plainText, 'non-stream Type3 review leak'),
    'image_payload_not_promoted' => !str_contains($plainText, 'Non-stream Type3 image payload noise')
        && !str_contains($encodedReview, $glyphPayload),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'visible_text_preserved',
    'non_stream_charproc_image_review_rejected',
    'non_stream_charproc_payload_excluded',
    'image_payload_not_promoted',
] as $requiredFlag) {
    if ($metadata[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProc non-stream image review boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-image-xobject-type3-charproc-non-stream-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
